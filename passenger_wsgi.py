"""
passenger_wsgi.py — entry point for cPanel Application Manager (Phusion Passenger).

FastAPI is ASGI; Passenger speaks WSGI. We bridge them.

IMPORTANT: a2wsgi's streaming response can be truncated by Passenger's WSGI
implementation (symptom: full page renders internally but browser gets a few
bytes). This version uses a2wsgi but forces the response body to be fully
buffered and returned as a single bytes object, which Passenger serves correctly.
"""
import os
import sys
import glob

APP_DIR = os.path.dirname(os.path.abspath(__file__))

# Make venv packages importable whichever Python Passenger launches.
_cands = glob.glob(os.path.join(APP_DIR, "venv", "lib", "python*", "site-packages"))
_cands.append(os.path.join(APP_DIR, "venv", "lib", "python3.9", "site-packages"))
for _sp in _cands:
    if os.path.isdir(_sp) and _sp not in sys.path:
        sys.path.insert(0, _sp)
if APP_DIR not in sys.path:
    sys.path.insert(0, APP_DIR)

try:
    from a2wsgi import ASGIMiddleware
    from app.main import app as _asgi_app

    _bridge = ASGIMiddleware(_asgi_app)

    def application(environ, start_response):
        # Fully materialise the response body into one bytes object before
        # handing it to Passenger. This avoids the streaming-truncation bug.
        captured = {}

        def _sr(status, headers, exc_info=None):
            captured["status"] = status
            captured["headers"] = headers
            return lambda b: None

        body_parts = []
        for chunk in _bridge(environ, _sr):
            if chunk:
                body_parts.append(chunk)
        body = b"".join(body_parts)

        # Recompute Content-Length to match the buffered body exactly.
        headers = [(k, v) for (k, v) in captured.get("headers", [])
                   if k.lower() != "content-length"]
        headers.append(("Content-Length", str(len(body))))

        start_response(captured.get("status", "200 OK"), headers)
        return [body]

except Exception:
    import traceback
    _tb = traceback.format_exc()

    def application(environ, start_response):
        body = ("passenger_wsgi failed to load the app:\n\n" + _tb).encode("utf-8")
        start_response("500 Internal Server Error",
                       [("Content-Type", "text/plain; charset=utf-8"),
                        ("Content-Length", str(len(body)))])
        return [body]
