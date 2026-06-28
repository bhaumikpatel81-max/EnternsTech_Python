from __future__ import annotations
from app.database import fetchone, fetchall, execute


def lock(
    student_id: int,
    mentor_id: int,
    mentor_portion_paise: int,
    sessions_total: int,
    *,
    bundle_id: int | None = None,
    payment_id: int | None = None,
) -> int:
    """Insert an escrow row in 'locked' state. Returns escrow_id."""
    escrow_id = execute(
        """INSERT INTO escrow
           (student_id, mentor_id, bundle_id, payment_id, total_paise,
            released_paise, sessions_total, sessions_released, state)
           VALUES (%s, %s, %s, %s, %s, 0, %s, 0, 'locked')""",
        (student_id, mentor_id, bundle_id, payment_id, mentor_portion_paise, sessions_total),
    )
    execute(
        """INSERT INTO escrow_ledger (escrow_id, session_id, direction, amount_paise, note)
           VALUES (%s, NULL, 'lock', %s, %s)""",
        (escrow_id, mentor_portion_paise, f"Lock for {sessions_total} session(s)"),
    )
    return escrow_id


def _find_escrow(session: dict) -> dict | None:
    student_id = session.get("student_id")
    mentor_id = session.get("mentor_id")
    bundle_id = session.get("bundle_id")
    if bundle_id:
        return fetchone(
            """SELECT * FROM escrow WHERE bundle_id=%s
               AND state NOT IN ('fully_released','refunded') ORDER BY id DESC LIMIT 1""",
            (bundle_id,),
        )
    return fetchone(
        """SELECT * FROM escrow WHERE student_id=%s AND mentor_id=%s
           AND state NOT IN ('fully_released','refunded') ORDER BY id DESC LIMIT 1""",
        (student_id, mentor_id),
    )


def release_for_session(session_id: int) -> dict:
    """Release one session share. Idempotent."""
    already = fetchone(
        "SELECT id FROM escrow_ledger WHERE session_id=%s AND direction='release'",
        (session_id,),
    )
    if already:
        return {"ok": True, "already": True}

    session = fetchone("SELECT * FROM sessions WHERE id=%s", (session_id,))
    if not session:
        return {"ok": False, "error": "Session not found"}

    escrow = _find_escrow(session)
    if not escrow:
        return {"ok": True, "note": "No active escrow for session"}

    escrow_id = escrow["id"]
    sessions_total = int(escrow["sessions_total"])
    sessions_released = int(escrow["sessions_released"]) + 1
    released_so_far = int(escrow["released_paise"])
    total = int(escrow["total_paise"])

    # Last session gets remainder to prevent rounding loss
    if sessions_released >= sessions_total:
        share = total - released_so_far
        new_state = "fully_released"
    else:
        share = total // sessions_total
        new_state = "partially_released"

    execute(
        """UPDATE escrow SET released_paise=released_paise+%s,
           sessions_released=%s, state=%s WHERE id=%s""",
        (share, sessions_released, new_state, escrow_id),
    )
    execute(
        """INSERT INTO escrow_ledger (escrow_id, session_id, direction, amount_paise, note)
           VALUES (%s, %s, 'release', %s, %s)""",
        (escrow_id, session_id, share, f"Release for session #{session_id}"),
    )
    return {"ok": True, "released_paise": share, "state": new_state}


def refund(escrow_id: int, reason: str) -> dict:
    """Refund full remaining balance for an escrow."""
    escrow = fetchone("SELECT * FROM escrow WHERE id=%s", (escrow_id,))
    if not escrow:
        return {"ok": False, "error": "Escrow not found"}
    remaining = int(escrow["total_paise"]) - int(escrow["released_paise"])
    execute(
        "UPDATE escrow SET state='refunded', released_paise=total_paise WHERE id=%s",
        (escrow_id,),
    )
    execute(
        """INSERT INTO escrow_ledger (escrow_id, session_id, direction, amount_paise, note)
           VALUES (%s, NULL, 'refund', %s, %s)""",
        (escrow_id, remaining, (reason or "Refund")[:255]),
    )
    return {"ok": True, "refunded_paise": remaining}


def refund_for_session(session: dict, reason: str) -> dict:
    """Refund the escrow share for a single cancelled/no-show session."""
    escrow = _find_escrow(session)
    if not escrow:
        return {"ok": True, "note": "No escrow found"}
    sessions_total = int(escrow["sessions_total"])
    if sessions_total <= 1:
        return refund(escrow["id"], reason)
    # Partial: refund one session's share
    share = int(escrow["total_paise"]) // sessions_total
    new_released = int(escrow["released_paise"]) + share
    execute(
        "UPDATE escrow SET released_paise=%s WHERE id=%s",
        (new_released, escrow["id"]),
    )
    execute(
        """INSERT INTO escrow_ledger (escrow_id, session_id, direction, amount_paise, note)
           VALUES (%s, %s, 'refund', %s, %s)""",
        (escrow["id"], session.get("id"), share, (reason or "Partial refund")[:255]),
    )
    return {"ok": True, "refunded_paise": share}


def summary(escrow_id: int) -> dict:
    row = fetchone("SELECT * FROM escrow WHERE id=%s", (escrow_id,))
    if not row:
        return {}
    row = dict(row)
    row["ledger"] = fetchall(
        "SELECT * FROM escrow_ledger WHERE escrow_id=%s ORDER BY created_at",
        (escrow_id,),
    )
    return row


def mentor_balance(mentor_id: int) -> dict:
    rows = fetchall(
        """SELECT state, SUM(total_paise) AS total, SUM(released_paise) AS released
           FROM escrow WHERE mentor_id=%s GROUP BY state""",
        (mentor_id,),
    )
    locked = released = 0
    for r in rows:
        if r["state"] in ("locked", "partially_released"):
            locked += int(r.get("total") or 0) - int(r.get("released") or 0)
        elif r["state"] == "fully_released":
            released += int(r.get("released") or 0)
    return {"locked_paise": locked, "released_paise": released}
