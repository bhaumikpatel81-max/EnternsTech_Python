"""
Real-DB integrity gates — G10 (concurrent booking) and G14 (transaction rollback).

Requires a live MySQL instance matching the credentials in .env.
Creates its own test rows under emails ending in @enp-integrity-test.local and
cleans them up on exit even when tests fail.

Run from the project root:
    python tests/test_real_db_integrity.py
"""
from __future__ import annotations

import os
import sys
import threading
import traceback

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Load .env before importing app modules
try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass  # python-dotenv not installed; rely on environment already being set

os.environ.setdefault("APP_ENV", "testing")
os.environ.setdefault("SECRET_KEY", "test-only-key-not-for-production")
os.environ.setdefault("CRON_SECRET", "test-cron-secret")

import pymysql
from app.database import execute, fetchone, get_transaction, execute_txn, fetchone_txn

_PASS = "\033[32mPASS\033[0m"
_FAIL = "\033[31mFAIL\033[0m"

# Use a far-future datetime so these test slots never collide with real data
_TEST_SLOT_CONCURRENT  = "2099-06-15 09:00"
_TEST_SLOT_ROLLBACK    = "2099-06-15 10:00"
_EMAIL_SUFFIX          = "@enp-integrity-test.local"


# ── Fixtures ─────────────────────────────────────────────────────────────────

def setup() -> tuple[int, int, int]:
    """Insert minimal test rows; return (mentor_id, mentee_a_id, mentee_b_id)."""
    uid_m = execute(
        "INSERT INTO users (email, password_hash, role) VALUES (%s,'x','mentor')",
        (f"mentor{_EMAIL_SUFFIX}",),
    )
    mentor_id = execute(
        """INSERT INTO mentors (user_id, full_name, email, status, rate_per_session)
           VALUES (%s,'Test Mentor',%s,'approved',500)""",
        (uid_m, f"mentor{_EMAIL_SUFFIX}"),
    )
    uid_a = execute(
        "INSERT INTO users (email, password_hash, role) VALUES (%s,'x','student')",
        (f"mentee_a{_EMAIL_SUFFIX}",),
    )
    mentee_a = execute(
        """INSERT INTO students (user_id, email, full_name, plan_id, sessions_total, mentor_id, status)
           VALUES (%s,%s,'Mentee A','starter',10,%s,'active')""",
        (uid_a, f"mentee_a{_EMAIL_SUFFIX}", mentor_id),
    )
    uid_b = execute(
        "INSERT INTO users (email, password_hash, role) VALUES (%s,'x','student')",
        (f"mentee_b{_EMAIL_SUFFIX}",),
    )
    mentee_b = execute(
        """INSERT INTO students (user_id, email, full_name, plan_id, sessions_total, mentor_id, status)
           VALUES (%s,%s,'Mentee B','starter',10,%s,'active')""",
        (uid_b, f"mentee_b{_EMAIL_SUFFIX}", mentor_id),
    )
    return mentor_id, mentee_a, mentee_b


def teardown(mentor_id: int, mentee_a: int, mentee_b: int) -> None:
    execute("DELETE FROM sessions  WHERE mentor_id=%s", (mentor_id,))
    execute("DELETE FROM students  WHERE id IN (%s,%s)", (mentee_a, mentee_b))
    execute("DELETE FROM mentors   WHERE id=%s", (mentor_id,))
    execute(
        "DELETE FROM users WHERE email LIKE %s",
        (f"%{_EMAIL_SUFFIX}",),
    )


# ── Test 1: concurrent booking — exactly one wins ────────────────────────────

def _book_slot(mentor_id: int, mentee_id: int, slot: str,
               barrier: threading.Barrier, results: list, label: str) -> None:
    try:
        with get_transaction() as conn:
            conflict = fetchone_txn(
                conn,
                """SELECT id FROM sessions
                   WHERE mentor_id=%s AND scheduled_at=%s
                     AND status NOT IN ('cancelled','rescheduled','no_show')
                   LIMIT 1 FOR UPDATE""",
                (mentor_id, slot),
            )
            # Both threads synchronise here — neither has inserted yet
            barrier.wait(timeout=10)

            if conflict:
                results.append((label, "conflict-detected-before-insert"))
                return

            execute_txn(
                conn,
                """INSERT INTO sessions
                   (student_id, mentor_id, scheduled_at, status, booked_by,
                    topic, rate_applied)
                   VALUES (%s,%s,%s,'scheduled','student','G10-concurrent-test',0)""",
                (mentee_id, mentor_id, slot),
            )
            results.append((label, "success"))
    except pymysql.err.IntegrityError:
        results.append((label, "integrity-error-caught"))
    except Exception as exc:
        results.append((label, f"unexpected:{exc}"))


