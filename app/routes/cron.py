from __future__ import annotations

from fastapi import APIRouter, Query
from fastapi.responses import JSONResponse

from app.config import settings
from app.services import reviews as reviews_svc

router = APIRouter(prefix="/internal")

# CSRF middleware already skips /internal/ paths — no CSRF token needed here.


@router.get("/cron/release-reviews")
async def cron_release_reviews(secret: str = Query("")):
    """Release time-overdue reviews.  Call from a Bluehost cron job:

        curl -s "https://yourdomain.com/internal/cron/release-reviews?secret=YOUR_SECRET"

    Set CRON_SECRET in .env.  An empty CRON_SECRET always returns 403.
    """
    if not settings.CRON_SECRET or secret != settings.CRON_SECRET:
        return JSONResponse({"error": "Forbidden"}, status_code=403)

    released = reviews_svc.release_due()
    return {"released": released, "status": "ok"}
