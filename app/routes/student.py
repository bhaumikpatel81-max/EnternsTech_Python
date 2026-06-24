import json
import os
from datetime import datetime, timedelta, timezone
from fastapi import APIRouter, Request
from fastapi.responses import HTMLResponse, JSONResponse, RedirectResponse
from fastapi.templating import Jinja2Templates
from app.auth import get_current_user
from app.database import fetchone, fetchall, execute
from app import email_service

router = APIRouter(prefix="/student")
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))


def _get_student(user: dict) -> dict | None:
    return fetchone("SELECT * FROM students WHERE user_id=%s LIMIT 1", (user["sub"],))


def _open_slots(mentor: dict, days_ahead: int = 14) -> list[dict]:
    """Expand mentor's weekly slots_json into concrete datetimes over the next `days_ahead` days,
    excluding slots already booked (status='scheduled')."""
    raw = mentor.get("slots_json") or "[]"
    try:
        slot_defs = json.loads(raw) if isinstance(raw, str) else raw
    except Exception:
        return []

    if not slot_defs:
        return []

    day_map = {"MO": 0, "TU": 1, "WE": 2, "TH": 3, "FR": 4, "SA": 5, "SU": 6}
    booked = {
        row["scheduled_at"].strftime("%Y-%m-%d %H:%M")
        for row in fetchall(
            "SELECT scheduled_at FROM sessions WHERE mentor_id=%s AND status='scheduled'",
            (mentor["id"],),
        )
        if row.get("scheduled_at")
    }

    now = datetime.now(timezone.utc).replace(tzinfo=None)
    result = []
    for offset in range(days_ahead):
        day = now.date() + timedelta(days=offset)
        wd = day.weekday()
        for s in slot_defs:
            if day_map.get(s.get("day", "").upper()) != wd:
                continue
            try:
                h, m = map(int, s["start"].split(":"))
                slot_dt = datetime(day.year, day.month, day.day, h, m)
            except Exception:
                continue
            if slot_dt <= now:
                continue
            key = slot_dt.strftime("%Y-%m-%d %H:%M")
            if key not in booked:
                result.append({"datetime": key, "display": slot_dt.strftime("%a %d %b, %H:%M")})
    return result


