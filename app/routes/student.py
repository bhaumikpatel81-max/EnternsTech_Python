from fastapi import APIRouter, Request
from fastapi.responses import HTMLResponse, JSONResponse, RedirectResponse
from fastapi.templating import Jinja2Templates
from app.auth import get_current_user
from app.database import fetchone, fetchall, execute
from app import email_service

router = APIRouter(prefix="/student")
templates = Jinja2Templates(directory="templates")


def _get_student(user: dict) -> dict | None:
    return fetchone("SELECT * FROM students WHERE user_id=%s LIMIT 1", (user["sub"],))


@router.get("", response_class=HTMLResponse)
async def dashboard(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "student":
        return RedirectResponse("/login")

    student = _get_student(user)
    if not student:
        return templates.TemplateResponse("student/no_profile.html", {"request": request, "user": user})

    mentor = None
    if student.get("mentor_id"):
        mentor = fetchone("SELECT * FROM mentors WHERE id=%s LIMIT 1", (student["mentor_id"],))

    mentors = fetchall("SELECT * FROM mentors WHERE status='approved' ORDER BY full_name")

    sessions = fetchall(
        """SELECT s.*, m.full_name AS mentor_name
           FROM sessions s LEFT JOIN mentors m ON s.mentor_id=m.id
           WHERE s.student_id=%s ORDER BY s.scheduled_at DESC LIMIT 20""",
        (student["id"],),
    )

    from app.config import settings
    plan_info = settings.PLAN_CATALOG.get(student.get("plan_id", ""), {})

    return templates.TemplateResponse("student/dashboard.html", {
        "request": request,
        "user":     user,
        "student":  student,
        "mentor":   mentor,
        "mentors":  mentors,
        "sessions": sessions,
        "plan_info": plan_info,
        "plan_catalog": settings.PLAN_CATALOG,
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
    data     = await request.json()
    mentor_id = int(data.get("mentor_id", 0))
    mentor   = fetchone("SELECT id FROM mentors WHERE id=%s AND status='approved'", (mentor_id,))
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
    data   = await request.json()
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
