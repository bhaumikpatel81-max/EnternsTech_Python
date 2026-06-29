from __future__ import annotations

import os
from unittest.mock import MagicMock, patch

import pytest
from starlette.testclient import TestClient

_CRON_SECRET = os.environ.get("CRON_SECRET", "test-cron-secret")


@pytest.fixture(scope="module")
def client():
    """App client with DB mocked and CRON_SECRET set to a known test value."""
    mock_settings = MagicMock()
    mock_settings.CRON_SECRET = _CRON_SECRET
    mock_settings.REVIEW_RELEASE_DAYS = 7

    with patch("app.routes.cron.settings", mock_settings), \
         patch("app.services.reviews.execute", return_value=5), \
         patch("app.services.reviews.fetchone", return_value=None), \
         patch("app.services.reviews.fetchall", return_value=[]), \
         patch("app.routes.auth.fetchone", return_value=None), \
         patch("app.routes.auth.execute", return_value=1):
        from app.main import app
        yield TestClient(app, raise_server_exceptions=False)


# ── Access control ────────────────────────────────────────────────────────────

def test_no_secret_is_forbidden(client):
    resp = client.get("/internal/cron/release-reviews")
    assert resp.status_code == 403


def test_wrong_secret_is_forbidden(client):
    resp = client.get("/internal/cron/release-reviews?secret=definitely-wrong")
    assert resp.status_code == 403


def test_empty_secret_param_is_forbidden(client):
    resp = client.get("/internal/cron/release-reviews?secret=")
    assert resp.status_code == 403


# ── Happy path ────────────────────────────────────────────────────────────────

def test_correct_secret_returns_ok(client):
    resp = client.get(f"/internal/cron/release-reviews?secret={_CRON_SECRET}")
    assert resp.status_code == 200
    body = resp.json()
    assert body["status"] == "ok"
    assert body["released"] == 5
