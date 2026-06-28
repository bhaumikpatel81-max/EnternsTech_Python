from __future__ import annotations
from datetime import datetime, timedelta, timezone
from app.database import fetchone, execute
from app.services import escrow as escrow_svc
from app.services.meeting import jitsi_url


def _now_utc() -> datetime:
    return datetime.now(timezone.utc).replace(tzinfo=None)


def within_sla(scheduled_at_utc: datetime) -> bool:
    from app.config import settings
    return (scheduled_at_utc - _now_utc()) > timedelta(hours=settings.CANCEL_SLA_HOURS)


def cancel_session(session_id: int, actor_role: str, reason: str) -> dict:
    session = fetchone("SELECT * FROM sessions WHERE id=%s", (session_id,))
    if not session:
        return {"ok": False, "error": "Session not found"}
    if session["status"] in ("completed", "cancelled", "no_show"):
        return {"ok": False, "error": f"Cannot cancel a {session['status']} session"}

    sla = within_sla(session["scheduled_at"]) if session.get("scheduled_at") else True
    execute(
        "UPDATE sessions SET status='cancelled', cancelled_by=%s, cancel_reason=%s WHERE id=%s",
        (actor_role, (reason or "")[:255], session_id),
    )
    execute(
        """INSERT INTO cancellations (session_id, actor_role, kind, reason, within_sla)
           VALUES (%s, %s, 'cancel', %s, %s)""",
        (session_id, actor_role, (reason or "")[:255], 1 if sla else 0),
    )
    # Escrow: mentor/admin cancel within SLA → refund student
    if actor_role in ("mentor", "admin") and sla:
        escrow_svc.refund_for_session(dict(session), f"Cancel by {actor_role}: {reason}")
    # Student cancels within SLA → restore one session credit
    if actor_role == "student" and sla:
        execute(
            "UPDATE students SET sessions_used=GREATEST(0, sessions_used-1) WHERE id=%s",
            (session["student_id"],),
        )
    return {"ok": True, "within_sla": sla}


def mark_no_show(session_id: int, no_show_by: str) -> dict:
    session = fetchone("SELECT * FROM sessions WHERE id=%s", (session_id,))
    if not session:
        return {"ok": False, "error": "Session not found"}
    execute(
        "UPDATE sessions SET status='no_show', no_show_by=%s WHERE id=%s",
        (no_show_by, session_id),
    )
    execute(
        """INSERT INTO cancellations (session_id, actor_role, kind, reason, within_sla)
           VALUES (%s, %s, 'no_show', 'No show reported', 0)""",
        (session_id, no_show_by),
    )
    # Mentor no-show → refund student; student no-show → forfeit
    if no_show_by == "mentor":
        escrow_svc.refund_for_session(dict(session), "Mentor no-show — refund to student")
    return {"ok": True}


def reschedule(session_id: int, new_slot_utc: str, mentee_tz: str) -> dict:
    session = fetchone("SELECT * FROM sessions WHERE id=%s", (session_id,))
    if not session:
        return {"ok": False, "error": "Session not found"}
    if session["status"] in ("completed", "cancelled", "no_show"):
        return {"ok": False, "error": f"Cannot reschedule a {session['status']} session"}

    execute("UPDATE sessions SET status='rescheduled' WHERE id=%s", (session_id,))
    execute(
        """INSERT INTO cancellations (session_id, actor_role, kind, reason, within_sla)
           VALUES (%s, 'student', 'reschedule', 'Rescheduled', 1)""",
        (session_id,),
    )
    new_id = execute(
        """INSERT INTO sessions
           (student_id, mentor_id, scheduled_at, duration_min, status, booked_by,
            topic, rate_applied, bundle_id, mentee_tz)
           VALUES (%s, %s, %s, %s, 'scheduled', 'student', %s, %s, %s, %s)""",
        (
            session["student_id"], session["mentor_id"], new_slot_utc,
            session.get("duration_min") or 60, session.get("topic") or "",
            session.get("rate_applied") or 0, session.get("bundle_id"), mentee_tz,
        ),
    )
    url = jitsi_url(new_id)
    execute("UPDATE sessions SET meeting_link=%s WHERE id=%s", (url, new_id))
    return {"ok": True, "new_session_id": new_id, "meeting_link": url}


def offense_count(role_id: int, role: str) -> int:
    col = "student_id" if role == "student" else "mentor_id"
    row = fetchone(
        f"""SELECT COUNT(*) AS cnt FROM cancellations c
            JOIN sessions s ON c.session_id=s.id
            WHERE s.{col}=%s AND c.within_sla=0
              AND c.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)""",
        (role_id,),
    )
    return int(row["cnt"]) if row else 0
