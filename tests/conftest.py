from __future__ import annotations

import os

# Set test environment variables at module level so they are in place
# before any app module imports (app.config caches settings on first import).
os.environ.setdefault("APP_ENV", "testing")
os.environ.setdefault("SECRET_KEY", "test-secret-key-only-for-ci-tests!")
os.environ.setdefault("CRON_SECRET", "test-cron-secret")
