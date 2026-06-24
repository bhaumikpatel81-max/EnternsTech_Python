import json
import secrets
from datetime import datetime, timedelta, timezone
from fastapi import APIRouter, Request, Form
from fastapi.responses import HTMLResponse, JSONResponse, RedirectResponse
from fastapi.templating import Jinja2Templates
from app.auth import get_current_user, hash_password, create_token
from app.database import fetchone, fetchall, execute
from app.config import settings
from app import email_service
from app.routes.payments import activate_student

router = APIRouter(prefix="/admin")
templates = Jinja2Templates(directory="templates")


def _admin_required(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "admin":
        return None
    return user


# ── Overview ────────────────────────────────────────────────────────────────────

@router.get("", response_class=HTMLResponse)
async def overview(request: Request):
    if not _admin_required(request):
        return RedirectResponse("/login")
    stats = {
        "students": (fetchone("SELECT COUNT(*) AS n FROM students WHERE status='active'") or {}).get("n", 0),
        "mentors":  (fetchone("SELECT COUNT(*) AS n FROM mentors WHERE status='approved'") or {}).get("n", 0),
        "pending_mentors": (fetchone("SELECT COUNT(*) AS n FROM mentors WHERE status='pending'") or {}).get("n", 0),
        "sessions": (fetchone("SELECT COUNT(*) AS n FROM sessions") or {}).get("n", 0),
        "open_requests": (fetchone("SELECT COUNT(*) AS n FROM requests WHERE status='open'") or {}).get("n", 0),
        "assessments": (fetchone("SELECT COUNT(*) AS n FROM psy_assessments") or {}).get("n", 0),
    }
    return templates.TemplateResponse("admin/overview.html", {
        "request": request, "stats": stats, "section": "overview"
    })


# ── Students ────────────────────────────────────────────────────────────────────

@router.get("/students", response_class=HTMLResponse)
async def students(request: Request, q: str = ""):
    if not _admin_required(request):
        return RedirectResponse("/login")
    sql = """SELECT s.*, m.full_name AS mentor_name
             FROM students s LEFT JOIN mentors m ON s.mentor_id=m.id"""
    params = ()
    if q:
        sql += " WHERE s.full_name LIKE %s OR s.email LIKE %s"
        params = (f"%{q}%", f"%{q}%")
    sql += " ORDER BY s.created_at DESC"
    rows = fetchall(sql, params)
    mentors = fetchall("SELECT id, full_name FROM mentors WHERE status='approved' ORDER BY full_name")
    return templates.TemplateResponse("admin/students.html", {
        "request": request, "students": rows, "mentors": mentors,
        "section": "students", "q": q, "plan_catalog": settings.PLAN_CATALOG,
    })


@router.post("/students/activate")
async def activate_student_manual(
    request: Request,
    email:   str = Form(...),
    plan_id: str = Form(...),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    if plan_id not in settings.PLAN_PRICES:
        return JSONResponse({"ok": False, "error": "Invalid plan"})

    # Insert a manual payment record first
    pay_id = execute(
        """INSERT INTO payments (email, plan_id, amount, currency, gateway, status)
           VALUES (%s, %s, %s, 'INR', 'manual', 'paid')""",
        (email, plan_id, round(settings.PLAN_PRICES[plan_id] / 100, 2)),
    )
    result = activate_student(email, plan_id, pay_id)
    return JSONResponse(result)


@router.post("/students/assign-mentor")
async def assign_mentor(request: Request, student_id: int = Form(...), mentor_id: int = Form(...)):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute("UPDATE students SET mentor_id=%s WHERE id=%s", (mentor_id, student_id))
    return JSONResponse({"ok": True})


@router.post("/students/update-sessions")
async def update_sessions(request: Request, student_id: int = Form(...), sessions_total: int = Form(...)):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute("UPDATE students SET sessions_total=%s WHERE id=%s", (sessions_total, student_id))
    return JSONResponse({"ok": True})


# ── Mentors / Applications ──────────────────────────────────────────────────────

@router.get("/mentors", response_class=HTMLResponse)
async def mentors(request: Request, tab: str = "approved"):
    if not _admin_required(request):
        return RedirectResponse("/login")
    if tab == "pending":
        rows = fetchall("SELECT * FROM mentors WHERE status='pending' ORDER BY created_at DESC")
    else:
        rows = fetchall("SELECT * FROM mentors WHERE status='approved' ORDER BY full_name")
    return templates.TemplateResponse("admin/mentors.html", {
        "request": request, "mentors": rows, "section": "mentors", "tab": tab,
    })


@router.post("/mentors/approve")
async def approve_mentor(request: Request, mentor_id: int = Form(...)):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    mentor = fetchone("SELECT * FROM mentors WHERE id=%s", (mentor_id,))
    if not mentor:
        return JSONResponse({"ok": False, "error": "Mentor not found"})

    # Create user account
    user = fetchone("SELECT id FROM users WHERE email=%s LIMIT 1", (mentor["email"],))
    if user:
        uid = user["id"]
        execute("UPDATE users SET role='mentor' WHERE id=%s", (uid,))
    else:
        uid = execute(
            "INSERT INTO users (email, password_hash, role) VALUES (%s, %s, 'mentor')",
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


# ── Payments ────────────────────────────────────────────────────────────────────

@router.get("/payments", response_class=HTMLResponse)
async def payments(request: Request):
    if not _admin_required(request):
        return RedirectResponse("/login")
    rows = fetchall(
        """SELECT p.*, s.full_name AS student_name
           FROM payments p LEFT JOIN students s ON p.student_id=s.id
           ORDER BY p.created_at DESC LIMIT 200"""
    )
    return templates.TemplateResponse("admin/payments.html", {
        "request": request, "payments": rows, "section": "payments",
        "plan_catalog": settings.PLAN_CATALOG,
    })


# ── Requests ────────────────────────────────────────────────────────────────────

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
    return templates.TemplateResponse("admin/requests.html", {
        "request": request, "requests": rows, "section": "requests",
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


# ── Assessments ─────────────────────────────────────────────────────────────────

@router.get("/assessments", response_class=HTMLResponse)
async def assessments(request: Request, tab: str = "list"):
    if not _admin_required(request):
        return RedirectResponse("/login")
    rows = fetchall("SELECT * FROM psy_assessments ORDER BY created_at DESC LIMIT 200")
    return templates.TemplateResponse("admin/assessments.html", {
        "request": request, "assessments": rows, "section": "assessments", "tab": tab,
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
    return templates.TemplateResponse("admin/assessment_detail.html", {
        "request": request, "assessment": row, "scores": scores, "section": "assessments",
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
    assessment_id: int = Form(...),
    recommendation: str = Form(""),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute(
        "UPDATE psy_scores SET recommendation=%s WHERE assessment_id=%s",
        (recommendation, assessment_id),
    )
    return JSONResponse({"ok": True})


# ── Sessions ────────────────────────────────────────────────────────────────────

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
    return templates.TemplateResponse("admin/sessions.html", {
        "request": request, "sessions": rows,
        "students": students, "mentors": mentors, "section": "sessions",
    })


@router.post("/sessions/add")
async def add_session(
    request: Request,
    student_id:   int   = Form(...),
    mentor_id:    int   = Form(...),
    scheduled_at: str   = Form(...),
    duration_min: int   = Form(60),
    status:       str   = Form("planned"),
    notes:        str   = Form(""),
):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    mentor = fetchone("SELECT rate_per_session FROM mentors WHERE id=%s", (mentor_id,))
    rate   = mentor["rate_per_session"] if mentor else 0
    execute(
        """INSERT INTO sessions (student_id, mentor_id, scheduled_at, duration_min, status, rate_applied, notes)
           VALUES (%s,%s,%s,%s,%s,%s,%s)""",
        (student_id, mentor_id, scheduled_at, duration_min, status, rate, notes),
    )
    return JSONResponse({"ok": True})


@router.post("/sessions/mark-complete")
async def mark_complete(request: Request, session_id: int = Form(...), mentor_paid: int = Form(0)):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    execute(
        "UPDATE sessions SET status='completed', mentor_paid=%s WHERE id=%s",
        (bool(mentor_paid), session_id),
    )
    session = fetchone("SELECT student_id FROM sessions WHERE id=%s", (session_id,))
    if session:
        execute("UPDATE students SET sessions_used=sessions_used+1 WHERE id=%s", (session["student_id"],))
    return JSONResponse({"ok": True})


# ── Manual Revenue ───────────────────────────────────────────────────────────────

@router.get("/manual-revenue", response_class=HTMLResponse)
async def manual_revenue(request: Request):
    if not _admin_required(request):
        return RedirectResponse("/login")
    rows = fetchall("SELECT * FROM manual_revenue ORDER BY entry_date DESC LIMIT 500")
    totals = fetchall(
        "SELECT currency, SUM(amount) AS total FROM manual_revenue GROUP BY currency"
    )
    return templates.TemplateResponse("admin/manual_revenue.html", {
        "request": request, "entries": rows, "totals": totals, "section": "manual_revenue",
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


# ── Mentor extra_fields ──────────────────────────────────────────────────────────

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


# ── Psychometric settings (Razorpay toggle per field) ────────────────────────────

@router.get("/assessments/settings", response_class=HTMLResponse)
async def psy_settings(request: Request):
    if not _admin_required(request):
        return RedirectResponse("/login")
    rows = fetchall("SELECT * FROM psy_assessments ORDER BY created_at DESC LIMIT 200")
    row = fetchone(
        "SELECT setting_val FROM app_settings WHERE setting_key='psy_rzp_plans' LIMIT 1"
    )
    try:
        rzp_plans = json.loads(row["setting_val"]) if row else {}
    except Exception:
        rzp_plans = {}
    return templates.TemplateResponse("admin/assessments.html", {
        "request": request, "assessments": rows,
        "section": "assessments", "tab": "settings",
        "rzp_plans": rzp_plans,
        "plan_catalog": settings.PLAN_CATALOG,
    })


@router.post("/assessments/settings")
async def save_psy_settings(request: Request):
    if not _admin_required(request):
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    form = await request.form()
    rzp_plans = {k: True for k in form.keys() if k.startswith("plan_")}
    serialised = json.dumps(rzp_plans)
    existing = fetchone(
        "SELECT id FROM app_settings WHERE setting_key='psy_rzp_plans' LIMIT 1"
    )
    if existing:
        execute(
            "UPDATE app_settings SET setting_val=%s WHERE setting_key='psy_rzp_plans'",
            (serialised,),
        )
    else:
        execute(
            "INSERT INTO app_settings (setting_key, setting_val) VALUES ('psy_rzp_plans', %s)",
            (serialised,),
        )
    return JSONResponse({"ok": True})
