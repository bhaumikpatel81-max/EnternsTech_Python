"""
CSRF protection middleware (G3).

Mechanism — double-submit cookie
---------------------------------
1. On every request the middleware ensures an ``enp_csrf`` cookie exists.
   Its value is stored on ``request.state.csrf_token`` so Jinja2 templates
   can inject it into HTML forms as a hidden field.

2. On any POST whose Content-Type is a browser form (urlencoded or multipart),
   the middleware reads ``csrf_token`` from the submitted form data and does a
   constant-time comparison against the ``enp_csrf`` cookie.
   A mismatch → HTTP 403 before the route handler is ever called.

What is NOT checked
-------------------
- Paths starting with ``/api/``   : JSON API calls; Content-Type + SameSite=Lax
                                    on the session cookie already prevent CSRF.
- Paths starting with ``/internal/``: cron endpoints protected by CRON_SECRET.
- Requests with ``application/json`` Content-Type.

Notes
-----
- ``BaseHTTPMiddleware`` caches ``request.form()`` so FastAPI ``Form(...)``
  params in route handlers receive the same object — no double-read.
- The cookie is ``SameSite=Strict`` and optionally ``Secure`` in production.
  JavaScript in templates reads the value from a ``<meta name="csrf-token">``
  tag (populated server-side) rather than from the cookie itself, so the cookie
  can safely be ``HttpOnly=True``.
"""
from __future__ import annotations

import hmac
import secrets

from starlette.middleware.base import BaseHTTPMiddleware
from starlette.requests import Request
from starlette.responses import HTMLResponse

from app.config import settings

CSRF_COOKIE_NAME = "enp_csrf"
CSRF_FIELD_NAME  = "csrf_token"

# Prefixes exempt from form-body CSRF verification
_SKIP_PREFIXES = ("/api/", "/internal/", "/health", "/static/")

_FAIL_HTML = (
    "<!doctype html><html><head><title>403</title></head><body>"
    "<h2>403 — Security check failed</h2>"
    "<p>Your session token may have expired. "
    "<a href='javascript:history.back()'>Go back</a> and try again.</p>"
    "</body></html>"
)


class CSRFMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):  # type: ignore[override]
        # ── Step 1: ensure a CSRF token exists and is available to templates ──
        existing = request.cookies.get(CSRF_COOKIE_NAME, "")
        is_new   = not existing or len(existing) != 64
        token    = existing if not is_new else secrets.token_hex(32)
        request.state.csrf_token = token

        # ── Step 2: verify on state-changing form submissions ─────────────────
        if request.method == "POST":
            path = request.url.path
            ct   = request.headers.get("content-type", "").lower()

            skip = (
                any(path.startswith(p) for p in _SKIP_PREFIXES)
                or "application/json" in ct
            )

            if not skip and (
                "application/x-www-form-urlencoded" in ct
                or "multipart/form-data" in ct
            ):
                try:
                    form_data  = await request.form()
                    form_token = str(form_data.get(CSRF_FIELD_NAME) or "")
                except Exception:
                    form_token = ""

                cookie_token = request.cookies.get(CSRF_COOKIE_NAME, "")

                if (
                    not form_token
                    or not cookie_token
                    or not hmac.compare_digest(cookie_token, form_token)
                ):
                    return HTMLResponse(_FAIL_HTML, status_code=403)

        # ── Step 3: run the actual route ──────────────────────────────────────
        response = await call_next(request)

        # ── Step 4: set cookie on the response if it is new ──────────────────
        if is_new:
            response.set_cookie(
                CSRF_COOKIE_NAME,
                token,
                httponly=True,
                samesite="strict",
                secure=(settings.APP_ENV.lower() == "production"),
                max_age=86400,  # 24 hours; refreshed on next page load
            )

        return response
