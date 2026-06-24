import sys
import os

# Vercel prepends its own _vendor directory (bundled fastapi / starlette / jinja2)
# to sys.path BEFORE user code runs.  That vendored Jinja2 has a bug where it
# passes a dict as a cache key → "TypeError: unhashable type: dict".
# Strip every _vendor entry so our requirements.txt versions are used instead.
sys.path = [p for p in sys.path if "_vendor" not in p]

# Add project root so "from app.xxx import ..." resolves correctly.
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.main import app          # noqa: E402
from mangum import Mangum         # noqa: E402

# Mangum converts the Vercel / AWS Lambda event into an ASGI scope and
# passes it directly to our FastAPI app — bypassing Vercel's runtime wrapper.
handler = Mangum(app, lifespan="off")
