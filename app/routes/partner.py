import os
import uuid
from fastapi import APIRouter, Request, Form, File, UploadFile
from fastapi.responses import HTMLResponse, JSONResponse
from fastapi.templating import Jinja2Templates
from app.database import execute
from app import email_service

router = APIRouter()
templates = Jinja2Templates(directory="templates")

ALLOWED_IMAGE_TYPES = {"image/jpeg", "image/png", "image/webp"}
MAX_PHOTO_MB = 5


@router.get("/partner", response_class=HTMLResponse)
async def partner_page(request: Request):
    return templates.TemplateResponse("partner/apply.html", {"request": request, "submitted": False, "error": None})


@router.post("/partner/apply")
async def partner_apply(
    request: Request,
    full_name:       str = Form(...),
    email:           str = Form(...),
    phone:           str = Form(""),
    linkedin:        str = Form(""),
    tech_stack:      str = Form(""),
    available_slots: str = Form(""),
    photo:           UploadFile = File(None),
):
    if "@" not in email:
        return templates.TemplateResponse("partner/apply.html", {
            "request": request, "submitted": False, "error": "Invalid email address."
        })

    photo_url = ""
    if photo and photo.filename:
        if photo.content_type not in ALLOWED_IMAGE_TYPES:
            return templates.TemplateResponse("partner/apply.html", {
                "request": request, "submitted": False,
                "error": "Photo must be JPEG, PNG, or WebP."
            })
        content = await photo.read()
        if len(content) > MAX_PHOTO_MB * 1024 * 1024:
            return templates.TemplateResponse("partner/apply.html", {
                "request": request, "submitted": False,
                "error": f"Photo must be under {MAX_PHOTO_MB}MB."
            })
        ext = photo.filename.rsplit(".", 1)[-1].lower()
        fname = f"mentor_{uuid.uuid4().hex}.{ext}"
        upload_dir = "public/uploads/mentors"
        os.makedirs(upload_dir, exist_ok=True)
        with open(os.path.join(upload_dir, fname), "wb") as f:
            f.write(content)
        photo_url = f"/static/uploads/mentors/{fname}"

    mentor_id = execute(
        """INSERT INTO mentors
           (full_name, email, phone, linkedin, tech_stack, available_slots, photo_url, status)
           VALUES (%s, %s, %s, %s, %s, %s, %s, 'pending')""",
        (full_name, email, phone, linkedin, tech_stack, available_slots, photo_url),
    )

    email_service.send_mentor_application_received({
        "full_name": full_name, "email": email, "phone": phone,
        "linkedin": linkedin, "tech_stack": tech_stack, "available_slots": available_slots,
    })

    return templates.TemplateResponse("partner/apply.html", {
        "request": request, "submitted": True, "error": None
    })
