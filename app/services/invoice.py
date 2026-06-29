from __future__ import annotations
from app.database import fetchone, fetchall
from app.services.matching import fee_breakdown


def build_invoice(payment_id: int) -> dict | None:
    payment = fetchone("SELECT * FROM payments WHERE id=%s", (payment_id,))
    if not payment:
        return None
    student = {}
    if payment.get("student_id"):
        student = fetchone(
            "SELECT id, full_name, college, plan_id FROM students WHERE id=%s",
            (payment["student_id"],),
        ) or {}
    plan = fetchone("SELECT * FROM plans WHERE id=%s", (payment.get("plan_id"),))
    plan_name = (plan.get("name") if plan else None) or payment.get("plan_id", "")
    amount_paise = int(payment.get("amount") or 0)
    fee = fee_breakdown(amount_paise)
    sessions = []
    if student.get("id"):
        sessions = fetchall(
            "SELECT id, scheduled_at, status, duration_min, topic FROM sessions WHERE student_id=%s ORDER BY scheduled_at",
            (student["id"],),
        )
    return {
        "payment":      dict(payment),
        "student":      student,
        "plan_name":    plan_name,
        "amount_paise": amount_paise,
        "amount_rupees": amount_paise / 100,
        "fee":          fee,
        "sessions":     sessions,
    }
