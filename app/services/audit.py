from __future__ import annotations

from app.database import execute


def log(
    admin_user_id: int,
    action: str,
    *,
    target_table: str | None = None,
    target_id: int | None = None,
    notes: str = "",
    ip: str = "",
) -> None:
    execute(
        """INSERT INTO admin_audit_log
           (admin_user_id, action, target_table, target_id, notes, ip)
           VALUES (%s, %s, %s, %s, %s, %s)""",
        (admin_user_id, action, target_table, target_id, (notes or "")[:2000], (ip or "")[:45]),
    )
