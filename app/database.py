from __future__ import annotations

import pymysql
import pymysql.cursors
from contextlib import contextmanager
from typing import Generator

from app.config import settings


def _connect() -> pymysql.Connection:
    return pymysql.connect(
        host=settings.DB_HOST,
        port=settings.DB_PORT,
        user=settings.DB_USER,
        password=settings.DB_PASS,
        database=settings.DB_NAME,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
        connect_timeout=10,
    )


@contextmanager
def get_db() -> Generator[pymysql.Connection, None, None]:
    conn = _connect()
    try:
        yield conn
    finally:
        conn.close()


def fetchone(sql: str, params: tuple = ()) -> dict | None:
    with get_db() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params)
            return cur.fetchone()


def fetchall(sql: str, params: tuple = ()) -> list[dict]:
    with get_db() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params)
            return cur.fetchall()


def execute(sql: str, params: tuple = ()) -> int:
    """Execute INSERT/UPDATE/DELETE; returns lastrowid for INSERT, rowcount otherwise."""
    with get_db() as conn:
        with conn.cursor() as cur:
            cur.execute(sql, params)
            return cur.lastrowid or cur.rowcount


def execute_many(sql: str, params_list: list[tuple]) -> int:
    with get_db() as conn:
        with conn.cursor() as cur:
            cur.executemany(sql, params_list)
            return cur.rowcount


# ── Transaction helpers (G10, G14) ───────────────────────────────────────────
# Use these when multiple statements must succeed or fail together,
# or when SELECT ... FOR UPDATE is needed to prevent concurrent bookings.

@contextmanager
def get_transaction() -> Generator[pymysql.Connection, None, None]:
    """Open a connection with autocommit disabled.

    Commits on normal exit; rolls back and re-raises on any exception.

    Usage::

        with get_transaction() as conn:
            row = fetchone_txn(conn, "SELECT … FOR UPDATE", (…,))
            execute_txn(conn, "INSERT INTO …", (…,))
        # commit happens here; rollback on exception
    """
    conn = _connect()
    try:
        conn.autocommit(False)
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


def fetchone_txn(conn: pymysql.Connection, sql: str, params: tuple = ()) -> dict | None:
    """fetchone on an existing transaction connection."""
    with conn.cursor() as cur:
        cur.execute(sql, params)
        return cur.fetchone()


def execute_txn(conn: pymysql.Connection, sql: str, params: tuple = ()) -> int:
    """execute on an existing transaction connection.
    Returns lastrowid for INSERT, rowcount otherwise.
    """
    with conn.cursor() as cur:
        cur.execute(sql, params)
        return cur.lastrowid or cur.rowcount
