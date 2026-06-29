from __future__ import annotations

import secrets

import pytest
from fastapi import FastAPI, Request
from fastapi.responses import PlainTextResponse
from starlette.testclient import TestClient

from app.middleware.csrf import CSRF_COOKIE_NAME, CSRF_FIELD_NAME, CSRFMiddleware


def _make_app() -> FastAPI:
    """Minimal FastAPI app for CSRF middleware tests — no DB, no auth."""
    app = FastAPI()
    app.add_middleware(CSRFMiddleware)

    @app.get("/page")
    async def get_page():
        return PlainTextResponse("ok")

    @app.post("/page")
    async def post_page(request: Request):  # noqa: ARG001
        return PlainTextResponse("ok")

    @app.post("/api/data")
    async def api_data():
        return PlainTextResponse("ok")

    return app


# Fresh client per test — avoids cookie-jar leakage between cases
@pytest.fixture
def client():
    return TestClient(_make_app(), raise_server_exceptions=False)


# ── Blocking tests ────────────────────────────────────────────────────────────

class TestCsrfBlocking:
    def test_post_no_cookie_no_field_is_403(self, client):
        resp = client.post("/page", data={"x": "y"})
        assert resp.status_code == 403

    def test_post_field_present_but_no_cookie_is_403(self, client):
        resp = client.post(
            "/page",
            data={"x": "y", CSRF_FIELD_NAME: "some-token"},
        )
        assert resp.status_code == 403

    def test_post_cookie_present_but_no_field_is_403(self, client):
        token = secrets.token_hex(32)
        resp = client.post(
            "/page",
            data={"x": "y"},
            cookies={CSRF_COOKIE_NAME: token},
        )
        assert resp.status_code == 403

    def test_post_mismatched_tokens_is_403(self, client):
        resp = client.post(
            "/page",
            data={"x": "y", CSRF_FIELD_NAME: "token-a"},
            cookies={CSRF_COOKIE_NAME: "token-b"},
        )
        assert resp.status_code == 403

    def test_post_matching_tokens_passes(self, client):
        token = secrets.token_hex(32)
        resp = client.post(
            "/page",
            data={"x": "y", CSRF_FIELD_NAME: token},
            cookies={CSRF_COOKIE_NAME: token},
        )
        assert resp.status_code == 200

    def test_json_post_skips_csrf_check(self, client):
        # application/json — no token needed
        resp = client.post("/api/data", json={"x": "y"})
        assert resp.status_code == 200

    def test_api_prefix_skips_csrf_check(self, client):
        # /api/ is in _SKIP_PREFIXES — form POST without token still passes
        resp = client.post("/api/data", data={"x": "y"})
        assert resp.status_code == 200

    def test_get_is_never_checked(self, client):
        resp = client.get("/page")
        assert resp.status_code == 200


# ── Cookie-setting tests ──────────────────────────────────────────────────────

class TestCsrfCookieSetting:
    def test_cookie_set_on_first_get(self, client):
        resp = client.get("/page")
        assert resp.status_code == 200
        assert CSRF_COOKIE_NAME in resp.cookies
        assert len(resp.cookies[CSRF_COOKIE_NAME]) == 64  # token_hex(32)

    def test_existing_valid_cookie_not_overwritten(self, client):
        token = secrets.token_hex(32)  # exactly 64 hex chars
        resp = client.get("/page", cookies={CSRF_COOKIE_NAME: token})
        assert resp.status_code == 200
        # Middleware does not re-set an existing valid cookie
        assert CSRF_COOKIE_NAME not in resp.cookies

    def test_short_cookie_replaced(self, client):
        resp = client.get("/page", cookies={CSRF_COOKIE_NAME: "too-short"})
        assert resp.status_code == 200
        assert CSRF_COOKIE_NAME in resp.cookies
        assert len(resp.cookies[CSRF_COOKIE_NAME]) == 64
