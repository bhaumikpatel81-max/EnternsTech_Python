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
