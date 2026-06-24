import os
import sys
import glob

# ── Path setup ────────────────────────────────────────────────────────────────
# Passenger may launch with system Python. Insert the venv site-packages so
# all pip-installed dependencies are importable regardless of which interpreter
# Passenger uses. Safe to run even when Passenger uses the venv interpreter.
APP_DIR = os.path.dirname(os.path.abspath(__file__))

_venv_lib = os.path.join(APP_DIR, "venv", "lib")
for _sp in glob.glob(os.path.join(_venv_lib, "python*", "site-packages")):
    if _sp not in sys.path:
        sys.path.insert(0, _sp)

if APP_DIR not in sys.path:
    sys.path.insert(0, APP_DIR)

# ── ASGI → WSGI bridge ────────────────────────────────────────────────────────
# FastAPI is ASGI; Phusion Passenger speaks WSGI.
# a2wsgi.ASGIMiddleware wraps the ASGI app in a synchronous WSGI callable.
from a2wsgi import ASGIMiddleware
from app.main import app as _asgi_app

application = ASGIMiddleware(_asgi_app)
