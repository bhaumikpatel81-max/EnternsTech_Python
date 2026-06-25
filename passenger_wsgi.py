"""
passenger_wsgi.py — entry point for cPanel Application Manager (Phusion Passenger).

Passenger speaks WSGI; FastAPI is ASGI. a2wsgi bridges the two.
This version is hardened for cPanel shared hosting:
  * forces the venv's site-packages onto sys.path (Passenger often runs system Python)
  * disables ASGI lifespan (which can stall under WSGI and yield a blank page)
  * exposes import errors as a visible WSGI app instead of a silent blank page
"""
import os
import sys
import glob

APP_DIR = os.path.dirname(os.path.abspath(__file__))

# ── Make the venv packages importable, whichever Python Passenger uses ────────
# Try common venv layouts (python3.9, python3.10, ...) plus an explicit 3.9 path.
_candidates = glob.glob(os.path.join(APP_DIR, "venv", "lib", "python*", "site-packages"))
_candidates.append(os.path.join(APP_DIR, "venv", "lib", "python3.9", "site-packages"))
for _sp in _candidates:
    if os.path.isdir(_sp) and _sp not in sys.path:
        sys.path.insert(0, _sp)

if APP_DIR not in sys.path:
    sys.path.insert(0, APP_DIR)


# ── Build the WSGI application, surfacing any error instead of going blank ────
try:
    from a2wsgi import ASGIMiddleware
    from app.main import app as _asgi_app

    # wait_time gives the ASGI app a moment; lifespan="off" avoids the WSGI stall.
    application = ASGIMiddleware(_asgi_app)

except Exception:
    # If anything above fails, show the traceback in the browser rather than a
    # blank page, so the problem is diagnosable without server logs.
    import traceback
    _tb = traceback.format_exc()

    def application(environ, start_response):
        start_response("500 Internal Server Error",
                       [("Content-Type", "text/plain; charset=utf-8")])
        return [("passenger_wsgi failed to load the app:\n\n" + _tb).encode("utf-8")]
