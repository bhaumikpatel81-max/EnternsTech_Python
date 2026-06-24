"""
Privacy layer — controls which fields each role can see about the other party.

Requirement: prevent off-platform contact bypass.
  - Students must NOT see mentor phone or personal email.
  - Mentors must NOT see student phone or personal email.
All scheduling stays in-platform.
"""

from __future__ import annotations


def student_view_of_mentor(mentor_row: dict) -> dict:
    """Strip private contact fields before showing a mentor to a student."""
    view = dict(mentor_row)
    # Remove direct-contact fields that would enable off-platform bypass
    view.pop("phone", None)
    view.pop("email", None)
    # LinkedIn is professional-public; keep it
    return view


def mentor_view_of_student(student_row: dict) -> dict:
    """Mask private contact fields before showing a student to a mentor.

    Academic/professional profile (college, tech_stack, cv_url, live_project)
    is fully visible so the mentor can prepare. Direct contact is masked.
    """
    view = dict(student_row)
    # Replace real contact details with an in-platform-only message
    view["phone"] = "Contact via Enterns Tech platform"
    view["email"] = "Contact via Enterns Tech platform"
    return view
