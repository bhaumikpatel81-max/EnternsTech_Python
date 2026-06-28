from __future__ import annotations
import sys
from pathlib import Path

import pymysql.err

# Allow running from any working directory
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.database import execute  # noqa: E402 — path must be set first

_SQL_PATH = Path(__file__).resolve().parent.parent / "migrations" / "006_workflow.sql"

# MySQL error codes that mean "already applied" — skip silently
_IGNORABLE = {1050, 1060, 1061}  # table exists, duplicate column, duplicate key name


def _has_sql(chunk: str) -> bool:
    """True if chunk contains at least one non-comment, non-blank SQL token."""
    for line in chunk.splitlines():
        s = line.strip()
        if s and not s.startswith("--"):
            return True
    return False


def main() -> None:
    raw = _SQL_PATH.read_text(encoding="utf-8")
    statements = [s.strip() for s in raw.split(";") if _has_sql(s.strip())]

    print(f"migrate_006: {len(statements)} statement(s) to process\n")
    ok = skipped = 0

    for stmt in statements:
        label = " ".join(stmt.split())[:100]
        try:
            execute(stmt)
            print(f"  OK      {label}")
            ok += 1
        except pymysql.err.OperationalError as exc:
            code = exc.args[0]
            if code in _IGNORABLE:
                print(f"  SKIP    [{code}] {label}")
                skipped += 1
            else:
                print(f"  ERROR   {exc}")
                raise

    print(f"\nmigrate_006: done — {ok} executed, {skipped} skipped (already applied).")


if __name__ == "__main__":
    main()
