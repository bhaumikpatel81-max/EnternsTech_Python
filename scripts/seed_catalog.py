"""
Seed the plans/plan_features/combos tables from the canonical catalog.

Idempotent — uses INSERT ... ON DUPLICATE KEY UPDATE so it is safe to re-run.

Usage:
    python scripts/seed_catalog.py
"""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.database import execute, fetchone

# ── Plan definitions ─────────────────────────────────────────────────────────
PLANS = [
    {
        "id": "basic",
        "name": "Basic Plan",
        "tagline": "Kickstart your career with expert mentorship",
        "price_intl": "$2,500",
        "price_dom": "₹1,50,000",
        "paise": 15000000,
        "cents": 250000,
        "alt_intl": "",
        "alt_dom": "",
        "note": "",
        "duration": "3 months",
        "sessions": 4,
        "badge": "",
        "featured": 0,
        "active": 1,
        "sort_order": 1,
        "features": [
            "4 live 1:1 mentor sessions",
            "CV review & feedback",
            "LinkedIn profile optimisation",
            "Career roadmap plan",
            "WhatsApp support (business hours)",
        ],
    },
    {
        "id": "elite",
        "name": "Elite Plan",
        "tagline": "Accelerate with deeper guidance and CV redesign",
        "price_intl": "$4,000",
        "price_dom": "₹2,50,000",
        "paise": 25000000,
        "cents": 400000,
        "alt_intl": "",
        "alt_dom": "",
        "note": "",
        "duration": "4 months",
        "sessions": 6,
        "badge": "Popular",
        "featured": 1,
        "active": 1,
        "sort_order": 2,
        "features": [
            "6 live 1:1 mentor sessions",
            "Full CV redesign",
            "LinkedIn profile optimisation",
            "Mock interviews (2)",
            "Job application strategy",
            "Priority WhatsApp support",
        ],
    },
    {
        "id": "premium",
        "name": "Premium Plan",
        "tagline": "Complete transformation — portfolio, interviews, placement",
        "price_intl": "$5,500",
        "price_dom": "₹3,50,000",
        "paise": 35000000,
        "cents": 550000,
        "alt_intl": "$13,000",
        "alt_dom": "₹8,00,000",
        "note": "Includes live-project placement (alt price)",
        "duration": "6 months",
        "sessions": 8,
        "badge": "Best Value",
        "featured": 0,
        "active": 1,
        "sort_order": 3,
        "features": [
            "8 live 1:1 mentor sessions",
            "Full CV & portfolio redesign",
            "Mock interviews (4)",
            "Live project / internship placement",
            "LinkedIn + GitHub optimisation",
            "Job referral network access",
            "Priority support 7 days/week",
        ],
    },
    {
        "id": "accelerator",
        "name": "Career Accelerator Combo",
        "tagline": "Elite + Premium bundled for maximum career impact",
        "price_intl": "$8,500",
        "price_dom": "₹5,50,000",
        "paise": 55000000,
        "cents": 850000,
        "alt_intl": "",
        "alt_dom": "",
        "note": "Combines Elite + Premium features",
        "duration": "9 months",
        "sessions": 8,
        "badge": "Combo",
        "featured": 0,
        "active": 1,
        "sort_order": 4,
        "features": [
            "Everything in Elite & Premium",
            "8 live 1:1 mentor sessions",
            "Full CV & portfolio redesign",
            "Mock interviews (6)",
            "Live project / internship placement",
            "Dedicated placement manager",
            "Priority support 7 days/week",
        ],
    },
    {
        "id": "starter",
        "name": "Career Starter Combo",
        "tagline": "Basic + Elite bundled — ideal first step",
        "price_intl": "$5,800",
        "price_dom": "₹3,75,000",
        "paise": 37500000,
        "cents": 580000,
        "alt_intl": "",
        "alt_dom": "",
        "note": "Combines Basic + Elite features",
        "duration": "5 months",
        "sessions": 6,
        "badge": "Combo",
        "featured": 0,
        "active": 1,
        "sort_order": 5,
        "features": [
            "Everything in Basic & Elite",
            "6 live 1:1 mentor sessions",
            "CV review & full redesign",
            "Mock interviews (3)",
            "Job application strategy",
            "LinkedIn optimisation",
            "WhatsApp priority support",
        ],
    },
]

