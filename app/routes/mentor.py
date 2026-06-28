from __future__ import annotations
import json
import os
from datetime import datetime, timezone
from fastapi import APIRouter, Request
from fastapi.responses import HTMLResponse, JSONResponse, RedirectResponse
from fastapi.templating import Jinja2Templates
from app.auth import get_current_user
from app.database import fetchone, fetchall, execute
from app.services import tz as tz_svc
from app.services import escrow as escrow_svc
from app.services import scheduling as sched_svc
from app.services import reviews as reviews_svc
from app.services import capacity as cap_svc
from app.services.matching import normalize_tags

router = APIRouter(prefix="/mentor")
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))

VALID_DAYS = {"MO", "TU", "WE", "TH", "FR", "SA", "SU"}


def _get_mentor(user: dict) -> dict | None:
    return fetchone("SELECT * FROM mentors WHERE user_id=%s LIMIT 1", (user["sub"],))


# ── Dashboard ────────────────────────────────────────────────────────────────

@router.get("", response_class=HTMLResponse)
async def dashboard(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return RedirectResponse("/login")

    mentor = _get_mentor(user)
    if not mentor:
        return templates.TemplateResponse(request, "mentor/no_profile.html", {"user": user})

    mentor_tz = mentor.get("timezone") or "Asia/Kolkata"

    students = fetchall(
        """SELECT s.id, s.full_name, s.plan_id, s.sessions_total, s.sessions_used,
                  s.cv_redesign_status, s.status, s.tech_stack
           FROM students s WHERE s.mentor_id=%s AND s.status='active'
           ORDER BY s.full_name""",
        (mentor["id"],),
    )

    stats = fetchone(
        """SELECT
             COUNT(*) AS total_sessions,
             SUM(status='completed') AS done_count,
             SUM(status IN ('scheduled','confirmed','active')) AS planned_count,
             SUM(IF(status='completed', COALESCE(duration_min,60), 0)) AS total_minutes
           FROM sessions WHERE mentor_id=%s""",
        (mentor["id"],),
    ) or {}

    revenue = fetchone(
        "SELECT COALESCE(SUM(rate_applied),0) AS total FROM sessions WHERE mentor_id=%s AND mentor_paid=1",
        (mentor["id"],),
    ) or {"total": 0}

    balance = escrow_svc.mentor_balance(mentor["id"])

    planned_raw = fetchall(
        """SELECT s.id, s.scheduled_at, s.topic, s.duration_min, s.status,
                  s.meeting_link,
                  st.full_name AS student_name, st.id AS student_id
           FROM sessions s
           JOIN students st ON s.student_id=st.id
           WHERE s.mentor_id=%s AND s.status IN ('scheduled','confirmed','active')
           ORDER BY s.scheduled_at""",
        (mentor["id"],),
    )
    planned_sessions = []
    for s in planned_raw:
        s = dict(s)
        if s.get("scheduled_at"):
            s["scheduled_local"] = tz_svc.fmt_local(s["scheduled_at"], mentor_tz)
        else:
            s["scheduled_local"] = "—"
        planned_sessions.append(s)

    completed_raw = fetchall(
        """SELECT s.id, s.scheduled_at, s.status, st.full_name AS student_name
           FROM sessions s JOIN students st ON s.student_id=st.id
           WHERE s.mentor_id=%s AND s.status='completed'
           ORDER BY s.scheduled_at DESC LIMIT 20""",
        (mentor["id"],),
    )
    completed_sessions = []
    for s in completed_raw:
        s = dict(s)
        if s.get("scheduled_at"):
            s["scheduled_local"] = tz_svc.fmt_local(s["scheduled_at"], mentor_tz)
        rev = fetchone(
            "SELECT id FROM reviews WHERE session_id=%s AND author_role='mentor'", (s["id"],)
        )
        s["review_submitted"] = bool(rev)
        completed_sessions.append(s)

    try:
        current_slots = json.loads(mentor.get("slots_json") or "[]")
    except Exception:
        current_slots = []

    offense = sched_svc.offense_count(mentor["id"], "mentor")

    return templates.TemplateResponse(request, "mentor/dashboard.html", {
        "user":               user,
        "mentor":             mentor,
        "mentor_tz":          mentor_tz,
        "students":           students,
        "planned_sessions":   planned_sessions,
        "completed_sessions": completed_sessions,
        "done_count":         int(stats.get("done_count") or 0),
        "planned_count":      int(stats.get("planned_count") or 0),
        "total_hours":        round(int(stats.get("total_minutes") or 0) / 60, 1),
        "revenue":            float(revenue["total"] or 0),
        "current_slots":      current_slots,
        "balance":            balance,
        "offense_count":      offense,
        "common_tzs":         tz_svc.COMMON_TZS,
    })


# ── Student detail ────────────────────────────────────────────────────────────

@router.get("/mentee/{mentee_id}", response_class=HTMLResponse)
async def mentee_detail(request: Request, mentee_id: int):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return RedirectResponse("/login")

    mentor = _get_mentor(user)
    if not mentor:
        return RedirectResponse("/mentor")

    raw_student = fetchone(
        "SELECT * FROM students WHERE id=%s AND mentor_id=%s LIMIT 1",
        (mentee_id, mentor["id"]),
    )
    if not raw_student:
        return RedirectResponse("/mentor")

    from app.services.privacy import mentor_view_of_student
    student = mentor_view_of_student(raw_student)

    sessions = fetchall(
        "SELECT * FROM sessions WHERE student_id=%s AND mentor_id=%s ORDER BY scheduled_at DESC LIMIT 20",
        (mentee_id, mentor["id"]),
    )

    return templates.TemplateResponse(request, "mentor/student_detail.html", {
        "user": user, "mentor": mentor, "student": student, "sessions": sessions,
    })


# ── Timezone ─────────────────────────────────────────────────────────────────

@router.post("/api/set-timezone")
async def set_timezone(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    tz_name = str(data.get("timezone", "")).strip()
    if not tz_svc.valid_tz(tz_name):
        return JSONResponse({"ok": False, "error": "Invalid timezone"})
    mentor = _get_mentor(user)
    if not mentor:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    execute("UPDATE mentors SET timezone=%s WHERE id=%s", (tz_name, mentor["id"]))
    return JSONResponse({"ok": True})


# ── Availability slots ────────────────────────────────────────────────────────

@router.post("/api/set-slots")
async def set_slots(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)

    mentor = _get_mentor(user)
    if not mentor:
        return JSONResponse({"ok": False, "error": "Profile not found"})

    data = await request.json()
    raw = data.get("slots", [])
    if not isinstance(raw, list):
        return JSONResponse({"ok": False, "error": "slots must be a list"})

    clean = []
    for s in raw:
        day = str(s.get("day", "")).upper().strip()
        start = str(s.get("start", "")).strip()
        if day not in VALID_DAYS:
            return JSONResponse({"ok": False, "error": f"Invalid day: {day}"})
        parts = start.split(":")
        if len(parts) != 2 or not all(p.isdigit() for p in parts):
            return JSONResponse({"ok": False, "error": f"Invalid start time: {start}"})
        h, m = int(parts[0]), int(parts[1])
        if not (0 <= h <= 23 and 0 <= m <= 59):
            return JSONResponse({"ok": False, "error": f"Invalid time: {start}"})
        clean.append({"day": day, "start": f"{h:02d}:{m:02d}"})

    execute("UPDATE mentors SET slots_json=%s WHERE id=%s", (json.dumps(clean), mentor["id"]))
    return JSONResponse({"ok": True, "count": len(clean)})


# ── Mark session complete ─────────────────────────────────────────────────────

@router.post("/api/session/{session_id}/complete")
async def mark_complete(request: Request, session_id: int):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)

    mentor = _get_mentor(user)
    if not mentor:
        return JSONResponse({"ok": False, "error": "Profile not found"})

    session = fetchone(
        "SELECT * FROM sessions WHERE id=%s AND mentor_id=%s LIMIT 1",
        (session_id, mentor["id"]),
    )
    if not session:
        return JSONResponse({"ok": False, "error": "Session not found"})
    if session["status"] == "completed":
        return JSONResponse({"ok": True, "already": True})

    execute("UPDATE sessions SET status='completed' WHERE id=%s", (session_id,))
    execute(
        "UPDATE students SET sessions_used=sessions_used+1 WHERE id=%s",
        (session["student_id"],),
    )
    escrow_svc.release_for_session(session_id)

    # Check if this completes a bundle
    bundle_id = session.get("bundle_id")
    if bundle_id:
        remaining = fetchone(
            """SELECT COUNT(*) AS cnt FROM sessions
               WHERE bundle_id=%s AND status NOT IN ('completed','cancelled','no_show','rescheduled')
                 AND id != %s""",
            (bundle_id, session_id),
        )
        if not remaining or int((remaining or {}).get("cnt") or 0) == 0:
            # Final session — detach from mentor
            cap_svc.detach(session["student_id"], mentor["id"])
            execute(
                "UPDATE students SET status='inactive' WHERE id=%s AND sessions_used>=sessions_total",
                (session["student_id"],),
            )

    return JSONResponse({"ok": True})


# ── Cancel session ────────────────────────────────────────────────────────────

@router.post("/api/cancel-session")
async def cancel_session(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    session_id = int(data.get("session_id", 0))
    reason = str(data.get("reason", ""))[:255]
    mentor = _get_mentor(user)
    if not mentor:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    session = fetchone("SELECT * FROM sessions WHERE id=%s AND mentor_id=%s", (session_id, mentor["id"]))
    if not session:
        return JSONResponse({"ok": False, "error": "Session not found"})
    return JSONResponse(sched_svc.cancel_session(session_id, "mentor", reason))


# ── No-show ───────────────────────────────────────────────────────────────────

@router.post("/api/no-show")
async def no_show(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    session_id = int(data.get("session_id", 0))
    mentor = _get_mentor(user)
    if not mentor:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    session = fetchone("SELECT * FROM sessions WHERE id=%s AND mentor_id=%s", (session_id, mentor["id"]))
    if not session:
        return JSONResponse({"ok": False, "error": "Session not found"})
    return JSONResponse(sched_svc.mark_no_show(session_id, "mentor"))


# ── Reschedule session ────────────────────────────────────────────────────────

@router.post("/api/reschedule-session")
async def reschedule_session(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    session_id = int(data.get("session_id", 0))
    new_slot = str(data.get("new_slot", "")).strip()
    mentor = _get_mentor(user)
    if not mentor:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    session = fetchone("SELECT * FROM sessions WHERE id=%s AND mentor_id=%s", (session_id, mentor["id"]))
    if not session:
        return JSONResponse({"ok": False, "error": "Session not found"})
    mentee_tz = session.get("mentee_tz") or "Asia/Kolkata"
    return JSONResponse(sched_svc.reschedule(session_id, new_slot, mentee_tz))


# ── Review ────────────────────────────────────────────────────────────────────

@router.post("/api/review")
async def submit_review(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    session_id = int(data.get("session_id", 0))
    rating = int(data.get("rating", 0))
    comment = str(data.get("comment", ""))[:2000]
    mentor = _get_mentor(user)
    if not mentor:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    session = fetchone(
        "SELECT * FROM sessions WHERE id=%s AND mentor_id=%s AND status='completed'",
        (session_id, mentor["id"]),
    )
    if not session:
        return JSONResponse({"ok": False, "error": "Session not found or not completed"})
    return JSONResponse(reviews_svc.submit(session_id, "mentor", rating, comment))


# ── Specializations ───────────────────────────────────────────────────────────

@router.post("/api/set-specializations")
async def set_specializations(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    raw = str(data.get("specializations", ""))[:500]
    normalized = ", ".join(sorted(normalize_tags(raw)))
    mentor = _get_mentor(user)
    if not mentor:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    execute("UPDATE mentors SET specializations=%s WHERE id=%s", (normalized, mentor["id"]))
    return JSONResponse({"ok": True, "specializations": normalized})


# ── Profile update ────────────────────────────────────────────────────────────

@router.post("/api/update-profile")
async def update_profile(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return JSONResponse({"ok": False, "error": "Unauthorized"}, status_code=401)
    data = await request.json()
    mentor = _get_mentor(user)
    if not mentor:
        return JSONResponse({"ok": False, "error": "Profile not found"})
    try:
        extra = json.loads(mentor.get("extra_fields") or "{}")
    except Exception:
        extra = {}
    if "headline" in data:
        extra["headline"] = str(data["headline"])[:150]
    if "bio" in data:
        extra["bio"] = str(data["bio"])[:1000]
    if "display_name" in data:
        extra["display_name"] = str(data["display_name"])[:100]
    execute(
        "UPDATE mentors SET extra_fields=%s WHERE id=%s",
        (json.dumps(extra), mentor["id"]),
    )
    return JSONResponse({"ok": True})
