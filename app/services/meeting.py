from __future__ import annotations
import secrets


def jitsi_url(session_id: int) -> str:
    """Return a privacy-preserving Jitsi Meet URL for a session."""
    rand = secrets.token_urlsafe(8)
    return f"https://meet.jit.si/enterns-{session_id}-{rand}"
