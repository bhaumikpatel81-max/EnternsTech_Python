from __future__ import annotations
from datetime import datetime, timezone
from app.database import fetchone, fetchall, execute


def _now() -> datetime:
    return datetime.now(timezone.utc).replace(tzinfo=None)


def submit(session_id: int, author_role: str, rating: int, comment: str) -> dict:
    rating = max(1, min(5, int(rating)))
    comment_val = (comment or "")[:2000] or None
    existing = fetchone(
        "SELECT id FROM reviews WHERE session_id=%s AND author_role=%s",
        (session_id, author_role),
    )
    if existing:
        execute(
            "UPDATE reviews SET rating=%s, comment=%s, state='hidden', submitted_at=%s WHERE id=%s",
            (rating, comment_val, _now(), existing["id"]),
        )
    else:
        execute(
            """INSERT INTO reviews (session_id, author_role, rating, comment, state, submitted_at)
               VALUES (%s, %s, %s, %s, 'hidden', %s)""",
            (session_id, author_role, rating, comment_val, _now()),
        )
    maybe_release(session_id)
    _lazy_release_due()
    return {"ok": True}


def maybe_release(session_id: int) -> None:
    """If both parties have submitted, flip both to released."""
    rows = fetchall(
        "SELECT id, author_role FROM reviews WHERE session_id=%s AND state='hidden'",
        (session_id,),
    )
    if {r["author_role"] for r in rows} >= {"student", "mentor"}:
        ids = tuple(r["id"] for r in rows)
        placeholders = ",".join(["%s"] * len(ids))
        execute(
            f"UPDATE reviews SET state='released', released_at=%s WHERE id IN ({placeholders})",
            (_now(), *ids),
        )


def release_due() -> int:
    """Time-based fallback: release reviews whose session is older than REVIEW_RELEASE_DAYS."""
    from app.config import settings
    updated = execute(
        """UPDATE reviews r
           JOIN sessions s ON r.session_id=s.id
           SET r.state='released', r.released_at=%s
           WHERE r.state IN ('pending','hidden')
             AND s.scheduled_at < DATE_SUB(NOW(), INTERVAL %s DAY)""",
        (_now(), settings.REVIEW_RELEASE_DAYS),
    )
    return updated if isinstance(updated, int) else 0


def _lazy_release_due() -> None:
    """Call release_due() at most once per hour, guarded by app_settings."""
    import datetime as _dt
    guard = fetchone(
        "SELECT setting_val FROM app_settings WHERE setting_key='reviews_last_release' LIMIT 1"
    )
    now_str = _now().strftime("%Y-%m-%d %H:%M:%S")
    if guard:
        try:
            last = _dt.datetime.strptime(guard["setting_val"], "%Y-%m-%d %H:%M:%S")
            if (_now() - last).total_seconds() < 3600:
                return
        except Exception:
            pass
        execute(
            "UPDATE app_settings SET setting_val=%s WHERE setting_key='reviews_last_release'",
            (now_str,),
        )
    else:
        execute(
            "INSERT INTO app_settings (setting_key, setting_val) VALUES ('reviews_last_release', %s)",
            (now_str,),
        )
    release_due()


def admin_list(filters: dict | None = None) -> list[dict]:
    return fetchall(
        """SELECT r.*, s.scheduled_at, st.full_name AS student_name, m.full_name AS mentor_name
           FROM reviews r
           JOIN sessions s  ON r.session_id=s.id
           LEFT JOIN students st ON s.student_id=st.id
           LEFT JOIN mentors m   ON s.mentor_id=m.id
           ORDER BY r.submitted_at DESC LIMIT 500"""
    )


def mentor_avg(mentor_id: int) -> float | None:
    row = fetchone(
        """SELECT AVG(r.rating) AS avg_rating
           FROM reviews r JOIN sessions s ON r.session_id=s.id
           WHERE s.mentor_id=%s AND r.author_role='student' AND r.state='released'""",
        (mentor_id,),
    )
    val = (row or {}).get("avg_rating")
    return round(float(val), 2) if val is not None else None
