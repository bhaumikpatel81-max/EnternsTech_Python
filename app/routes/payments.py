import hashlib
import hmac
import time
import httpx
import base64
from fastapi import APIRouter, Request
from fastapi.responses import JSONResponse
from app.config import settings
from app.database import fetchone, execute
from app import email_service
from app.auth import hash_password, create_token

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

    if plan_id not in settings.PLAN_PRICES:
        return JSONResponse({"ok": False, "error": "Invalid plan."})
    if "@" not in email:
        return JSONResponse({"ok": False, "error": "Invalid email."})

    paise = settings.PLAN_PRICES[plan_id]
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

    # Insert payment record
    payment_id = execute(
        """INSERT INTO payments (email, plan_id, amount, currency, gateway, gateway_order_id, status)
           VALUES (%s, %s, %s, 'INR', 'razorpay', %s, 'created')""",
        (email, plan_id, round(paise / 100, 2), order["id"]),
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
        return JSONResponse({"ok": True, "redirect": "/student"})

    execute(
        "UPDATE payments SET gateway_payment_id=%s WHERE id=%s",
        (rzp_payment_id, pay["id"]),
    )

    result = activate_student(email, pay["plan_id"], int(pay["id"]))
    if not result["ok"]:
        return JSONResponse({"ok": False, "error": result["error"]})

    return JSONResponse({"ok": True, "redirect": "/student"})


def activate_student(email: str, plan_id: str, payment_id: int) -> dict:
    """Idempotent: create/update user+student row, mark payment paid, send welcome email."""
    execute("UPDATE payments SET status='paid' WHERE id=%s", (payment_id,))

    sessions = settings.PLAN_SESSIONS.get(plan_id, 4)
    plan_name = settings.PLAN_CATALOG.get(plan_id, {}).get("name", plan_id)

    # Find or create user
    user = fetchone("SELECT * FROM users WHERE email=%s LIMIT 1", (email,))
    is_new = False

    if user:
        uid = user["id"]
        execute("UPDATE users SET role='student' WHERE id=%s", (uid,))
    else:
        is_new = True
        temp_hash = hash_password("ChangeMe123!")  # will be overwritten via set-password link
        uid = execute(
            "INSERT INTO users (email, password_hash, role) VALUES (%s, %s, 'student')",
            (email, temp_hash),
        )

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

    if is_new:
        # Generate set-password link (24-hour expiry)
        token = create_token({"user_id": uid, "purpose": "set_password"}, expires_minutes=1440)
        set_pw_url = f"{settings.APP_BASE_URL}/set-password?token={token}"
        email_service.send_student_welcome(email, plan_name, set_pw_url)

    return {"ok": True, "student_id": student_id}
