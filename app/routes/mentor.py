from __future__ import annotations
import json
import os
from fastapi import APIRouter, Request
from fastapi.responses import HTMLResponse, JSONResponse, RedirectResponse
from fastapi.templating import Jinja2Templates
from app.auth import get_current_user
from app.database import fetchone, fetchall, execute

router = APIRouter(prefix="/mentor")
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))

VALID_DAYS = {"MO", "TU", "WE", "TH", "FR", "SA", "SU"}


def _get_mentor(user: dict) -> dict | None:
    return fetchone("SELECT * FROM mentors WHERE user_id=%s LIMIT 1", (user["sub"],))


@router.get("", response_class=HTMLResponse)
async def dashboard(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return RedirectResponse("/login")

    mentor = _get_mentor(user)
    if not mentor:
        return templates.TemplateResponse(request, "mentor/no_profile.html", {"user": user})

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
             SUM(status='scheduled') AS planned_count,
             SUM(IF(status='completed', COALESCE(duration_min,60), 0)) AS total_minutes
           FROM sessions WHERE mentor_id=%s""",
        (mentor["id"],),
    ) or {}

    revenue = fetchone(
        "SELECT COALESCE(SUM(rate_applied),0) AS total FROM sessions WHERE mentor_id=%s AND mentor_paid=1",
        (mentor["id"],),
    ) or {"total": 0}

    planned_sessions = fetchall(
        """SELECT s.id, s.scheduled_at, s.topic, s.duration_min,
                  st.full_name AS student_name, st.id AS student_id
           FROM sessions s
           JOIN students st ON s.student_id=st.id
           WHERE s.mentor_id=%s AND s.status='scheduled'
           ORDER BY s.scheduled_at""",
        (mentor["id"],),
    )

    try:
        current_slots = json.loads(mentor.get("slots_json") or "[]")
    except Exception:
        current_slots = []

    return templates.TemplateResponse(request, "mentor/dashboard.html", {
        "user":             user,
        "mentor":           mentor,
        "students":         students,
        "planned_sessions": planned_sessions,
        "done_count":       int(stats.get("done_count") or 0),
        "planned_count":    int(stats.get("planned_count") or 0),
        "total_hours":      round(int(stats.get("total_minutes") or 0) / 60, 1),
        "revenue":          float(revenue["total"] or 0),
        "current_slots":    current_slots,
    })


@router.get("/student/{student_id}", response_class=HTMLResponse)
async def student_detail(request: Request, student_id: int):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return RedirectResponse("/login")

    mentor = _get_mentor(user)
    if not mentor:
        return RedirectResponse("/mentor")

    raw_student = fetchone(
        "SELECT * FROM students WHERE id=%s AND mentor_id=%s LIMIT 1",
        (student_id, mentor["id"]),
    )
    if not raw_student:
        return RedirectResponse("/mentor")

    from app.services.privacy import mentor_view_of_student
    student = mentor_view_of_student(raw_student)

    sessions = fetchall(
        """SELECT * FROM sessions WHERE student_id=%s AND mentor_id=%s
           ORDER BY scheduled_at DESC LIMIT 20""",
        (student_id, mentor["id"]),
    )

    return templates.TemplateResponse(request, "mentor/student_detail.html", {
        "user":     user,
        "mentor":   mentor,
        "student":  student,
        "sessions": sessions,
    })


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

    execute(
        "UPDATE mentors SET slots_json=%s WHERE id=%s",
        (json.dumps(clean), mentor["id"]),
    )
    return JSONResponse({"ok": True, "count": len(clean)})


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

    execute(
        "UPDATE sessions SET status='completed' WHERE id=%s",
        (session_id,),
    )
    # Increment sessions_used on the student
    execute(
        "UPDATE students SET sessions_used = sessions_used + 1 WHERE id=%s",
        (session["student_id"],),
    )
    return JSONResponse({"ok": True})
