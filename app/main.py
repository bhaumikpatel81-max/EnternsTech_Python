import logging
import os
import pymysql
from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse, RedirectResponse, PlainTextResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates

from app.routes import auth, student, mentor, admin, payments, partner, psychometric

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

app = FastAPI(title="Enterns Tech Portal", docs_url=None, redoc_url=None)

from app.config import settings as _settings
logging.getLogger("uvicorn.error").info(
    "DB target -> host=%s db=%s user=%s", _settings.DB_HOST, _settings.DB_NAME, _settings.DB_USER
)


# ── Global error handlers ────────────────────────────────────────────────────

@app.exception_handler(pymysql.err.OperationalError)
async def db_operational_error(request: Request, exc: pymysql.err.OperationalError):
    msg = (
        "Database connection failed.\n\n"
        f"Error: {exc}\n\n"
        "Check your .env file:\n"
        "  DB_HOST  — must be your real Bluehost server hostname (not the placeholder)\n"
        "  DB_NAME  — full name including cPanel prefix, e.g. u12345678_enternstech\n"
        "  DB_USER  — full username with prefix, e.g. u12345678_entuser\n"
        "  DB_PASS  — your database password\n\n"
        "Also ensure Remote MySQL is enabled in Bluehost cPanel → Remote MySQL → add % as host.\n"
        "After editing .env, restart uvicorn."
    )
    return PlainTextResponse(msg, status_code=503)


@app.exception_handler(Exception)
async def generic_error(request: Request, exc: Exception):
    import traceback
    tb = traceback.format_exc()
    msg = f"Unhandled error: {type(exc).__name__}: {exc}\n\n{tb}"
    return PlainTextResponse(msg, status_code=500)

# Static files
if os.path.isdir(os.path.join(BASE_DIR, "public")):
    app.mount("/static", StaticFiles(directory=os.path.join(BASE_DIR, "public")), name="static")

templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))

# Register routers
app.include_router(auth.router)
app.include_router(student.router)
app.include_router(mentor.router)
app.include_router(admin.router)
app.include_router(payments.router)
app.include_router(partner.router)
app.include_router(psychometric.router)


@app.get("/", response_class=HTMLResponse)
async def home(request: Request):
    from app.services.catalog import get_catalog
    cat = get_catalog()
    return templates.TemplateResponse(request, "home.html", {"plans": cat["plans"]})


@app.get("/api/content")
async def api_content():
    """Public read-only endpoint returning catalog data for frontend hydration."""
    from app.services.catalog import get_catalog
    return get_catalog()


@app.get("/health")
async def health():
    return {"status": "ok"}
