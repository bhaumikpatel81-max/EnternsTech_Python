import os
from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse, RedirectResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates

from app.routes import auth, student, mentor, admin, payments, partner, psychometric

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

app = FastAPI(title="Enterns Tech Portal", docs_url=None, redoc_url=None)

# Static files
if os.path.isdir(os.path.join(BASE_DIR, "public")):
    app.mount("/static", StaticFiles(directory=os.path.join(BASE_DIR, "public")), name="static")

templates = Jinja2Templates(directory=os.path.join(BASE_DIR, "templates"))

# Register routers
app.include_router(auth.router)
app.include_router(student.router)
app.include_router(mentor.router)
app.include_router(admin.router)
app.include_router(payments.router)
app.include_router(partner.router)
app.include_router(psychometric.router)


@app.get("/", response_class=HTMLResponse)
async def home(request: Request):
    return templates.TemplateResponse("home.html", {"request": request})


@app.get("/health")
async def health():
    return {"status": "ok"}