@router.get("", response_class=HTMLResponse)
async def dashboard(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return RedirectResponse("/login")

    student = _get_student(user)
    if not student:
        return templates.TemplateResponse(request, "student/no_profile.html", {"user": user})

    from app.services.catalog import get_catalog
    from app.services.privacy import student_view_of_mentor
    catalog = get_catalog()
    plan_catalog = catalog["plans"]

    mentor = None
    open_slots = []
    if student.get("mentor_id"):
        raw_mentor = fetchone("SELECT * FROM mentors WHERE id=%s LIMIT 1", (student["mentor_id"],))
        if raw_mentor:
            mentor = student_view_of_mentor(raw_mentor)
            open_slots = _open_slots(raw_mentor)

    # All approved mentors as cards (privacy-masked)
    all_mentors = [
        student_view_of_mentor(m)
        for m in fetchall("SELECT * FROM mentors WHERE status='approved' ORDER BY full_name")
    ]

    sessions = fetchall(
        """SELECT s.*, m.full_name AS mentor_name
           FROM sessions s LEFT JOIN mentors m ON s.mentor_id=m.id
           WHERE s.student_id=%s ORDER BY s.scheduled_at DESC LIMIT 20""",
        (student["id"],),
    )

    next_session = fetchone(
        """SELECT * FROM sessions
           WHERE student_id=%s AND status='scheduled' AND scheduled_at > NOW()
           ORDER BY scheduled_at LIMIT 1""",
        (student["id"],),
    )

    plan_info = plan_catalog.get(student.get("plan_id", ""), {})
    sessions_remaining = max(
        0,
        int(student.get("sessions_total", 0)) - int(student.get("sessions_used", 0)),
    )

    # Plans above current (for upgrade)
    current_plan = student.get("plan_id", "")
    current_paise = (plan_catalog.get(current_plan) or {}).get("paise", 0)
    upgrade_plans = {
        pid: p for pid, p in plan_catalog.items()
        if p.get("paise", 0) > current_paise
    }

    return templates.TemplateResponse(request, "student/dashboard.html", {
        "user":               user,
        "student":            student,
        "mentor":             mentor,
        "mentors":            all_mentors,
        "sessions":           sessions,
        "next_session":       next_session,
        "open_slots":         open_slots,
        "plan_info":          plan_info,
        "plan_catalog":       plan_catalog,
        "sessions_remaining": sessions_remaining,
        "upgrade_plans":      upgrade_plans,
    })


@router.post("/api/update-skills")
async def update_skills(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    skills = str(data.get("tech_stack", ""))[:2000]
    student = _get_student(user)
    if not student:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    execute("UPDATE students SET tech_stack=%s WHERE id=%s", (skills, student["id"]))
    return JSONResponse({"ok": True})


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
    student = _get_student(user)
    if not student:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    execute("UPDATE students SET mentor_id=%s WHERE id=%s", (mentor_id, student["id"]))
    return JSONResponse({"ok": True})


@router.post("/api/request-mentor-change")
async def request_mentor_change(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    reason = str(data.get("reason", ""))[:1000]
    student = _get_student(user)
    if not student:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    execute(
        """INSERT INTO requests (type, student_id, mentor_id, payload, status)
           VALUES ('mentor_change', %s, %s, %s, 'open')""",
        (student["id"], student.get("mentor_id"), reason),
    )
    email_service.send_mentor_change_received(student.get("full_name", user["email"]), reason)
    return JSONResponse({"ok": True})


@router.post("/api/book-slot")
async def book_slot(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)

    data = await request.json()
    slot_dt_str = str(data.get("datetime", "")).strip()   # "YYYY-MM-DD HH:MM"
    topic = str(data.get("topic", ""))[:255]

    student = _get_student(user)
    if not student:
        return JSONResponse({"ok": False, "error": "Profile not found"})

    sessions_remaining = int(student.get("sessions_total", 0)) - int(student.get("sessions_used", 0))
    if sessions_remaining <= 0:
        return JSONResponse({"ok": False, "error": "No sessions remaining in your plan."})

    if not student.get("mentor_id"):
        return JSONResponse({"ok": False, "error": "No mentor assigned yet."})

    mentor = fetchone(
        "SELECT * FROM mentors WHERE id=%s AND status='approved'", (student["mentor_id"],)
    )
    if not mentor:
        return JSONResponse({"ok": False, "error": "Mentor not found."})

    # Verify the slot is still free
    open_slots = _open_slots(mentor)
    valid_keys = {s["datetime"] for s in open_slots}
    if slot_dt_str not in valid_keys:
        return JSONResponse({"ok": False, "error": "Slot no longer available."})

    rate = mentor.get("rate_per_session", 0)
    execute(
        """INSERT INTO sessions
           (student_id, mentor_id, scheduled_at, status, booked_by, topic, rate_applied)
           VALUES (%s, %s, %s, 'scheduled', 'student', %s, %s)""",
        (student["id"], mentor["id"], slot_dt_str, topic, rate),
    )
    return JSONResponse({"ok": True})


@router.post("/api/request-upgrade")
async def request_upgrade(request: Request):
    """Create a Razorpay order for the price difference to the target plan."""
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)

    data = await request.json()
    new_plan_id = str(data.get("plan_id", "")).lower().strip()
    email = str(data.get("email", user.get("email", ""))).strip().lower()

    student = _get_student(user)
    if not student:
        return JSONResponse({"ok": False, "error": "Profile not found"})

    from app.services.catalog import get_plan
    new_plan = get_plan(new_plan_id)
    if not new_plan:
        return JSONResponse({"ok": False, "error": "Invalid plan."})

    current_plan = get_plan(student.get("plan_id", "")) or {}
    current_paise = current_plan.get("paise", 0)
    new_paise = new_plan["paise"]

    if new_paise <= current_paise:
        return JSONResponse({"ok": False, "error": "Can only upgrade to a higher-value plan."})

    diff_paise = new_paise - current_paise
    if diff_paise <= 0:
        return JSONResponse({"ok": False, "error": "No payment difference."})

    # Create a pending payments record; full Razorpay flow reuses the existing /create-order path
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
           VALUES (%s, %s, %s, 'INR', 'razorpay', %s, 'created')""",
        (email, new_plan_id, round(diff_paise / 100, 2), order["id"]),
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
