from __future__ import annotations

import base64
import hashlib
import hmac
import secrets
import time
from datetime import datetime, timedelta, timezone

import httpx
from fastapi import APIRouter, Request
from fastapi.responses import JSONResponse

from app import email_service
from app.auth import hash_password
from app.config import settings
from app.database import execute, fetchone

router = APIRouter(prefix="/api/payments")


def _rzp_configured() -> bool:
    return bool(settings.RAZORPAY_KEY_ID and settings.RAZORPAY_KEY_SECRET)


@router.post("/create-order")
async def create_order(request: Request):
    if not _rzp_configured():
        return JSONResponse({"ok": False, "error": "Payment gateway not configured."})

    data    = await request.json()
    plan_id = str(data.get("plan_id", "")).lower().strip()
    email   = str(data.get("email", "")).strip().lower()

    if "@" not in email:
        return JSONResponse({"ok": False, "error": "Invalid email."})

    from app.services.catalog import get_plan, get_active_auto_discounts, apply_discounts
    plan = get_plan(plan_id)
    if not plan:
        return JSONResponse({"ok": False, "error": "Invalid plan."})

    paise = plan["paise"]
    # Apply any active automatic discounts server-side (never trust client amounts)
    auto_discounts = get_active_auto_discounts(plan_id)
    if auto_discounts:
        paise = apply_discounts(paise, auto_discounts)
    receipt = f"enp_{plan_id}_{int(time.time())}"

    auth = base64.b64encode(
        f"{settings.RAZORPAY_KEY_ID}:{settings.RAZORPAY_KEY_SECRET}".encode()
    ).decode()

    async with httpx.AsyncClient(timeout=20) as client:
        resp = await client.post(
            "https://api.razorpay.com/v1/orders",
            headers={"Authorization": f"Basic {auth}", "Content-Type": "application/json"},
            json={"amount": paise, "currency": "INR", "receipt": receipt, "partial_payment": False},
        )

    if resp.status_code != 200:
        body = resp.json()
        msg  = body.get("error", {}).get("description", f"Razorpay error {resp.status_code}")
        return JSONResponse({"ok": False, "error": msg})

    order = resp.json()

    # Insert payment record — amount stored in paise (G12)
    payment_id = execute(
        """INSERT INTO payments (email, plan_id, amount, currency, gateway, gateway_order_id, status)
           VALUES (%s, %s, %s, 'INR', 'razorpay', %s, 'created')""",
        (email, plan_id, paise, order["id"]),
    )

    return JSONResponse({
        "ok": True,
        "order_id":   order["id"],
        "key_id":     settings.RAZORPAY_KEY_ID,
        "amount":     paise,
        "currency":   "INR",
        "payment_id": payment_id,
    })


@router.post("/verify")
async def verify_payment(request: Request):
    if not _rzp_configured():
        return JSONResponse({"ok": False, "error": "Payment gateway not configured."})

    data = await request.json()
    rzp_order_id   = str(data.get("razorpay_order_id", ""))
    rzp_payment_id = str(data.get("razorpay_payment_id", ""))
    rzp_signature  = str(data.get("razorpay_signature", ""))
    payment_id     = int(data.get("payment_id", 0))
    email          = str(data.get("email", "")).strip().lower()

    if not all([rzp_order_id, rzp_payment_id, rzp_signature, payment_id, "@" in email]):
        return JSONResponse({"ok": False, "error": "Missing payment parameters."})

    # Verify HMAC-SHA256
    expected = hmac.new(
        settings.RAZORPAY_KEY_SECRET.encode(),
        f"{rzp_order_id}|{rzp_payment_id}".encode(),
        hashlib.sha256,
    ).hexdigest()
    if not hmac.compare_digest(expected, rzp_signature):
        return JSONResponse({"ok": False, "error": "Payment signature verification failed."})

    # Load payment record
    pay = fetchone(
        "SELECT id, plan_id, status FROM payments WHERE id=%s AND gateway_order_id=%s LIMIT 1",
        (payment_id, rzp_order_id),
    )
    if not pay:
        return JSONResponse({"ok": False, "error": "Payment record not found."})
    if pay["status"] == "paid":
        return JSONResponse({"ok": True, "redirect": "/mentee"})

    execute(
        "UPDATE payments SET gateway_payment_id=%s WHERE id=%s",
        (rzp_payment_id, pay["id"]),
    )

    result = activate_student(email, pay["plan_id"], int(pay["id"]))
    if not result["ok"]:
        return JSONResponse({"ok": False, "error": result["error"]})

    return JSONResponse({"ok": True, "redirect": "/mentee"})


def activate_student(email: str, plan_id: str, payment_id: int) -> dict:
    """Idempotent: create/update user+student row, mark payment paid, send welcome email."""
    execute("UPDATE payments SET status='paid' WHERE id=%s", (payment_id,))

    from app.services.catalog import get_plan
    plan_info = get_plan(plan_id) or {}
    sessions = max(4, min(8, int(plan_info.get("sessions", settings.PLAN_SESSIONS.get(plan_id, 4)))))
    plan_name = plan_info.get("name") or settings.PLAN_CATALOG.get(plan_id, {}).get("name", plan_id)

    # Find or create user
    user = fetchone("SELECT id, password_hash FROM users WHERE email=%s LIMIT 1", (email,))

    if user:
        uid = user["id"]
        execute("UPDATE users SET role='student' WHERE id=%s", (uid,))
    else:
        # NULL password_hash — user must set password via email link
        uid = execute(
            "INSERT INTO users (email, password_hash, role) VALUES (%s, NULL, 'student')",
            (email,),
        )
        user = {"id": uid, "password_hash": None}

    # Upsert student row
    student = fetchone("SELECT id FROM students WHERE email=%s LIMIT 1", (email,))
    if student:
        execute(
            "UPDATE students SET plan_id=%s, sessions_total=%s, status='active', user_id=%s WHERE id=%s",
            (plan_id, sessions, uid, student["id"]),
        )
        student_id = student["id"]
    else:
        student_id = execute(
            """INSERT INTO students (full_name, email, plan_id, sessions_total, status, user_id)
               VALUES ('', %s, %s, %s, 'active', %s)""",
            (email, plan_id, sessions, uid),
        )

    execute("UPDATE payments SET student_id=%s WHERE id=%s", (student_id, payment_id))

    # Send welcome + set-password link whenever user has no password yet
    if not user.get("password_hash"):
        raw_token = secrets.token_hex(32)
        token_hash = hashlib.sha256(raw_token.encode()).hexdigest()
        expires = datetime.now(timezone.utc) + timedelta(minutes=settings.SET_TOKEN_EXPIRY_MINUTES)
        execute(
            """INSERT INTO password_tokens (user_id, token_hash, purpose, expires_at)
               VALUES (%s, %s, 'set', %s)""",
            (uid, token_hash, expires.replace(tzinfo=None)),
        )
        set_pw_url = f"{settings.APP_BASE_URL}/set-password?token={raw_token}"
        email_service.send_student_welcome(email, plan_name, set_pw_url)

    return {"ok": True, "student_id": student_id}
