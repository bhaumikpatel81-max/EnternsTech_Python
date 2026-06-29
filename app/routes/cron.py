from __future__ import annotations

import hmac

from fastapi import APIRouter, Request
from fastapi.responses import JSONResponse

from app.config import settings
from app.services import reviews as reviews_svc

router = APIRouter(prefix="/internal")

# CSRF middleware already skips /internal/ paths — no CSRF token needed here.


@router.get("/cron/release-reviews")
async def cron_release_reviews(request: Request):
    """Release time-overdue reviews.  Call from a Bluehost cron job:

        curl -s -H "X-Cron-Secret: YOUR_SECRET" \\
             "https://yourdomain.com/internal/cron/release-reviews"

    Set CRON_SECRET in .env.  An empty CRON_SECRET always returns 403.
    Constant-time compare prevents timing-based secret enumeration.
    """
    secret = request.headers.get("x-cron-secret", "")
    if not settings.CRON_SECRET or not hmac.compare_digest(secret, settings.CRON_SECRET):
        return JSONResponse({"error": "Forbidden"}, status_code=403)

    released = reviews_svc.release_due()
    return {"released": released, "status": "ok"}
