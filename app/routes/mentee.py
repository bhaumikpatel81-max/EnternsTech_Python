from __future__ import annotations
import json
import os
from datetime import datetime, timedelta, timezone

import pymysql
from fastapi import APIRouter, Request
from fastapi.responses import HTMLResponse, JSONResponse, RedirectResponse
from fastapi.templating import Jinja2Templates

from app.auth import get_current_user
from app.database import execute, execute_txn, fetchall, fetchone, fetchone_txn, get_transaction
from app import email_service
from app.services import tz as tz_svc
from app.services.matching import filtered_mentors, fee_breakdown
from app.services.meeting import jitsi_url
from app.services import escrow as escrow_svc
from app.services import scheduling as sched_svc
from app.services import reviews as reviews_svc
from app.services import capacity as cap_svc
from app.services import invoice as invoice_svc

router = APIRouter(prefix="/mentee")
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))


def _get_mentee(user: dict) -> dict | None:
    return fetchone("SELECT * FROM students WHERE user_id=%s LIMIT 1", (user["sub"],))


def _open_slots(mentor: dict, mentee_tz: str = "Asia/Kolkata", days_ahead: int = 14) -> list[dict]:
    """Expand mentor's weekly slots_json into concrete datetimes over the next `days_ahead` days,
    excluding already-booked slots. Keys are UTC strings; display is in the mentee's timezone."""
    raw = mentor.get("slots_json") or "[]"
    try:
        slot_defs = json.loads(raw) if isinstance(raw, str) else raw
    except Exception:
        return []
    if not slot_defs:
        return []

    mentor_tz = mentor.get("timezone") or "Asia/Kolkata"
    day_map = {"MO": 0, "TU": 1, "WE": 2, "TH": 3, "FR": 4, "SA": 5, "SU": 6}

    booked = {
        row["scheduled_at"].strftime("%Y-%m-%d %H:%M")
        for row in fetchall(
            "SELECT scheduled_at FROM sessions WHERE mentor_id=%s AND status IN ('scheduled','confirmed','active')",
            (mentor["id"],),
        )
        if row.get("scheduled_at")
    }

    now_utc = datetime.now(timezone.utc).replace(tzinfo=None)
    result = []
    for offset in range(days_ahead):
        day = now_utc.date() + timedelta(days=offset)
        wd = day.weekday()
        for s in slot_defs:
            if day_map.get(s.get("day", "").upper()) != wd:
                continue
            try:
                h, m = map(int, s["start"].split(":"))
                slot_local = datetime(day.year, day.month, day.day, h, m)
            except Exception:
                continue
            slot_utc = tz_svc.to_utc(slot_local, mentor_tz)
            if slot_utc <= now_utc:
                continue
            key = slot_utc.strftime("%Y-%m-%d %H:%M")
            if key not in booked:
                display = tz_svc.fmt_local(slot_utc, mentee_tz, "%a %d %b, %H:%M %Z")
                result.append({"datetime": key, "display": display})
    return result


# ── Dashboard ────────────────────────────────────────────────────────────────