COMBOS = [
    {
        "id": "accelerator_combo",
        "name": "Career Accelerator Combo",
        "plans": "elite,premium",
        "price_intl": "$8,500",
        "price_dom": "₹5,50,000",
        "paise": 55000000,
        "cents": 850000,
        "note": "Save vs buying separately",
        "description": "The ultimate career transformation bundle combining Elite and Premium plans for comprehensive mentorship, CV redesign, mock interviews, live project placement, and dedicated support.",
        "active": 1,
        "sort_order": 1,
    },
    {
        "id": "starter_combo",
        "name": "Career Starter Combo",
        "plans": "basic,elite",
        "price_intl": "$5,800",
        "price_dom": "₹3,75,000",
        "paise": 37500000,
        "cents": 580000,
        "note": "Best entry-level bundle",
        "description": "Start strong with the combined power of Basic and Elite plans — expert mentorship, CV redesign, mock interviews, and LinkedIn optimisation.",
        "active": 1,
        "sort_order": 2,
    },
]


def seed() -> None:
    print("[seed_catalog] Seeding plans...")
    for p in PLANS:
        features = p.pop("features")
        execute(
            """INSERT INTO plans
               (id, name, tagline, price_intl, price_dom, paise, cents,
                alt_intl, alt_dom, note, duration, sessions,
                badge, featured, active, sort_order)
               VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
               ON DUPLICATE KEY UPDATE
                 name=VALUES(name), tagline=VALUES(tagline),
                 price_intl=VALUES(price_intl), price_dom=VALUES(price_dom),
                 paise=VALUES(paise), cents=VALUES(cents),
                 alt_intl=VALUES(alt_intl), alt_dom=VALUES(alt_dom),
                 note=VALUES(note), duration=VALUES(duration),
                 sessions=VALUES(sessions), badge=VALUES(badge),
                 featured=VALUES(featured), active=VALUES(active),
                 sort_order=VALUES(sort_order)""",
            (
                p["id"], p["name"], p["tagline"], p["price_intl"], p["price_dom"],
                p["paise"], p["cents"], p["alt_intl"], p["alt_dom"], p["note"],
                p["duration"], p["sessions"], p["badge"], p["featured"],
                p["active"], p["sort_order"],
            ),
        )
        # Replace features for this plan
        execute("DELETE FROM plan_features WHERE plan_id=%s", (p["id"],))
        for i, feat in enumerate(features):
            execute(
                "INSERT INTO plan_features (plan_id, feature, sort_order) VALUES (%s,%s,%s)",
                (p["id"], feat, i),
            )
        print(f"  plan: {p['id']} ({len(features)} features)")

    print("[seed_catalog] Seeding combos...")
    for c in COMBOS:
        execute(
            """INSERT INTO combos
               (id, name, plans, price_intl, price_dom, paise, cents,
                note, description, active, sort_order)
               VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
               ON DUPLICATE KEY UPDATE
                 name=VALUES(name), plans=VALUES(plans),
                 price_intl=VALUES(price_intl), price_dom=VALUES(price_dom),
                 paise=VALUES(paise), cents=VALUES(cents),
                 note=VALUES(note), description=VALUES(description),
                 active=VALUES(active), sort_order=VALUES(sort_order)""",
            (
                c["id"], c["name"], c["plans"], c["price_intl"], c["price_dom"],
                c["paise"], c["cents"], c["note"], c["description"],
                c["active"], c["sort_order"],
            ),
        )
        print(f"  combo: {c['id']}")

    print("[seed_catalog] Done.")


if __name__ == "__main__":
    seed()
