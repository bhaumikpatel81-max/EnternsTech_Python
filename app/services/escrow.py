from __future__ import annotations

from app.database import (
    execute,
    fetchall,
    fetchone,
    execute_txn,
    fetchone_txn,
    get_transaction,
)


def lock(
    student_id: int,
    mentor_id: int,
    mentor_portion_paise: int,
    sessions_total: int,
    *,
    bundle_id: int | None = None,
    payment_id: int | None = None,
) -> int:
    """Insert escrow + ledger rows atomically. Returns escrow_id."""
    with get_transaction() as conn:
        escrow_id = execute_txn(
            conn,
            """INSERT INTO escrow
               (student_id, mentor_id, bundle_id, payment_id, total_paise,
                released_paise, sessions_total, sessions_released, state)
               VALUES (%s, %s, %s, %s, %s, 0, %s, 0, 'locked')""",
            (student_id, mentor_id, bundle_id, payment_id, mentor_portion_paise, sessions_total),
        )
        execute_txn(
            conn,
            """INSERT INTO escrow_ledger (escrow_id, session_id, direction, amount_paise, note)
               VALUES (%s, NULL, 'lock', %s, %s)""",
            (escrow_id, mentor_portion_paise, f"Lock for {sessions_total} session(s)"),
        )
    return escrow_id


def _find_escrow(session: dict) -> dict | None:
    student_id = session.get("student_id")
    mentor_id  = session.get("mentor_id")
    bundle_id  = session.get("bundle_id")
    exclude    = "('fully_released','refunded','paid_out')"
    if bundle_id:
        return fetchone(
            f"SELECT * FROM escrow WHERE bundle_id=%s AND state NOT IN {exclude} ORDER BY id DESC LIMIT 1",
            (bundle_id,),
        )
    return fetchone(
        f"""SELECT * FROM escrow WHERE student_id=%s AND mentor_id=%s
            AND state NOT IN {exclude} ORDER BY id DESC LIMIT 1""",
        (student_id, mentor_id),
    )


def release_for_session(session_id: int) -> dict:
    """Release one session share. Idempotent; UPDATE + ledger INSERT are atomic."""
    # Fast idempotency check outside transaction
    if fetchone(
        "SELECT id FROM escrow_ledger WHERE session_id=%s AND direction='release'",
        (session_id,),
    ):
        return {"ok": True, "already": True}

    session = fetchone("SELECT * FROM sessions WHERE id=%s", (session_id,))
    if not session:
        return {"ok": False, "error": "Session not found"}

    escrow = _find_escrow(session)
    if not escrow:
        return {"ok": True, "note": "No active escrow for session"}

    escrow_id        = escrow["id"]
    sessions_total   = int(escrow["sessions_total"])
    sessions_released = int(escrow["sessions_released"]) + 1
    released_so_far  = int(escrow["released_paise"])
    total            = int(escrow["total_paise"])

    # Last session gets remainder to prevent rounding loss
    if sessions_released >= sessions_total:
        share     = total - released_so_far
        new_state = "fully_released"
    else:
        share     = total // sessions_total
        new_state = "partially_released"

    with get_transaction() as conn:
        # Re-check idempotency inside the transaction (guards against concurrent releases)
        if fetchone_txn(
            conn,
            "SELECT id FROM escrow_ledger WHERE session_id=%s AND direction='release'",
            (session_id,),
        ):
            return {"ok": True, "already": True}

        execute_txn(
            conn,
            """UPDATE escrow SET released_paise=released_paise+%s,
               sessions_released=%s, state=%s WHERE id=%s""",
            (share, sessions_released, new_state, escrow_id),
        )
        execute_txn(
            conn,
            """INSERT INTO escrow_ledger (escrow_id, session_id, direction, amount_paise, note)
               VALUES (%s, %s, 'release', %s, %s)""",
            (escrow_id, session_id, share, f"Release for session #{session_id}"),
        )

    return {"ok": True, "released_paise": share, "state": new_state}


def refund(escrow_id: int, reason: str) -> dict:
    """Refund full remaining balance. UPDATE + ledger INSERT are atomic."""
    escrow = fetchone("SELECT * FROM escrow WHERE id=%s", (escrow_id,))
    if not escrow:
        return {"ok": False, "error": "Escrow not found"}

    remaining = int(escrow["total_paise"]) - int(escrow["released_paise"])

    with get_transaction() as conn:
        execute_txn(
            conn,
            "UPDATE escrow SET state='refunded', released_paise=total_paise WHERE id=%s",
            (escrow_id,),
        )
        execute_txn(
            conn,
            """INSERT INTO escrow_ledger (escrow_id, session_id, direction, amount_paise, note)
               VALUES (%s, NULL, 'refund', %s, %s)""",
            (escrow_id, remaining, (reason or "Refund")[:255]),
        )

    return {"ok": True, "refunded_paise": remaining}


def refund_for_session(session: dict, reason: str) -> dict:
    """Refund one session's escrow share. UPDATE + ledger INSERT are atomic."""
    escrow = _find_escrow(session)
    if not escrow:
        return {"ok": True, "note": "No escrow found"}

    sessions_total = int(escrow["sessions_total"])
    if sessions_total <= 1:
        return refund(escrow["id"], reason)

    share = int(escrow["total_paise"]) // sessions_total

    with get_transaction() as conn:
        execute_txn(
            conn,
            "UPDATE escrow SET released_paise=released_paise+%s WHERE id=%s",
            (share, escrow["id"]),
        )
        execute_txn(
            conn,
            """INSERT INTO escrow_ledger (escrow_id, session_id, direction, amount_paise, note)
               VALUES (%s, %s, 'refund', %s, %s)""",
            (escrow["id"], session.get("id"), share, (reason or "Partial refund")[:255]),
        )

    return {"ok": True, "refunded_paise": share}


def payout_mentor(mentor_id: int, notes: str = "") -> dict:
    """Record a cash payout for all fully_released, not-yet-paid escrows (G8).

    Transitions matched escrow rows to state='paid_out' and links them to a
    new mentor_payouts record.  Returns the payout summary.
    """
    rows = fetchall(
        """SELECT id, released_paise FROM escrow
           WHERE mentor_id=%s AND state='fully_released' AND payout_id IS NULL""",
        (mentor_id,),
    )
    if not rows:
        return {"ok": False, "error": "No unpaid released escrow for this mentor"}

    total_paise = sum(int(r["released_paise"]) for r in rows)

    with get_transaction() as conn:
        payout_id = execute_txn(
            conn,
            "INSERT INTO mentor_payouts (mentor_id, amount_paise, notes) VALUES (%s, %s, %s)",
            (mentor_id, total_paise, (notes or "")[:500]),
        )
        ids = tuple(r["id"] for r in rows)
        placeholders = ",".join(["%s"] * len(ids))
        execute_txn(
            conn,
            f"UPDATE escrow SET state='paid_out', payout_id=%s WHERE id IN ({placeholders})",
            (payout_id, *ids),
        )

    return {"ok": True, "payout_id": payout_id, "amount_paise": total_paise}


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
    locked = released = paid_out = 0
    for r in rows:
        state = r["state"]
        if state in ("locked", "partially_released"):
            locked   += int(r.get("total") or 0) - int(r.get("released") or 0)
        elif state == "fully_released":
            released  += int(r.get("released") or 0)
        elif state == "paid_out":
            paid_out  += int(r.get("released") or 0)
    return {
        "locked_paise":   locked,
        "released_paise": released,
        "paid_out_paise": paid_out,
    }
