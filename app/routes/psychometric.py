"""
Psychometric candidate flow:
  GET  /assessment?t=TOKEN       → multi-step assessment page
  POST /api/psy/validate-token   → validate token, return metadata
  POST /api/psy/save-details     → save candidate info + build paper
  POST /api/psy/autosave         → per-section autosave
  POST /api/psy/submit           → submit + score (rate-limited)
"""
import json
import os
import secrets
from datetime import datetime, timedelta, timezone
from fastapi import APIRouter, Request
from fastapi.responses import HTMLResponse, JSONResponse
from fastapi.templating import Jinja2Templates
from app.database import fetchone, execute, fetchall
from app.services.psy_resolver import PsyResolver
from app.services.psy_scorer import score as psy_score, persist_scores
from app import email_service

router = APIRouter()
BASE_DIR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))

RATE_LIMIT_WINDOW_SECONDS = 3600
RATE_LIMIT_MAX_ATTEMPTS   = 5


# ── Candidate page ──────────────────────────────────────────────────────────────

@router.get("/assessment", response_class=HTMLResponse)
async def assessment_page(request: Request, t: str = ""):
    return templates.TemplateResponse(request, "psy/candidate.html", {
        "token": t,
    })


# ── API endpoints ───────────────────────────────────────────────────────────────

@router.post("/api/psy/validate-token")
async def validate_token(request: Request):
    data  = await request.json()
    token = str(data.get("token", "")).strip()
    if not token:
        return JSONResponse({"ok": False, "error": "No token provided."})

    row = fetchone("SELECT * FROM psy_assessments WHERE token=%s LIMIT 1", (token,))
    if not row:
        return JSONResponse({"ok": False, "error": "Invalid or expired link."})
    if row["status"] == "submitted":
        return JSONResponse({"ok": False, "error": "assessment_already_submitted"})

    expires = row["expires_at"]
    if isinstance(expires, str):
        expires = datetime.fromisoformat(expires)
    if expires.tzinfo is None:
        expires = expires.replace(tzinfo=timezone.utc)
    if datetime.now(timezone.utc) > expires:
        return JSONResponse({"ok": False, "error": "This assessment link has expired."})

    return JSONResponse({
        "ok": True,
        "assessment_id":  row["id"],
        "candidate_name":  row["candidate_name"],
        "candidate_email": row["candidate_email"],
        "candidate_phone": row["candidate_phone"],
        "education_level": row["education_level"],
        "field":           row["field"],
        "region":          row["region"],
    })


@router.post("/api/psy/save-details")
async def save_details(request: Request):
    data  = await request.json()
    token = str(data.get("token", "")).strip()
    row   = _load_valid_assessment(token)
    if not row:
        return JSONResponse({"ok": False, "error": "Invalid or expired link."})

    name    = str(data.get("name", "")).strip()[:200]
    email   = str(data.get("email", "")).strip()[:200]
    phone   = str(data.get("phone", "")).strip()[:30]
    edu     = int(data.get("education_level", 1) or 1)
    field   = str(data.get("field", "IT")).strip().upper()
    region  = str(data.get("region", row["region"])).strip().upper()

    execute(
        """UPDATE psy_assessments
           SET candidate_name=%s, candidate_email=%s, candidate_phone=%s,
               education_level=%s, field=%s, region=%s, status='in_progress'
           WHERE id=%s""",
        (name, email, phone, edu, field, region, row["id"]),
    )

    # Build paper if not already built
    selected_json = row.get("selected_items")
    if not selected_json:
        resolver     = PsyResolver(region, edu, field)
        selected_json = resolver.resolve_and_persist(row["id"])

    resolver = PsyResolver(region, edu, field)
    paper    = resolver.rebuild_from_persisted(selected_json, strip_sensitive=True)

    return JSONResponse({"ok": True, "paper": paper})


@router.post("/api/psy/autosave")
async def autosave(request: Request):
    data    = await request.json()
    token   = str(data.get("token", "")).strip()
    section = int(data.get("section", 0))
    answers = data.get("answers", {})  # {item_id: answer_value}

    row = _load_valid_assessment(token)
    if not row:
        return JSONResponse({"ok": False, "error": "Invalid token."})

    selected_json = row.get("selected_items")
    if not selected_json:
        return JSONResponse({"ok": False, "error": "Paper not built yet."})

    persisted = json.loads(selected_json)
    valid_ids = set(persisted.get(str(section), persisted.get(section, [])))

    for item_id, answer in answers.items():
        if item_id not in valid_ids:
            continue
        existing = fetchone(
            "SELECT id FROM psy_responses WHERE assessment_id=%s AND item_id=%s",
            (row["id"], item_id),
        )
        if existing:
            execute(
                "UPDATE psy_responses SET answer_value=%s WHERE assessment_id=%s AND item_id=%s",
                (str(answer), row["id"], item_id),
            )
        else:
            execute(
                "INSERT INTO psy_responses (assessment_id, item_id, section, answer_value) VALUES (%s,%s,%s,%s)",
                (row["id"], item_id, section, str(answer)),
            )

    return JSONResponse({"ok": True})


@router.post("/api/psy/submit")
async def submit_assessment(request: Request):
    data  = await request.json()
    token = str(data.get("token", "")).strip()
    row   = _load_valid_assessment(token)
    if not row:
        return JSONResponse({"ok": False, "error": "Invalid or expired link."})

    # Rate limiting (DB-based)
    if not _check_rate_limit(token):
        return JSONResponse({"ok": False, "error": "Too many attempts. Please wait before trying again."})

    selected_json = row.get("selected_items")
    if not selected_json:
        return JSONResponse({"ok": False, "error": "Paper not built."})

    execute("UPDATE psy_assessments SET status='submitted' WHERE id=%s", (row["id"],))

    scores = psy_score(row["id"], selected_json)
    persist_scores(row["id"], scores)

    # Reload updated row for email
    updated = fetchone("SELECT * FROM psy_assessments WHERE id=%s", (row["id"],))
    email_service.send_assessment_result_to_admin(updated, scores)

    return JSONResponse({"ok": True})


# ── Helpers ─────────────────────────────────────────────────────────────────────

def _load_valid_assessment(token: str) -> dict | None:
    if not token:
        return None
    row = fetchone("SELECT * FROM psy_assessments WHERE token=%s LIMIT 1", (token,))
    if not row or row["status"] == "submitted":
        return None
    expires = row["expires_at"]
    if isinstance(expires, str):
        expires = datetime.fromisoformat(expires)
    if expires.tzinfo is None:
        expires = expires.replace(tzinfo=timezone.utc)
    if datetime.now(timezone.utc) > expires:
        return None
    return row


def _check_rate_limit(token: str) -> bool:
    """Allow max 5 submit attempts per token per hour."""
    window_start = datetime.now(timezone.utc) - timedelta(seconds=RATE_LIMIT_WINDOW_SECONDS)
    row = fetchone(
        """SELECT COUNT(*) as cnt FROM psy_rate_limits
           WHERE token=%s AND attempted_at > %s""",
        (token, window_start.replace(tzinfo=None)),
    )
    count = row["cnt"] if row else 0
    if count >= RATE_LIMIT_MAX_ATTEMPTS:
        return False
    execute(
        "INSERT INTO psy_rate_limits (token, attempted_at) VALUES (%s, NOW())",
        (token,),
    )
    return True