def test_concurrent_booking(mentor_id: int, mentee_a: int, mentee_b: int) -> bool:
    print("\n[T1] Concurrent booking — exactly one wins")
    results: list = []
    barrier = threading.Barrier(2)

    t_a = threading.Thread(
        target=_book_slot,
        args=(mentor_id, mentee_a, _TEST_SLOT_CONCURRENT, barrier, results, "A"),
    )
    t_b = threading.Thread(
        target=_book_slot,
        args=(mentor_id, mentee_b, _TEST_SLOT_CONCURRENT, barrier, results, "B"),
    )
    t_a.start()
    t_b.start()
    t_a.join(timeout=15)
    t_b.join(timeout=15)

    print(f"    Thread results: {results}")

    successes      = [r for r in results if r[1] == "success"]
    integrity_errs = [r for r in results if r[1] == "integrity-error-caught"]
    unexpected     = [r for r in results if r[1].startswith("unexpected:")]

    # Verify exactly one session was persisted
    row_count = fetchone(
        "SELECT COUNT(*) AS n FROM sessions WHERE mentor_id=%s AND scheduled_at=%s",
        (mentor_id, _TEST_SLOT_CONCURRENT),
    )
    persisted = int((row_count or {}).get("n", -1))

    ok = (
        len(successes) == 1
        and len(integrity_errs) + len([r for r in results if r[1] == "conflict-detected-before-insert"]) == 1
        and not unexpected
        and persisted == 1
    )
    status = _PASS if ok else _FAIL
    print(f"    Exactly one success: {len(successes)==1}")
    print(f"    Exactly one blocked/rejected: {len(results)-len(successes)==1}")
    print(f"    Rows persisted in DB: {persisted} (expected 1)")
    print(f"    {status}")
    return ok


# ── Test 2: mid-transaction failure → clean rollback, no orphan rows ─────────

def test_transaction_rollback(mentor_id: int, mentee_a: int) -> bool:
    print("\n[T2] Mid-transaction failure → clean rollback")
    inserted_id = None
    try:
        with get_transaction() as conn:
            inserted_id = execute_txn(
                conn,
                """INSERT INTO sessions
                   (student_id, mentor_id, scheduled_at, status, booked_by,
                    topic, rate_applied)
                   VALUES (%s,%s,%s,'scheduled','student','G14-rollback-test',0)""",
                (mentee_a, mentor_id, _TEST_SLOT_ROLLBACK),
            )
            print(f"    Inserted session id={inserted_id} (pre-commit)")
            raise RuntimeError("Simulated mid-transaction failure")
    except RuntimeError:
        pass  # expected — get_transaction rolls back on any exception

    # Row must not exist after the rollback
    row = fetchone("SELECT id FROM sessions WHERE id=%s", (inserted_id,)) if inserted_id else None
    # Also check by slot to be sure
    slot_row = fetchone(
        "SELECT id FROM sessions WHERE mentor_id=%s AND scheduled_at=%s",
        (mentor_id, _TEST_SLOT_ROLLBACK),
    )

    ok = (row is None) and (slot_row is None)
    status = _PASS if ok else _FAIL
    print(f"    Row by id after rollback: {row} (expected None)")
    print(f"    Row by slot after rollback: {slot_row} (expected None)")
    print(f"    {status}")
    return ok


# ── Runner ───────────────────────────────────────────────────────────────────

def main() -> None:
    print("=" * 60)
    print("Real-DB Integrity Gates")
    print("=" * 60)

    mentor_id = mentee_a = mentee_b = None
    try:
        mentor_id, mentee_a, mentee_b = setup()
        print(f"\nFixtures: mentor_id={mentor_id}, mentee_a={mentee_a}, mentee_b={mentee_b}")

        r1 = test_concurrent_booking(mentor_id, mentee_a, mentee_b)
        r2 = test_transaction_rollback(mentor_id, mentee_a)

        print("\n" + "=" * 60)
        print(f"T1 concurrent booking : {_PASS if r1 else _FAIL}")
        print(f"T2 transaction rollback: {_PASS if r2 else _FAIL}")
        print("=" * 60)

        if not (r1 and r2):
            sys.exit(1)

    except Exception:
        print("\nFATAL during test run:")
        traceback.print_exc()
        sys.exit(2)
    finally:
        if mentor_id is not None:
            teardown(mentor_id, mentee_a, mentee_b)
            print("\nTest fixtures cleaned up.")


if __name__ == "__main__":
    main()
