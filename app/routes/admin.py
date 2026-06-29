from __future__ import annotations

import json
import os
import secrets
from datetime import datetime, timedelta, timezone

from fastapi import APIRouter, Request, Form
from fastapi.responses import HTMLResponse, JSONResponse, RedirectResponse
from fastapi.templating import Jinja2Templates

from app.auth import get_current_user, hash_password, create_token
from app.database import execute, execute_txn, fetchall, fetchone, get_transaction
from app.config import settings
from app import email_service
from app.routes.payments import activate_student
from app.services import escrow as escrow_svc
from app.services import scheduling as sched_svc
from app.services import reviews as reviews_svc
from app.services import capacity as cap_svc
from app.services import invoice as invoice_svc
from app.services.meeting import jitsi_url
from app.services.matching import fee_breakdown

router = APIRouter(prefix="/admin")
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))


def _admin_required(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "admin":
        return None
    return user


# ── Overview ─────────────────────────────────────────────────────────────────

@router.get("", response_class=HTMLResponse)
async def overview(request: Request):
    if not _admin_required(request):
        return RedirectResponse("/login")
    # Lazy release trigger
    try:
        reviews_svc._lazy_release_due()
    except Exception:
        pass
    stats = {
        "students":       (fetchone("SELECT COUNT(*) AS n FROM students WHERE status='active'") or {}).get("n", 0),
        "mentors":        (fetchone("SELECT COUNT(*) AS n FROM mentors WHERE status='approved'") or {}).get("n", 0),
        "pending_mentors":(fetchone("SELECT COUNT(*) AS n FROM mentors WHERE status='pending'") or {}).get("n", 0),
        "sessions":       (fetchone("SELECT COUNT(*) AS n FROM sessions") or {}).get("n", 0),
        "open_requests":  (fetchone("SELECT COUNT(*) AS n FROM requests WHERE status='open'") or {}).get("n", 0),
        "assessments":    (fetchone("SELECT COUNT(*) AS n FROM psy_assessments") or {}).get("n", 0),
    }
    return templates.TemplateResponse(request, "admin/overview.html", {
        "stats": stats, "section": "overview"
    })


# ── Students ──────────────────────────────────────────────────────────────────

@router.get("/mentees", response_class=HTMLResponse)
async def students(request: Request, q: str = ""):
    if not _admin_required(request):
        return RedirectResponse("/login")
    sql = """
        SELECT s.*, m.full_name AS mentor_name,
               (SELECT MIN(ses.scheduled_at)
                FROM sessions ses
                WHERE ses.student_id=s.id AND ses.status IN ('scheduled','confirmed','active')
                  AND ses.scheduled_at > NOW()
               ) AS next_session_at
        FROM students s LEFT JOIN mentors m ON s.mentor_id=m.id"""
    params: tuple = ()
    if q:
        sql += " WHERE s.full_name LIKE %s OR s.email LIKE %s"
        params = (f"%{q}%", f"%{q}%")
    sql += " ORDER BY s.created_at DESC"
    rows = fetchall(sql, params)
    # Offense counts
    for r in rows:
        r["offense_count"] = sched_svc.offense_count(r["id"], "student")
    mentors = fetchall("SELECT id, full_name FROM mentors WHERE status='approved' ORDER BY full_name")
    from app.services.catalog import get_catalog
    plan_catalog = get_catalog()["plans"]
    return templates.TemplateResponse(request, "admin/students.html", {
        "students": rows, "mentors": mentors,
        "section": "students", "q": q, "plan_catalog": plan_catalog,
    })


@router.post("/mentees/activate")
async def activate_student_manual(
    request: Request,
    email:   str = Form(...),
    plan_id: str = Form(...),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    from app.services.catalog import get_plan
    plan = get_plan(plan_id)
    if not plan:
        return JSONResponse({"ok": False, "error": "Invalid plan"})
    pay_id = execute(
        "INSERT INTO payments (email, plan_id, amount, currency, gateway, status) VALUES (%s,%s,%s,'INR','manual','paid')",
        (email, plan_id, round(plan["paise"] / 100, 2)),
    )
    result = activate_student(email, plan_id, pay_id)
    return JSONResponse(result)


@router.post("/mentees/assign-mentor")
async def assign_mentor(request: Request, student_id: int = Form(...), mentor_id: int = Form(...)):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    result = cap_svc.attach(student_id, mentor_id, admin_override=True)
    if not result.get("ok") and result.get("error") != "Already assigned":
        return JSONResponse({"ok": False, "error": result.get("error", "Cannot assign")})
    execute("UPDATE students SET mentor_id=%s WHERE id=%s", (mentor_id, student_id))
    return JSONResponse({"ok": True})


@router.post("/mentees/update-sessions")
async def update_sessions(request: Request, student_id: int = Form(...), sessions_total: int = Form(...)):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute("UPDATE students SET sessions_total=%s WHERE id=%s", (sessions_total, student_id))
    return JSONResponse({"ok": True})


# ── Mentors / Applications ────────────────────────────────────────────────────

@router.get("/mentors", response_class=HTMLResponse)
async def mentors(request: Request, tab: str = "approved"):
    if not _admin_required(request):
        return RedirectResponse("/login")
    if tab == "pending":
        rows = fetchall("SELECT * FROM mentors WHERE status='pending' ORDER BY created_at DESC")
    else:
        rows = fetchall("SELECT * FROM mentors WHERE status='approved' ORDER BY full_name")
        for r in rows:
            r["avg_rating"] = reviews_svc.mentor_avg(r["id"])
            r["offense_count"] = sched_svc.offense_count(r["id"], "mentor")
    return templates.TemplateResponse(request, "admin/mentors.html", {
        "mentors": rows, "section": "mentors", "tab": tab,
    })


@router.post("/mentors/approve")
async def approve_mentor(request: Request, mentor_id: int = Form(...)):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    mentor = fetchone("SELECT * FROM mentors WHERE id=%s", (mentor_id,))
    if not mentor:
        return JSONResponse({"ok": False, "error": "Mentor not found"})
    user = fetchone("SELECT id FROM users WHERE email=%s LIMIT 1", (mentor["email"],))
    if user:
        uid = user["id"]
        execute("UPDATE users SET role='mentor' WHERE id=%s", (uid,))
    else:
        uid = execute(
            "INSERT INTO users (email, password_hash, role) VALUES (%s,%s,'mentor')",
            (mentor["email"], hash_password("ChangeMe123!")),
        )
    execute("UPDATE mentors SET status='approved', user_id=%s WHERE id=%s", (uid, mentor_id))
    token  = create_token({"user_id": uid, "purpose": "set_password"}, expires_minutes=1440)
    pw_url = f"{settings.APP_BASE_URL}/set-password?token={token}"
    email_service.send_mentor_approved(mentor["email"], pw_url)
    return JSONResponse({"ok": True})


@router.post("/mentors/reject")
async def reject_mentor(
    request: Request,
    mentor_id: int = Form(...),
    admin_note: str = Form(""),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    mentor = fetchone("SELECT email FROM mentors WHERE id=%s", (mentor_id,))
    if not mentor:
        return JSONResponse({"ok": False, "error": "Not found"})
    execute("UPDATE mentors SET status='rejected', admin_note=%s WHERE id=%s", (admin_note, mentor_id))
    email_service.send_mentor_rejected(mentor["email"], admin_note)
    return JSONResponse({"ok": True})


@router.post("/mentors/request-info")
async def request_info(
    request: Request,
    mentor_id: int = Form(...),
    admin_note: str = Form(...),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    mentor = fetchone("SELECT email FROM mentors WHERE id=%s", (mentor_id,))
    if not mentor:
        return JSONResponse({"ok": False, "error": "Not found"})
    execute("UPDATE mentors SET status='info_requested', admin_note=%s WHERE id=%s", (admin_note, mentor_id))
    email_service.send_mentor_info_request(mentor["email"], admin_note)
    return JSONResponse({"ok": True})


@router.post("/mentors/update-rate")
async def update_rate(request: Request, mentor_id: int = Form(...), rate_per_session: float = Form(...)):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute("UPDATE mentors SET rate_per_session=%s WHERE id=%s", (rate_per_session, mentor_id))
    return JSONResponse({"ok": True})


@router.post("/mentors/update-extra-fields")
async def update_extra_fields(
    request: Request,
    mentor_id:    int = Form(...),
    extra_fields: str = Form("{}"),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    try:
        parsed = json.loads(extra_fields)
    except (ValueError, TypeError):
        return JSONResponse({"ok": False, "error": "Invalid JSON"})
    execute("UPDATE mentors SET extra_fields=%s WHERE id=%s", (json.dumps(parsed), mentor_id))
    return JSONResponse({"ok": True})


@router.post("/mentors/payout")
async def mentor_payout(
    request:   Request,
    mentor_id: int = Form(...),
    notes:     str = Form(""),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    mentor = fetchone("SELECT id, full_name FROM mentors WHERE id=%s", (mentor_id,))
    if not mentor:
        return JSONResponse({"ok": False, "error": "Mentor not found"})
    result = escrow_svc.payout_mentor(mentor_id, notes)
    return JSONResponse(result)


# ── Payments ──────────────────────────────────────────────────────────────────

@router.get("/payments", response_class=HTMLResponse)
async def payments(request: Request):
    if not _admin_required(request):
        return RedirectResponse("/login")
    rows = fetchall(
        "SELECT p.*, s.full_name AS student_name FROM payments p LEFT JOIN students s ON p.student_id=s.id ORDER BY p.created_at DESC LIMIT 200"
    )
    return templates.TemplateResponse(request, "admin/payments.html", {
        "payments": rows, "section": "payments",
        "plan_catalog": settings.PLAN_CATALOG,
    })


# ── Requests ──────────────────────────────────────────────────────────────────

@router.get("/requests", response_class=HTMLResponse)
async def requests_view(request: Request):
    if not _admin_required(request):
        return RedirectResponse("/login")
    rows = fetchall(
        """SELECT r.*, s.full_name AS student_name, s.email AS student_email,
                  m.full_name AS mentor_name
           FROM requests r
           LEFT JOIN students s ON r.student_id=s.id
           LEFT JOIN mentors m  ON r.mentor_id=m.id
           WHERE r.status='open' ORDER BY r.created_at DESC"""
    )
    return templates.TemplateResponse(request, "admin/requests.html", {
        "requests": rows, "section": "requests",
    })


@router.post("/requests/approve")
async def approve_request(request: Request, request_id: int = Form(...), new_mentor_id: int = Form(0)):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    req = fetchone("SELECT * FROM requests WHERE id=%s", (request_id,))
    if not req:
        return JSONResponse({"ok": False, "error": "Not found"})
    execute("UPDATE requests SET status='approved' WHERE id=%s", (request_id,))
    if req["type"] == "mentor_change" and new_mentor_id:
        cap_svc.attach(req["student_id"], new_mentor_id, admin_override=True)
        execute("UPDATE students SET mentor_id=%s WHERE id=%s", (new_mentor_id, req["student_id"]))
        student = fetchone("SELECT email FROM students WHERE id=%s", (req["student_id"],))
        mentor  = fetchone("SELECT full_name FROM mentors WHERE id=%s", (new_mentor_id,))
        if student and mentor:
            email_service.send_mentor_change_approved(student["email"], mentor["full_name"])
    return JSONResponse({"ok": True})


@router.post("/requests/deny")
async def deny_request(request: Request, request_id: int = Form(...), admin_note: str = Form("")):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    req = fetchone("SELECT student_id FROM requests WHERE id=%s", (request_id,))
    if not req:
        return JSONResponse({"ok": False, "error": "Not found"})
    execute("UPDATE requests SET status='denied', admin_note=%s WHERE id=%s", (admin_note, request_id))
    student = fetchone("SELECT email FROM students WHERE id=%s", (req["student_id"],))
    if student:
        email_service.send_mentor_change_denied(student["email"], admin_note)
    return JSONResponse({"ok": True})


# ── Sessions ──────────────────────────────────────────────────────────────────

@router.get("/sessions", response_class=HTMLResponse)
async def sessions_view(request: Request):
    if not _admin_required(request):
        return RedirectResponse("/login")
    rows = fetchall(
        """SELECT ses.*, s.full_name AS student_name, m.full_name AS mentor_name
           FROM sessions ses
           LEFT JOIN students s ON ses.student_id=s.id
           LEFT JOIN mentors m  ON ses.mentor_id=m.id
           ORDER BY ses.scheduled_at DESC LIMIT 200"""
    )
    students = fetchall("SELECT id, full_name FROM students WHERE status='active' ORDER BY full_name")
    mentors  = fetchall("SELECT id, full_name FROM mentors WHERE status='approved' ORDER BY full_name")
    # Escrow summary per session (lightweight)
    escrow_map = {}
    escrows = fetchall("SELECT * FROM escrow ORDER BY created_at DESC LIMIT 500")
    for e in escrows:
        key = (e["student_id"], e["mentor_id"])
        if key not in escrow_map:
            escrow_map[key] = e
    return templates.TemplateResponse(request, "admin/sessions.html", {
        "sessions": rows, "students": students, "mentors": mentors,
        "section": "sessions", "escrow_map": escrow_map,
    })


@router.post("/sessions/add")
async def add_session(
    request: Request,
    student_id:   int   = Form(...),
    mentor_id:    int   = Form(...),
    scheduled_at: str   = Form(...),
    duration_min: int   = Form(60),
    status:       str   = Form("scheduled"),
    notes:        str   = Form(""),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    mentor = fetchone("SELECT rate_per_session FROM mentors WHERE id=%s", (mentor_id,))
    rate_decimal = float((mentor or {}).get("rate_per_session") or 0)
    rate_paise = int(round(rate_decimal * 100))
    fee = fee_breakdown(rate_paise)
    session_id = execute(
        """INSERT INTO sessions
           (student_id, mentor_id, scheduled_at, duration_min, status, rate_applied, notes, booked_by)
           VALUES (%s,%s,%s,%s,%s,%s,%s,'admin')""",
        (student_id, mentor_id, scheduled_at, duration_min, status, rate_decimal, notes),
    )
    url = jitsi_url(session_id)
    execute("UPDATE sessions SET meeting_link=%s WHERE id=%s", (url, session_id))
    if rate_paise > 0:
        escrow_svc.lock(student_id, mentor_id, fee["mentor_gets"], 1)
    return JSONResponse({"ok": True, "session_id": session_id, "meeting_link": url})


@router.post("/sessions/mark-complete")
async def mark_complete(request: Request, session_id: int = Form(...), mentor_paid: int = Form(0)):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    session = fetchone("SELECT * FROM sessions WHERE id=%s", (session_id,))
    if not session:
        return JSONResponse({"ok": False, "error": "Session not found"})
    with get_transaction() as conn:
        execute_txn(
            conn,
            "UPDATE sessions SET status='completed', mentor_paid=%s WHERE id=%s",
            (bool(mentor_paid), session_id),
        )
        execute_txn(
            conn,
            "UPDATE students SET sessions_used=sessions_used+1 WHERE id=%s",
            (session["student_id"],),
        )
    escrow_svc.release_for_session(session_id)
    return JSONResponse({"ok": True})


@router.post("/sessions/cancel")
async def cancel_session(
    request: Request,
    session_id: int = Form(...),
    reason:     str = Form(""),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    return JSONResponse(sched_svc.cancel_session(session_id, "admin", reason))


@router.post("/sessions/no-show")
async def no_show(
    request: Request,
    session_id: int = Form(...),
    no_show_by: str = Form("student"),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    return JSONResponse(sched_svc.mark_no_show(session_id, no_show_by))


@router.post("/sessions/reschedule")
async def reschedule_session(
    request: Request,
    session_id:   int = Form(...),
    new_slot_utc: str = Form(...),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    session = fetchone("SELECT mentee_tz FROM sessions WHERE id=%s", (session_id,))
    mentee_tz = (session or {}).get("mentee_tz") or "Asia/Kolkata"
    return JSONResponse(sched_svc.reschedule(session_id, new_slot_utc, mentee_tz))


# ── Escrow ────────────────────────────────────────────────────────────────────

@router.post("/escrow/force-refund")
async def force_refund(request: Request, escrow_id: int = Form(...), reason: str = Form("")):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    return JSONResponse(escrow_svc.refund(escrow_id, reason or "Admin force refund"))


# ── Reviews ───────────────────────────────────────────────────────────────────

@router.get("/reviews", response_class=HTMLResponse)
async def reviews_page(request: Request):
    if not _admin_required(request):
        return RedirectResponse("/login")
    rows = reviews_svc.admin_list()
    return templates.TemplateResponse(request, "admin/reviews.html", {
        "reviews": rows, "section": "reviews",
    })


@router.post("/reviews/release-due")
async def trigger_release_due(request: Request):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    count = reviews_svc.release_due()
    return JSONResponse({"ok": True, "released": count})


# ── Assessments ───────────────────────────────────────────────────────────────

@router.get("/assessments", response_class=HTMLResponse)
async def assessments(request: Request, tab: str = "list"):
    if not _admin_required(request):
        return RedirectResponse("/login")
    rows = fetchall("SELECT * FROM psy_assessments ORDER BY created_at DESC LIMIT 200")
    return templates.TemplateResponse(request, "admin/assessments.html", {
        "assessments": rows, "section": "assessments", "tab": tab,
        "plan_catalog": settings.PLAN_CATALOG, "rzp_plans": {},
    })


@router.get("/assessments/{assessment_id}", response_class=HTMLResponse)
async def assessment_detail(request: Request, assessment_id: int):
    if not _admin_required(request):
        return RedirectResponse("/login")
    row = fetchone("SELECT * FROM psy_assessments WHERE id=%s", (assessment_id,))
    if not row:
        return RedirectResponse("/admin/assessments")
    scores = fetchone("SELECT * FROM psy_scores WHERE assessment_id=%s", (assessment_id,))
    if scores:
        for field in ("strengths_clusters", "motivation_top3", "open_responses"):
            if scores.get(field) and isinstance(scores[field], str):
                try:
                    scores[field] = json.loads(scores[field])
                except Exception:
                    pass
    return templates.TemplateResponse(request, "admin/assessment_detail.html", {
        "assessment": row, "scores": scores, "section": "assessments",
    })


@router.post("/assessments/generate-link")
async def generate_link(
    request: Request,
    candidate_name:  str = Form(...),
    candidate_email: str = Form(...),
    candidate_phone: str = Form(""),
    region:          str = Form("UK"),
    education_level: int = Form(2),
    field:           str = Form("IT"),
    payment_ref:     str = Form(""),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    token   = secrets.token_hex(32)
    expires = datetime.now(timezone.utc) + timedelta(days=settings.PSY_LINK_EXPIRY_DAYS)
    user    = get_current_user(request)
    execute(
        """INSERT INTO psy_assessments
           (token, candidate_name, candidate_email, candidate_phone,
            region, education_level, field, created_by, payment_ref, status, expires_at)
           VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,'pending',%s)""",
        (token, candidate_name, candidate_email, candidate_phone,
         region.upper(), education_level, field.upper(),
         user.get("sub"), payment_ref, expires.replace(tzinfo=None)),
    )
    url = f"{settings.APP_BASE_URL}/assessment?t={token}"
    return JSONResponse({"ok": True, "token": token, "url": url})


@router.post("/assessments/send-link")
async def send_link(request: Request, assessment_id: int = Form(...)):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    row = fetchone("SELECT * FROM psy_assessments WHERE id=%s", (assessment_id,))
    if not row:
        return JSONResponse({"ok": False, "error": "Not found"})
    url = f"{settings.APP_BASE_URL}/assessment?t={row['token']}"
    ok  = email_service.send_assessment_link(row["candidate_email"], row["candidate_name"], url)
    return JSONResponse({"ok": ok})


@router.post("/assessments/save-recommendation")
async def save_recommendation(
    request: Request,
    assessment_id:  int = Form(...),
    recommendation: str = Form(""),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute("UPDATE psy_scores SET recommendation=%s WHERE assessment_id=%s", (recommendation, assessment_id))
    return JSONResponse({"ok": True})


@router.get("/assessments/settings", response_class=HTMLResponse)
async def psy_settings(request: Request):
    if not _admin_required(request):
        return RedirectResponse("/login")
    rows = fetchall("SELECT * FROM psy_assessments ORDER BY created_at DESC LIMIT 200")
    row = fetchone("SELECT setting_val FROM app_settings WHERE setting_key='psy_rzp_plans' LIMIT 1")
    try:
        rzp_plans = json.loads(row["setting_val"]) if row else {}
    except Exception:
        rzp_plans = {}
    return templates.TemplateResponse(request, "admin/assessments.html", {
        "assessments": rows, "section": "assessments", "tab": "settings",
        "rzp_plans": rzp_plans, "plan_catalog": settings.PLAN_CATALOG,
    })


@router.post("/assessments/settings")
async def save_psy_settings(request: Request):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    form = await request.form()
    rzp_plans = {k: True for k in form.keys() if k.startswith("plan_")}
    serialised = json.dumps(rzp_plans)
    existing = fetchone("SELECT id FROM app_settings WHERE setting_key='psy_rzp_plans' LIMIT 1")
    if existing:
        execute("UPDATE app_settings SET setting_val=%s WHERE setting_key='psy_rzp_plans'", (serialised,))
    else:
        execute("INSERT INTO app_settings (setting_key, setting_val) VALUES ('psy_rzp_plans',%s)", (serialised,))
    return JSONResponse({"ok": True})


# ── Catalog ───────────────────────────────────────────────────────────────────

@router.get("/catalog", response_class=HTMLResponse)
async def catalog_page(request: Request, tab: str = "plans"):
    if not _admin_required(request):
        return RedirectResponse("/login")
    from app.services.catalog import get_catalog
    cat = get_catalog()
    return templates.TemplateResponse(request, "admin/catalog.html", {
        "plans": cat["plans"], "combos": cat["combos"], "discounts": cat["discounts"],
        "section": "catalog", "tab": tab,
    })


@router.post("/catalog/plan/{plan_id}")
async def update_plan(
    request: Request, plan_id: str,
    name: str = Form(...), tagline: str = Form(""),
    price_dom: str = Form(""), price_intl: str = Form(""),
    paise: int = Form(...), cents: int = Form(0), sessions: int = Form(4),
    badge: str = Form(""), featured: int = Form(0), active: int = Form(1), sort_order: int = Form(0),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    sessions = max(4, min(8, sessions))
    form = await request.form()
    features_json = form.get("features_json", "[]")
    try:
        features = json.loads(features_json)
    except Exception:
        features = []
    execute(
        """UPDATE plans SET name=%s, tagline=%s, price_dom=%s, price_intl=%s, paise=%s, cents=%s,
           sessions=%s, badge=%s, featured=%s, active=%s, sort_order=%s WHERE id=%s""",
        (name, tagline, price_dom, price_intl, paise, cents,
         sessions, badge, bool(featured), bool(active), sort_order, plan_id),
    )
    execute("DELETE FROM plan_features WHERE plan_id=%s", (plan_id,))
    for i, feat in enumerate(features):
        if str(feat).strip():
            execute(
                "INSERT INTO plan_features (plan_id, feature, sort_order) VALUES (%s,%s,%s)",
                (plan_id, str(feat).strip(), i),
            )
    return JSONResponse({"ok": True})


@router.post("/catalog/combo/{combo_id}")
async def update_combo(
    request: Request, combo_id: str,
    name: str = Form(...), price_dom: str = Form(""), price_intl: str = Form(""),
    paise: int = Form(...), cents: int = Form(0), note: str = Form(""),
    active: int = Form(1), sort_order: int = Form(0),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute(
        """UPDATE combos SET name=%s, price_dom=%s, price_intl=%s, paise=%s, cents=%s,
           note=%s, active=%s, sort_order=%s WHERE id=%s""",
        (name, price_dom, price_intl, paise, cents, note, bool(active), sort_order, combo_id),
    )
    return JSONResponse({"ok": True})


@router.post("/catalog/discount")
async def create_discount(
    request: Request,
    label: str = Form(...), code: str = Form(""), kind: str = Form("percent"),
    value: float = Form(...), currency: str = Form("INR"), applies_to: str = Form("all"),
    starts_at: str = Form(""), ends_at: str = Form(""),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute(
        "INSERT INTO discounts (label, code, kind, value, currency, applies_to, starts_at, ends_at) VALUES (%s,%s,%s,%s,%s,%s,%s,%s)",
        (label, code.strip() or None, kind, value, currency.upper(), applies_to, starts_at.strip() or None, ends_at.strip() or None),
    )
    return JSONResponse({"ok": True})


@router.post("/catalog/discount/{discount_id}/toggle")
async def toggle_discount(request: Request, discount_id: int):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute("UPDATE discounts SET active=1-active WHERE id=%s", (discount_id,))
    return JSONResponse({"ok": True})


@router.post("/catalog/discount/{discount_id}/delete")
async def delete_discount(request: Request, discount_id: int):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute("DELETE FROM discounts WHERE id=%s", (discount_id,))
    return JSONResponse({"ok": True})


# ── Manual Revenue ────────────────────────────────────────────────────────────

@router.get("/manual-revenue", response_class=HTMLResponse)
async def manual_revenue(request: Request):
    if not _admin_required(request):
        return RedirectResponse("/login")
    rows   = fetchall("SELECT * FROM manual_revenue ORDER BY entry_date DESC LIMIT 500")
    totals = fetchall("SELECT currency, SUM(amount) AS total FROM manual_revenue GROUP BY currency")
    return templates.TemplateResponse(request, "admin/manual_revenue.html", {
        "entries": rows, "totals": totals, "section": "manual_revenue",
    })


@router.post("/manual-revenue/add")
async def manual_revenue_add(
    request: Request,
    entry_date:  str   = Form(...),
    amount:      float = Form(...),
    currency:    str   = Form("INR"),
    category:    str   = Form("other"),
    description: str   = Form(""),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute(
        "INSERT INTO manual_revenue (entry_date, amount, currency, category, description) VALUES (%s,%s,%s,%s,%s)",
        (entry_date, amount, currency.upper(), category, description),
    )
    return JSONResponse({"ok": True})


@router.post("/manual-revenue/delete")
async def manual_revenue_delete(request: Request, entry_id: int = Form(...)):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute("DELETE FROM manual_revenue WHERE id=%s", (entry_id,))
    return JSONResponse({"ok": True})


# ── Invoice ───────────────────────────────────────────────────────────────────

@router.get("/invoice/{payment_id}", response_class=HTMLResponse)
async def invoice(request: Request, payment_id: int):
    if not _admin_required(request):
        return RedirectResponse("/login")
    data = invoice_svc.build_invoice(payment_id)
    if not data:
        return RedirectResponse("/admin/payments")
    user = get_current_user(request)
    return templates.TemplateResponse(request, "invoice.html", {"data": data, "user": user})
