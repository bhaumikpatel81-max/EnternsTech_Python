import sys
import os

# Ensure the project root is on the Python path for Vercel serverless.
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from app.main import app  # noqa: E402
