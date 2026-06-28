from __future__ import annotations
from app.database import fetchone, execute


def attach(student_id: int, mentor_id: int, *, admin_override: bool = False) -> dict:
    """Assign student to mentor; increment active_mentees. Returns {ok, error?}."""
    mentor = fetchone("SELECT id, active_mentees, capacity FROM mentors WHERE id=%s", (mentor_id,))
    if not mentor:
        return {"ok": False, "error": "Mentor not found"}
    active = int(mentor.get("active_mentees") or 0)
    cap = int(mentor.get("capacity") or 5)
    if not admin_override and active >= cap:
        return {"ok": False, "error": f"Mentor at capacity ({active}/{cap})"}
    # If student already assigned to a different mentor, decrement the old one
    student = fetchone("SELECT mentor_id FROM students WHERE id=%s", (student_id,))
    old = (student or {}).get("mentor_id")
    if old and old != mentor_id:
        detach(student_id, old)
    elif old == mentor_id:
        return {"ok": True, "note": "Already assigned"}
    execute(
        "UPDATE mentors SET active_mentees=active_mentees+1 WHERE id=%s",
        (mentor_id,),
    )
    return {"ok": True}


def detach(student_id: int, mentor_id: int) -> dict:
    """Decrement active_mentees for mentor (never below 0)."""
    execute(
        "UPDATE mentors SET active_mentees=GREATEST(0, active_mentees-1) WHERE id=%s",
        (mentor_id,),
    )
    return {"ok": True}


def recount(mentor_id: int) -> int:
    """Rebuild active_mentees from a real COUNT — use to self-heal drift."""
    row = fetchone(
        "SELECT COUNT(*) AS cnt FROM students WHERE mentor_id=%s AND status='active'",
        (mentor_id,),
    )
    n = int((row or {}).get("cnt") or 0)
    execute("UPDATE mentors SET active_mentees=%s WHERE id=%s", (n, mentor_id))
    return n
