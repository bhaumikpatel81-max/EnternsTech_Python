from __future__ import annotations
import re
from app.database import fetchall, fetchone
from app.services.privacy import student_view_of_mentor


def normalize_tags(text: str) -> set[str]:
    if not text:
        return set()
    tokens = re.split(r"[,\s]+", text.lower())
    return {t.strip() for t in tokens if t.strip()}


def match_score(student_tags: str, mentor_specializations: str) -> int:
    return len(normalize_tags(student_tags) & normalize_tags(mentor_specializations))


def filtered_mentors(student: dict) -> list[dict]:
    all_mentors = fetchall("SELECT * FROM mentors WHERE status='approved' ORDER BY full_name")
    student_tags = student.get("skill_tags") or student.get("tech_stack") or ""
    scored = []
    for m in all_mentors:
        score = match_score(student_tags, (m.get("specializations") or "") + " " + (m.get("tech_stack") or ""))
        is_full = int(m.get("active_mentees") or 0) >= int(m.get("capacity") or 5)
        view = student_view_of_mentor(m)
        view["match_score"] = score
        view["is_full"] = is_full
        scored.append(view)
    # Available first, then by score desc, then name
    scored.sort(key=lambda m: (1 if m["is_full"] else 0, -m["match_score"], m.get("full_name", "")))
    return scored


def mentor_has_capacity(mentor_id: int) -> bool:
    m = fetchone("SELECT active_mentees, capacity FROM mentors WHERE id=%s", (mentor_id,))
    if not m:
        return False
    return int(m.get("active_mentees") or 0) < int(m.get("capacity") or 5)


def fee_breakdown(rate_paise: int) -> dict:
    """
    mentee_pays = rate × (1 + 7.5 %)
    mentor_gets = rate × (1 − 7.5 %)
    platform_keeps = mentee_pays − mentor_gets
    All paise, int-rounded.
    """
    from app.config import settings
    mentee_pays = int(round(rate_paise * (1 + settings.PLATFORM_FEE_MENTEE_PCT / 100)))
    mentor_gets = int(round(rate_paise * (1 - settings.PLATFORM_FEE_MENTOR_PCT / 100)))
    platform_keeps = mentee_pays - mentor_gets
    return {
        "mentee_pays": mentee_pays,
        "mentor_gets": mentor_gets,
        "platform_keeps": platform_keeps,
    }
