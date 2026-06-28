import hashlib
import os
import secrets
from datetime import datetime, timedelta, timezone
from fastapi import APIRouter, Request, Form, Response
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.templating import Jinja2Templates
from app.auth import (
    verify_password, create_token, get_current_user, hash_password,
    COOKIE_NAME, decode_token,
)
from app.config import settings
from app.database import fetchone, execute

router = APIRouter()
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))


@router.get("/login", response_class=HTMLResponse)
async def login_page(request: Request):
    user = get_current_user(request)
    if user:
        role = user.get("role", "")
        if role == "admin":   return RedirectResponse("/admin")
        if role == "student": return RedirectResponse("/mentee")
        if role == "mentor":  return RedirectResponse("/mentor")
    return templates.TemplateResponse(request, "login.html", {"error": None})


@router.post("/login")
async def login(
    request: Request,
    response: Response,
    email: str = Form(...),
    password: str = Form(...),
):
    user_row = fetchone(
        "SELECT * FROM users WHERE email=%s LIMIT 1",
        (email.lower().strip(),),
    )

    if not user_row:
        return templates.TemplateResponse(request, "login.html", {"error": "Invalid email or password."})

    if not user_row.get("password_hash"):
        return templates.TemplateResponse(request, "login.html", {
            "error": "Set your password using the link we emailed you."
        })

    if not verify_password(password, user_row["password_hash"]):
        return templates.TemplateResponse(request, "login.html", {"error": "Invalid email or password."})

    role = user_row["role"]
    token = create_token({
        "sub": str(user_row["id"]),
        "role": role,
        "email": user_row["email"],
    })
    dest = {"admin": "/admin", "student": "/mentee", "mentor": "/mentor"}.get(role, "/")
    resp = RedirectResponse(dest, status_code=303)
    resp.set_cookie(
        COOKIE_NAME, token, httponly=True, samesite="lax",
        secure=(settings.APP_ENV.lower() == "production"),
    )
    return resp


@router.get("/logout")
async def logout():
    resp = RedirectResponse("/login")
    resp.delete_cookie(COOKIE_NAME)
    return resp


# ── Token resolution ─────────────────────────────────────────────────────────
# New tokens are 64-char raw hex (stored as sha256 in password_tokens).
# Legacy tokens are JWTs (existing mentor/student activation emails still work).

def _resolve_token(token: str) -> tuple:
    """Return (user_id, None) on success or (None, error_str) on failure."""
    is_hex64 = len(token) == 64 and all(c in "0123456789abcdef" for c in token)

    if is_hex64:
        h = hashlib.sha256(token.encode()).hexdigest()
        row = fetchone(
            """SELECT * FROM password_tokens
               WHERE token_hash=%s AND used=0 AND expires_at > UTC_TIMESTAMP()
               LIMIT 1""",
            (h,),
        )
        if not row:
            return None, "Invalid or expired link. Please request a new one."
        execute("UPDATE password_tokens SET used=1 WHERE id=%s", (row["id"],))
        return int(row["user_id"]), None
    else:
        # Legacy JWT path (mentor/student activation emails)
        payload = decode_token(token)
        if not payload or "user_id" not in payload:
            return None, "Invalid or expired link."
        return int(payload["user_id"]), None


# ── Set-password (initial account setup) ────────────────────────────────────

@router.get("/set-password", response_class=HTMLResponse)
async def set_password_page(request: Request, token: str = ""):
    return templates.TemplateResponse(request, "set_password.html", {
        "token": token, "error": None, "form_action": "/set-password",
    })


