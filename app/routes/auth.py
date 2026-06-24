from fastapi import APIRouter, Request, Form, Response
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.templating import Jinja2Templates
from app.auth import (
    verify_password, create_token, get_current_user, hash_password,
    COOKIE_NAME, decode_token
)
from app.config import settings
from app.database import fetchone

router = APIRouter()
templates = Jinja2Templates(directory="templates")


@router.get("/login", response_class=HTMLResponse)
async def login_page(request: Request):
    user = get_current_user(request)
    if user:
        role = user.get("role", "")
        if role == "admin":   return RedirectResponse("/admin")
        if role == "student": return RedirectResponse("/student")
        if role == "mentor":  return RedirectResponse("/mentor")
    return templates.TemplateResponse("login.html", {"request": request, "error": None})


@router.post("/login")
async def login(
    request: Request,
    response: Response,
    email: str = Form(...),
    password: str = Form(...),
):
    error = None

    # Check admin
    if email.lower() == "admin" or email.lower() == "admin@enternstech.com":
        if password == settings.ADMIN_PASSWORD:
            token = create_token({"sub": "admin", "role": "admin", "email": "admin@enternstech.com"})
            resp  = RedirectResponse("/admin", status_code=303)
            resp.set_cookie(COOKIE_NAME, token, httponly=True, samesite="lax", secure=(settings.APP_ENV == "production"))
            return resp
        else:
            error = "Invalid credentials."

    if not error:
        # Check student / mentor
        user_row = fetchone("SELECT * FROM users WHERE email=%s LIMIT 1", (email,))
        if user_row and verify_password(password, user_row["password_hash"]):
            role  = user_row["role"]
            token = create_token({
                "sub": str(user_row["id"]),
                "role": role,
                "email": user_row["email"],
            })
            dest = "/student" if role == "student" else "/mentor"
            resp = RedirectResponse(dest, status_code=303)
            resp.set_cookie(COOKIE_NAME, token, httponly=True, samesite="lax", secure=(settings.APP_ENV == "production"))
            return resp
        else:
            error = "Invalid email or password."

    return templates.TemplateResponse("login.html", {"request": request, "error": error})


@router.get("/logout")
async def logout():
    resp = RedirectResponse("/login")
    resp.delete_cookie(COOKIE_NAME)
    return resp


@router.get("/set-password", response_class=HTMLResponse)
async def set_password_page(request: Request, token: str = ""):
    return templates.TemplateResponse("set_password.html", {"request": request, "token": token, "error": None})


@router.post("/set-password")
async def set_password(
    request: Request,
    token: str = Form(...),
    password: str = Form(...),
    confirm: str = Form(...),
):
    if password != confirm:
        return templates.TemplateResponse("set_password.html", {
            "request": request, "token": token, "error": "Passwords do not match."
        })
    if len(password) < 8:
        return templates.TemplateResponse("set_password.html", {
            "request": request, "token": token, "error": "Password must be at least 8 characters."
        })

    payload = decode_token(token)
    if not payload or "user_id" not in payload:
        return templates.TemplateResponse("set_password.html", {
            "request": request, "token": token, "error": "Invalid or expired link."
        })

    from app.database import execute as db_exec
    db_exec(
        "UPDATE users SET password_hash=%s WHERE id=%s",
        (hash_password(password), payload["user_id"]),
    )
    resp = RedirectResponse("/login?msg=password_set", status_code=303)
    return resp
