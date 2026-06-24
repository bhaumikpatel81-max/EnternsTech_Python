import sys
import os

# Ensure the project root is on the Python path for Vercel
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.main import app
from mangum import Mangum

# Mangum wraps FastAPI as an AWS Lambda / Vercel serverless handler.
# This avoids Vercel's vendored Jinja2/Starlette conflicting with ours.
handler = Mangum(app, lifespan="off")