@router.post("/set-password")
async def set_password(
    request: Request,
    token: str = Form(...),
    password: str = Form(...),
    confirm: str = Form(...),
):
    if password != confirm:
        return templates.TemplateResponse(request, "set_password.html", {
            "token": token, "error": "Passwords do not match.", "form_action": "/set-password",
        })
    if len(password) < 8:
        return templates.TemplateResponse(request, "set_password.html", {
            "token": token, "error": "Password must be at least 8 characters.", "form_action": "/set-password",
        })

    user_id, err = _resolve_token(token)
    if err:
        return templates.TemplateResponse(request, "set_password.html", {
            "token": token, "error": err, "form_action": "/set-password",
        })

    execute(
        "UPDATE users SET password_hash=%s, status='active' WHERE id=%s",
        (hash_password(password), user_id),
    )
    return RedirectResponse("/login?msg=password_set", status_code=303)


# ── Forgot password ───────────────────────────────────────────────────────────

@router.get("/forgot-password", response_class=HTMLResponse)
async def forgot_password_page(request: Request):
    return templates.TemplateResponse(request, "forgot_password.html", {
        "submitted": False, "error": None,
    })


@router.post("/forgot-password")
async def forgot_password(request: Request, email: str = Form(...)):
    from app import email_service

    email = email.lower().strip()
    user = fetchone("SELECT id FROM users WHERE email=%s LIMIT 1", (email,))
    if user:
        raw_token = secrets.token_hex(32)
        token_hash = hashlib.sha256(raw_token.encode()).hexdigest()
        expires = datetime.now(timezone.utc) + timedelta(
            minutes=settings.RESET_TOKEN_EXPIRY_MINUTES
        )
        execute(
            """INSERT INTO password_tokens (user_id, token_hash, purpose, expires_at)
               VALUES (%s, %s, 'reset', %s)""",
            (user["id"], token_hash, expires.replace(tzinfo=None)),
        )
        reset_url = f"{settings.APP_BASE_URL}/reset?token={raw_token}"
        body = f"""
<h2>Reset Your Password</h2>
<p>We received a request to reset the password for your Enterns Tech account.</p>
<p style="margin:24px 0">
  <a href="{reset_url}"
     style="background:#22D3EE;color:#05080f;padding:12px 28px;border-radius:8px;
            text-decoration:none;font-weight:700;display:inline-block">
     Reset Password &rarr;
  </a>
</p>
<p style="color:#94a3b8;font-size:13px">
  This link expires in {settings.RESET_TOKEN_EXPIRY_MINUTES} minutes.
  If you didn't request this, you can safely ignore this email.
</p>
<p style="color:#94a3b8;font-size:13px">
  If the button doesn't work, copy this link: {reset_url}
</p>
"""
        email_service.send_mail(
            email,
            "Reset your Enterns Tech password",
            body,
            bcc_admin=False,
        )

    # Always show the same neutral message to prevent account enumeration
    return templates.TemplateResponse(request, "forgot_password.html", {
        "submitted": True, "error": None,
    })


# ── Reset password (link from forgot-password email) ─────────────────────────

@router.get("/reset", response_class=HTMLResponse)
async def reset_page(request: Request, token: str = ""):
    return templates.TemplateResponse(request, "set_password.html", {
        "token": token, "error": None, "form_action": "/reset",
    })


@router.post("/reset")
async def reset_password(
    request: Request,
    token: str = Form(...),
    password: str = Form(...),
    confirm: str = Form(...),
):
    if password != confirm:
        return templates.TemplateResponse(request, "set_password.html", {
            "token": token, "error": "Passwords do not match.", "form_action": "/reset",
        })
    if len(password) < 8:
        return templates.TemplateResponse(request, "set_password.html", {
            "token": token, "error": "Password must be at least 8 characters.", "form_action": "/reset",
        })

    user_id, err = _resolve_token(token)
    if err:
        return templates.TemplateResponse(request, "set_password.html", {
            "token": token, "error": err, "form_action": "/reset",
        })

    execute(
        "UPDATE users SET password_hash=%s, status='active' WHERE id=%s",
        (hash_password(password), user_id),
    )
    return RedirectResponse("/login?msg=password_set", status_code=303)
