from __future__ import annotations

from unittest.mock import MagicMock, patch

import pytest
from fastapi import Request

from app.services.rate_limiter import (
    FORGOT_THRESHOLD,
    LOGIN_THRESHOLD,
    clear_on_success,
    get_client_ip,
    is_limited,
    record_attempt,
)


def _req(xff: str = "", real_ip: str = "", host: str = "1.2.3.4") -> Request:
    """Build a minimal mock Request for IP-extraction tests."""
    r = MagicMock(spec=Request)
    headers: dict[str, str] = {}
    if xff:
        headers["X-Forwarded-For"] = xff
    if real_ip:
        headers["X-Real-IP"] = real_ip
    r.headers = headers
    r.client = MagicMock()
    r.client.host = host
    return r


# ── get_client_ip ─────────────────────────────────────────────────────────────

class TestGetClientIp:
    def test_xff_first_entry_used(self):
        assert get_client_ip(_req(xff="10.0.0.1, 192.168.1.1")) == "10.0.0.1"

    def test_xff_single_entry(self):
        assert get_client_ip(_req(xff="10.0.0.1")) == "10.0.0.1"

    def test_xff_strips_whitespace(self):
        assert get_client_ip(_req(xff="  10.0.0.1  , 192.168.1.1")) == "10.0.0.1"

    def test_real_ip_used_when_no_xff(self):
        assert get_client_ip(_req(real_ip="10.0.0.2")) == "10.0.0.2"

    def test_client_host_fallback(self):
        assert get_client_ip(_req()) == "1.2.3.4"

    def test_xff_takes_priority_over_real_ip(self):
        assert get_client_ip(_req(xff="9.9.9.9", real_ip="8.8.8.8")) == "9.9.9.9"


# ── is_limited ────────────────────────────────────────────────────────────────

class TestIsLimited:
    def test_under_login_threshold_not_limited(self):
        with patch("app.services.rate_limiter.fetchone", return_value={"cnt": LOGIN_THRESHOLD - 1}):
            assert is_limited("a@b.com", "1.2.3.4", "login") is False

    def test_at_login_threshold_is_limited(self):
        with patch("app.services.rate_limiter.fetchone", return_value={"cnt": LOGIN_THRESHOLD}):
            assert is_limited("a@b.com", "1.2.3.4", "login") is True

    def test_above_login_threshold_is_limited(self):
        with patch("app.services.rate_limiter.fetchone", return_value={"cnt": LOGIN_THRESHOLD + 3}):
            assert is_limited("a@b.com", "1.2.3.4", "login") is True

    def test_under_forgot_threshold_not_limited(self):
        with patch("app.services.rate_limiter.fetchone", return_value={"cnt": FORGOT_THRESHOLD - 1}):
            assert is_limited("a@b.com", "1.2.3.4", "forgot") is False

    def test_at_forgot_threshold_is_limited(self):
        with patch("app.services.rate_limiter.fetchone", return_value={"cnt": FORGOT_THRESHOLD}):
            assert is_limited("a@b.com", "1.2.3.4", "forgot") is True

    def test_unknown_action_never_limited(self):
        with patch("app.services.rate_limiter.fetchone", return_value={"cnt": 9999}):
            assert is_limited("a@b.com", "1.2.3.4", "nonexistent_action") is False

    def test_null_db_row_not_limited(self):
        with patch("app.services.rate_limiter.fetchone", return_value=None):
            assert is_limited("a@b.com", "1.2.3.4", "login") is False

    def test_zero_count_not_limited(self):
        with patch("app.services.rate_limiter.fetchone", return_value={"cnt": 0}):
            assert is_limited("a@b.com", "1.2.3.4", "login") is False


# ── record_attempt ────────────────────────────────────────────────────────────

class TestRecordAttempt:
    def test_inserts_row_then_prunes(self):
        with patch("app.services.rate_limiter.execute") as mock_ex:
            record_attempt("a@b.com", "1.2.3.4", "login")
        assert mock_ex.call_count == 2
        insert_sql = mock_ex.call_args_list[0][0][0]
        assert "INSERT INTO rate_limits" in insert_sql
        prune_sql = mock_ex.call_args_list[1][0][0]
        assert "DELETE FROM rate_limits" in prune_sql

    def test_correct_params_passed(self):
        with patch("app.services.rate_limiter.execute") as mock_ex:
            record_attempt("user@example.com", "5.5.5.5", "forgot")
        insert_params = mock_ex.call_args_list[0][0][1]
        assert insert_params == ("user@example.com", "5.5.5.5", "forgot")


# ── clear_on_success ──────────────────────────────────────────────────────────

class TestClearOnSuccess:
    def test_deletes_for_triplet(self):
        with patch("app.services.rate_limiter.execute") as mock_ex:
            clear_on_success("a@b.com", "1.2.3.4", "login")
        assert mock_ex.call_count == 1
        sql = mock_ex.call_args[0][0]
        assert "DELETE FROM rate_limits" in sql

    def test_params_contain_all_three_keys(self):
        with patch("app.services.rate_limiter.execute") as mock_ex:
            clear_on_success("x@y.com", "10.0.0.1", "login")
        params = mock_ex.call_args[0][1]
        assert "x@y.com" in params
        assert "10.0.0.1" in params
        assert "login" in params
