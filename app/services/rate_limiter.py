"""
DB-backed rate limiter for auth endpoints (G1).

Policy
------
Login    : 5 failed attempts per (email, IP) within a 30-min rolling window.
           Successful login clears the counter for that pair.
Forgot   : 3 requests per (email, IP) per 60-min rolling window.
           Always return the neutral message — no observable lockout.

Key design
----------
- Keyed on BOTH email AND IP so an attacker cannot lock out a legitimate
  user from a different IP address (email-only keying is a DoS vector).
- Rows are cheap — opportunistic cleanup on each INSERT keeps the table small.
- No Redis, no APScheduler, no background threads (Passenger-hostile).
"""
from __future__ import annotations

from fastapi import Request

from app.database import execute, fetchone

# ── Policy constants ──────────────────────────────────────────────────────────

# Login: 5 failures → locked for the remainder of a 30-min sliding window
LOGIN_WINDOW_SEC = 30 * 60
LOGIN_THRESHOLD  = 5

# Forgot-password: 3 requests → silently stop sending emails for 60 min
FORGOT_WINDOW_SEC = 60 * 60
FORGOT_THRESHOLD  = 3

# Cleanup: delete rows older than this to keep the table lean
_CLEANUP_HORIZON_SEC = 2 * 60 * 60  # 2 hours


# ── Public helpers ────────────────────────────────────────────────────────────

def get_client_ip(request: Request) -> str:
    """Extract the real client IP, respecting common proxy headers."""
    xff = request.headers.get("X-Forwarded-For", "")
    if xff:
        return xff.split(",")[0].strip()
    real = request.headers.get("X-Real-IP", "")
    if real:
        return real.strip()
    return (request.client.host if request.client else "") or "unknown"


def is_limited(email: str, ip: str, action: str) -> bool:
    """Return True if this (email, ip, action) triple is currently rate-limited."""
    window, threshold = _policy(action)
    if window is None:
        return False
    row = fetchone(
        """SELECT COUNT(*) AS cnt FROM rate_limits
           WHERE key_email=%s AND key_ip=%s AND action=%s
             AND attempted_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL %s SECOND)""",
        (email, ip, action, window),
    )
    return int((row or {}).get("cnt") or 0) >= threshold


def record_attempt(email: str, ip: str, action: str) -> None:
    """Persist one attempt and opportunistically prune old rows."""
    execute(
        "INSERT INTO rate_limits (key_email, key_ip, action) VALUES (%s, %s, %s)",
        (email, ip, action),
    )
    # Cheap cleanup — runs after every insert; table stays small under normal load
    execute(
        "DELETE FROM rate_limits WHERE attempted_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %s SECOND)",
        (_CLEANUP_HORIZON_SEC,),
    )


def clear_on_success(email: str, ip: str, action: str) -> None:
    """Reset the counter after a successful login so the user isn't penalised."""
    execute(
        "DELETE FROM rate_limits WHERE key_email=%s AND key_ip=%s AND action=%s",
        (email, ip, action),
    )


# ── Internal ──────────────────────────────────────────────────────────────────

def _policy(action: str) -> tuple[int | None, int]:
    """Return (window_seconds, threshold) for the given action."""
    if action == "login":
        return LOGIN_WINDOW_SEC, LOGIN_THRESHOLD
    if action == "forgot":
        return FORGOT_WINDOW_SEC, FORGOT_THRESHOLD
    return None, 0