@router.get("", response_class=HTMLResponse)
async def dashboard(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return RedirectResponse("/login")

    mentee = _get_mentee(user)
    if not mentee:
        return templates.TemplateResponse(request, "student/no_profile.html", {"user": user})

    from app.services.catalog import get_catalog
    from app.services.privacy import student_view_of_mentor
    catalog = get_catalog()
    plan_catalog = catalog["plans"]
    mentee_tz = mentee.get("timezone") or "Asia/Kolkata"

    mentor = None
    open_slots = []
    if mentee.get("mentor_id"):
        raw_mentor = fetchone("SELECT * FROM mentors WHERE id=%s LIMIT 1", (mentee["mentor_id"],))
        if raw_mentor:
            mentor = student_view_of_mentor(raw_mentor)
            open_slots = _open_slots(raw_mentor, mentee_tz)

    mentors_list = filtered_mentors(mentee)

    sessions_raw = fetchall(
        """SELECT s.*, m.full_name AS mentor_name
           FROM sessions s LEFT JOIN mentors m ON s.mentor_id=m.id
           WHERE s.student_id=%s ORDER BY s.scheduled_at DESC LIMIT 20""",
        (mentee["id"],),
    )
    sessions = []
    for s in sessions_raw:
        s = dict(s)
        if s.get("scheduled_at"):
            s["scheduled_local"] = tz_svc.fmt_local(s["scheduled_at"], mentee_tz)
        else:
            s["scheduled_local"] = "—"
        if s.get("status") == "completed":
            rev = fetchone(
                "SELECT id FROM reviews WHERE session_id=%s AND author_role='student'",
                (s["id"],),
            )
            s["review_submitted"] = bool(rev)
        else:
            s["review_submitted"] = False
        sessions.append(s)

    next_session = fetchone(
        """SELECT * FROM sessions
           WHERE student_id=%s AND status IN ('scheduled','confirmed','active') AND scheduled_at > NOW()
           ORDER BY scheduled_at LIMIT 1""",
        (mentee["id"],),
    )
    if next_session and next_session.get("scheduled_at"):
        next_session = dict(next_session)
        next_session["scheduled_local"] = tz_svc.fmt_local(next_session["scheduled_at"], mentee_tz)

    plan_info = plan_catalog.get(mentee.get("plan_id", ""), {})
    sessions_remaining = max(
        0,
        int(mentee.get("sessions_total", 0)) - int(mentee.get("sessions_used", 0)),
    )

    current_plan = mentee.get("plan_id", "")
    current_paise = (plan_catalog.get(current_plan) or {}).get("paise", 0)
    upgrade_plans = {
        pid: p for pid, p in plan_catalog.items()
        if p.get("paise", 0) > current_paise
    }

    return templates.TemplateResponse(request, "student/dashboard.html", {
        "user":               user,
        "student":            mentee,
        "student_tz":         mentee_tz,
        "mentor":             mentor,
        "mentors":            mentors_list,
        "sessions":           sessions,
        "next_session":       next_session,
        "open_slots":         open_slots,
        "plan_info":          plan_info,
        "plan_catalog":       plan_catalog,
        "sessions_remaining": sessions_remaining,
        "upgrade_plans":      upgrade_plans,
        "common_tzs":         tz_svc.COMMON_TZS,
    })


# ── Skills ───────────────────────────────────────────────────────────────────

@router.post("/api/update-skills")
async def update_skills(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    skills = str(data.get("tech_stack", ""))[:2000]
    mentee = _get_mentee(user)
    if not mentee:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    execute("UPDATE students SET tech_stack=%s WHERE id=%s", (skills, mentee["id"]))
    return JSONResponse({"ok": True})


# ── Timezone ─────────────────────────────────────────────────────────────────

@router.post("/api/set-timezone")
async def set_timezone(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    tz_name = str(data.get("timezone", "")).strip()
    if not tz_svc.valid_tz(tz_name):
        return JSONResponse({"ok": False, "error": "Invalid timezone"})
    mentee = _get_mentee(user)
    if not mentee:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    execute("UPDATE students SET timezone=%s WHERE id=%s", (tz_name, mentee["id"]))
    return JSONResponse({"ok": True})


# ── Pick / change mentor ──────────────────────────────────────────────────────

@router.post("/api/pick-mentor")
async def pick_mentor(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    mentor_id = int(data.get("mentor_id", 0))
    mentor = fetchone("SELECT id FROM mentors WHERE id=%s AND status='approved'", (mentor_id,))
    if not mentor:
        return JSONResponse({"ok": False, "error": "Mentor not found"})
    mentee = _get_mentee(user)
    if not mentee:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    result = cap_svc.attach(mentee["id"], mentor_id)
    if not result.get("ok"):
        return JSONResponse({"ok": False, "error": result.get("error", "Cannot assign mentor")})
    execute("UPDATE students SET mentor_id=%s WHERE id=%s", (mentor_id, mentee["id"]))
    return JSONResponse({"ok": True})


@router.post("/api/request-mentor-change")
async def request_mentor_change(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    reason = str(data.get("reason", ""))[:1000]
    mentee = _get_mentee(user)
    if not mentee:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    execute(
        """INSERT INTO requests (type, student_id, mentor_id, payload, status)
           VALUES ('mentor_change', %s, %s, %s, 'open')""",
        (mentee["id"], mentee.get("mentor_id"), reason),
    )
    email_service.send_mentor_change_received(mentee.get("full_name", user["email"]), reason)
    return JSONResponse({"ok": True})


# ── Book slot ─────────────────────────────────────────────────────────────────

@router.post("/api/book-slot")
async def book_slot(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)

    data = await request.json()
    is_bundle = bool(data.get("bundle", False))
    slots_raw: list[str] = data.get("slots", [])
    slot_dt_str = str(data.get("datetime", "")).strip()
    topic = str(data.get("topic", ""))[:255]

    mentee = _get_mentee(user)
    if not mentee:
        return JSONResponse({"ok": False, "error": "Profile not found"})

    sessions_remaining = int(mentee.get("sessions_total", 0)) - int(mentee.get("sessions_used", 0))
    if sessions_remaining <= 0:
        return JSONResponse({"ok": False, "error": "No sessions remaining in your plan."})

    if not mentee.get("mentor_id"):
        return JSONResponse({"ok": False, "error": "No mentor assigned yet."})

    mentor = fetchone(
        "SELECT * FROM mentors WHERE id=%s AND status='approved'", (mentee["mentor_id"],)
    )
    if not mentor:
        return JSONResponse({"ok": False, "error": "Mentor not found."})

    mentee_tz = mentee.get("timezone") or "Asia/Kolkata"
    open_slots = _open_slots(mentor, mentee_tz)
    valid_keys = {s["datetime"] for s in open_slots}

    rate_decimal = float(mentor.get("rate_per_session") or 0)
    rate_paise = int(round(rate_decimal * 100))
    fee = fee_breakdown(rate_paise)

    if is_bundle and slots_raw:
        for s in slots_raw:
            if s not in valid_keys:
                return JSONResponse({"ok": False, "error": f"Slot {s} no longer available."})
        bundle_id: int
        session_ids: list[int]
        url: str
        try:
            with get_transaction() as conn:
                for slot in slots_raw:
                    conflict = fetchone_txn(
                        conn,
                        """SELECT id FROM sessions WHERE mentor_id=%s AND scheduled_at=%s
                           AND status NOT IN ('cancelled','rescheduled','no_show') LIMIT 1 FOR UPDATE""",
                        (mentor["id"], slot),
                    )
                    if conflict:
                        return JSONResponse({"ok": False, "error": f"Slot {slot} no longer available."})
                first_id = execute_txn(
                    conn,
                    """INSERT INTO sessions
                       (student_id, mentor_id, scheduled_at, status, booked_by, topic, rate_applied, mentee_tz)
                       VALUES (%s,%s,%s,'scheduled','mentee',%s,%s,%s)""",
                    (mentee["id"], mentor["id"], slots_raw[0], topic, rate_decimal, mentee_tz),
                )
                bundle_id = first_id
                execute_txn(conn, "UPDATE sessions SET bundle_id=%s WHERE id=%s", (bundle_id, first_id))
                url = jitsi_url(first_id)
                execute_txn(conn, "UPDATE sessions SET meeting_link=%s WHERE id=%s", (url, first_id))
                session_ids = [first_id]
                for slot in slots_raw[1:]:
                    sid = execute_txn(
                        conn,
                        """INSERT INTO sessions
                           (student_id, mentor_id, scheduled_at, status, booked_by, topic,
                            rate_applied, bundle_id, mentee_tz)
                           VALUES (%s,%s,%s,'scheduled','mentee',%s,%s,%s,%s,%s)""",
                        (mentee["id"], mentor["id"], slot, topic, rate_decimal, bundle_id, mentee_tz),
                    )
                    execute_txn(conn, "UPDATE sessions SET meeting_link=%s WHERE id=%s", (jitsi_url(sid), sid))
                    session_ids.append(sid)
        except pymysql.err.IntegrityError:
            return JSONResponse({"ok": False, "error": "One or more slots no longer available."})
        escrow_svc.lock(
            mentee["id"], mentor["id"],
            fee["mentor_gets"] * len(slots_raw),
            len(slots_raw),
            bundle_id=bundle_id,
        )
        email_service.send_booking_confirmation(
            mentee.get("email") or "",
            f"{len(slots_raw)} bundled sessions",
            url,
            (mentor.get("full_name") or "Your mentor").split()[0],
        )
        return JSONResponse({"ok": True, "bundle_id": bundle_id, "sessions": session_ids})

    if slot_dt_str not in valid_keys:
        return JSONResponse({"ok": False, "error": "Slot no longer available."})
    session_id: int
    url: str
    try:
        with get_transaction() as conn:
            conflict = fetchone_txn(
                conn,
                """SELECT id FROM sessions WHERE mentor_id=%s AND scheduled_at=%s
                   AND status NOT IN ('cancelled','rescheduled','no_show') LIMIT 1 FOR UPDATE""",
                (mentor["id"], slot_dt_str),
            )
            if conflict:
                return JSONResponse({"ok": False, "error": "Slot no longer available."})
            session_id = execute_txn(
                conn,
                """INSERT INTO sessions
                   (student_id, mentor_id, scheduled_at, status, booked_by, topic, rate_applied, mentee_tz)
                   VALUES (%s,%s,%s,'scheduled','mentee',%s,%s,%s)""",
                (mentee["id"], mentor["id"], slot_dt_str, topic, rate_decimal, mentee_tz),
            )
            url = jitsi_url(session_id)
            execute_txn(conn, "UPDATE sessions SET meeting_link=%s WHERE id=%s", (url, session_id))
    except pymysql.err.IntegrityError:
        return JSONResponse({"ok": False, "error": "Slot no longer available."})
    escrow_svc.lock(mentee["id"], mentor["id"], fee["mentor_gets"], 1)
    try:
        when_dt = datetime.strptime(slot_dt_str, "%Y-%m-%d %H:%M")
        when_str = tz_svc.fmt_local(when_dt, mentee_tz)
    except Exception:
        when_str = slot_dt_str
    email_service.send_booking_confirmation(
        mentee.get("email") or "",
        when_str,
        url,
        (mentor.get("full_name") or "Your mentor").split()[0],
    )
    return JSONResponse({"ok": True})


# ── Cancel session ────────────────────────────────────────────────────────────

@router.post("/api/cancel-session")
async def cancel_session(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    session_id = int(data.get("session_id", 0))
    reason = str(data.get("reason", ""))[:255]
    mentee = _get_mentee(user)
    if not mentee:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    session = fetchone("SELECT * FROM sessions WHERE id=%s AND student_id=%s", (session_id, mentee["id"]))
    if not session:
        return JSONResponse({"ok": False, "error": "Session not found"})
    return JSONResponse(sched_svc.cancel_session(session_id, "student", reason))


# ── Reschedule session ────────────────────────────────────────────────────────

@router.post("/api/reschedule-session")
async def reschedule_session(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    session_id = int(data.get("session_id", 0))
    new_slot = str(data.get("new_slot", "")).strip()
    mentee = _get_mentee(user)
    if not mentee:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    session = fetchone("SELECT * FROM sessions WHERE id=%s AND student_id=%s", (session_id, mentee["id"]))
    if not session:
        return JSONResponse({"ok": False, "error": "Session not found"})
    mentor = fetchone("SELECT * FROM mentors WHERE id=%s", (session["mentor_id"],))
    if not mentor:
        return JSONResponse({"ok": False, "error": "Mentor not found"})
    mentee_tz = mentee.get("timezone") or "Asia/Kolkata"
    open_slots = _open_slots(mentor, mentee_tz)
    valid_keys = {s["datetime"] for s in open_slots}
    if new_slot not in valid_keys:
        return JSONResponse({"ok": False, "error": "New slot no longer available."})
    return JSONResponse(sched_svc.reschedule(session_id, new_slot, mentee_tz))


# ── Review ────────────────────────────────────────────────────────────────────

@router.post("/api/review")
async def submit_review(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    session_id = int(data.get("session_id", 0))
    rating = int(data.get("rating", 0))
    comment = str(data.get("comment", ""))[:2000]
    mentee = _get_mentee(user)
    if not mentee:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    session = fetchone(
        "SELECT * FROM sessions WHERE id=%s AND student_id=%s AND status='completed'",
        (session_id, mentee["id"]),
    )
    if not session:
        return JSONResponse({"ok": False, "error": "Session not found or not completed"})
    return JSONResponse(reviews_svc.submit(session_id, "student", rating, comment))


# ── Upgrade ───────────────────────────────────────────────────────────────────

@router.post("/api/request-upgrade")
async def request_upgrade(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)

    data = await request.json()
    new_plan_id = str(data.get("plan_id", "")).lower().strip()
    email = str(data.get("email", user.get("email", ""))).strip().lower()

    mentee = _get_mentee(user)
    if not mentee:
        return JSONResponse({"ok": False, "error": "Profile not found"})

    from app.services.catalog import get_plan
    new_plan = get_plan(new_plan_id)
    if not new_plan:
        return JSONResponse({"ok": False, "error": "Invalid plan."})

    current_plan = get_plan(mentee.get("plan_id", "")) or {}
    current_paise = current_plan.get("paise", 0)
    new_paise = new_plan["paise"]

    if new_paise <= current_paise:
        return JSONResponse({"ok": False, "error": "Can only upgrade to a higher-value plan."})

    diff_paise = new_paise - current_paise
    if diff_paise <= 0:
        return JSONResponse({"ok": False, "error": "No payment difference."})

    from app.config import settings
    from app.routes.payments import _rzp_configured
    import httpx, base64, time

    if not _rzp_configured():
        return JSONResponse({"ok": False, "error": "Payment gateway not configured."})

    receipt = f"enp_upgrade_{new_plan_id}_{int(time.time())}"
    auth = base64.b64encode(
        f"{settings.RAZORPAY_KEY_ID}:{settings.RAZORPAY_KEY_SECRET}".encode()
    ).decode()

    async with httpx.AsyncClient(timeout=20) as client:
        resp = await client.post(
            "https://api.razorpay.com/v1/orders",
            headers={"Authorization": f"Basic {auth}", "Content-Type": "application/json"},
            json={"amount": diff_paise, "currency": "INR", "receipt": receipt, "partial_payment": False},
        )

    if resp.status_code != 200:
        body = resp.json()
        msg = body.get("error", {}).get("description", f"Razorpay error {resp.status_code}")
        return JSONResponse({"ok": False, "error": msg})

    order = resp.json()
    payment_id = execute(
        """INSERT INTO payments (email, plan_id, amount, currency, gateway, gateway_order_id, status)
           VALUES (%s,%s,%s,'INR','razorpay',%s,'created')""",
        (email, new_plan_id, diff_paise, order["id"]),
    )

    return JSONResponse({
        "ok": True,
        "order_id":   order["id"],
        "key_id":     settings.RAZORPAY_KEY_ID,
        "amount":     diff_paise,
        "currency":   "INR",
        "payment_id": payment_id,
        "new_plan":   new_plan_id,
    })


# ── Invoice ───────────────────────────────────────────────────────────────────

@router.get("/invoice/{payment_id}", response_class=HTMLResponse)
async def invoice(request: Request, payment_id: int):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return RedirectResponse("/login")
    mentee = _get_mentee(user)
    if not mentee:
        return RedirectResponse("/mentee")

    # G2 — strict ownership: verify payment belongs to this mentee before building invoice.
    # The old check (`data["student"].get("id")`) short-circuited to False when student_id
    # was NULL, letting any mentee view any invoice.  We now assert at the DB row level.
    payment = fetchone(
        "SELECT student_id FROM payments WHERE id=%s LIMIT 1", (payment_id,)
    )
    if not payment or payment.get("student_id") != mentee["id"]:
        return HTMLResponse("<h2>403 — Forbidden</h2>", status_code=403)

    data = invoice_svc.build_invoice(payment_id)
    if not data:
        return RedirectResponse("/mentee")
    return templates.TemplateResponse(request, "invoice.html", {"invoice": data, "user": user})
