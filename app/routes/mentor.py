import os
from fastapi import APIRouter, Request
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.templating import Jinja2Templates
from app.auth import get_current_user
from app.database import fetchone, fetchall

router = APIRouter(prefix="/mentor")
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))


@router.get("", response_class=HTMLResponse)
async def dashboard(request: Request):
    user = get_current_user(request)
    if not user or user.get("role") != "mentor":
        return RedirectResponse("/login")

    mentor = fetchone("SELECT * FROM mentors WHERE user_id=%s LIMIT 1", (user["sub"],))
    if not mentor:
        return templates.TemplateResponse(request, "mentor/no_profile.html", {"user": user})

    students = fetchall(
        """SELECT s.id, s.full_name, s.email, s.plan_id, s.sessions_total, s.sessions_used,
                  s.cv_redesign_status, s.status
           FROM students s WHERE s.mentor_id=%s AND s.status='active'
           ORDER BY s.full_name""",
        (mentor["id"],),
    )

    completed_sessions = fetchone(
        "SELECT COUNT(*) as cnt FROM sessions WHERE mentor_id=%s AND status='completed'",
        (mentor["id"],),
    ) or {"cnt": 0}

    revenue = fetchone(
        "SELECT COALESCE(SUM(rate_applied),0) as total FROM sessions WHERE mentor_id=%s AND mentor_paid=1",
        (mentor["id"],),
    ) or {"total": 0}

    return templates.TemplateResponse(request, "mentor/dashboard.html", {
        "user":     user,
        "mentor":   mentor,
        "students": students,
        "completed_sessions": completed_sessions["cnt"],
        "revenue":  revenue["total"],
    })
