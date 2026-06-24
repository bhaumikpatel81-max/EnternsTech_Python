"""
DB-driven plan catalog service.

All payment amounts are read here — never from the client or from config.py.
Falls back to config.PLAN_CATALOG if the DB tables are empty (logs a warning).
"""

from __future__ import annotations

from app.database import fetchall, fetchone


def get_catalog() -> dict:
    """Return {plans, combos, discounts} shaped for templates and the /api/content endpoint."""
    plans = _fetch_plans()
    combos = _fetch_combos()
    discounts = _fetch_discounts()
    return {"plans": plans, "combos": combos, "discounts": discounts}


def get_plan(plan_id: str) -> dict | None:
    """Return a single plan dict with authoritative paise/cents, or None."""
    plans = _fetch_plans()
    return plans.get(plan_id)


def get_active_auto_discounts(plan_id: str) -> list[dict]:
    """Return auto-apply active discounts that apply to a given plan (or 'all')."""
    rows = fetchall(
        """SELECT * FROM discounts
           WHERE active=1 AND (code IS NULL OR code='')
             AND (applies_to='all' OR applies_to=%s)
             AND (starts_at IS NULL OR starts_at <= CURDATE())
             AND (ends_at   IS NULL OR ends_at   >= CURDATE())
           ORDER BY id""",
        (plan_id,),
    )
    return [dict(r) for r in rows]


def apply_discounts(paise: int, discounts: list[dict]) -> int:
    """Apply a list of discount dicts to a paise amount. Returns new paise amount."""
    for d in discounts:
        if d["kind"] == "percent":
            paise = int(paise * (1 - float(d["value"]) / 100))
        elif d["kind"] == "flat" and d.get("currency", "INR") == "INR":
            paise = max(0, paise - int(float(d["value"]) * 100))
    return paise


# ── Private helpers ──────────────────────────────────────────────────────────

def _fetch_plans() -> dict:
    rows = fetchall(
        "SELECT * FROM plans WHERE active=1 ORDER BY sort_order, id"
    )
    if not rows:
        # Fallback to config so the app works before the DB is seeded
        import logging
        logging.warning("[catalog] plans table is empty — falling back to config.PLAN_CATALOG")
        from app.config import settings
        return dict(settings.PLAN_CATALOG)

    features_rows = fetchall(
        "SELECT * FROM plan_features ORDER BY plan_id, sort_order"
    )
    features_by_plan: dict[str, list[str]] = {}
    for f in features_rows:
        features_by_plan.setdefault(f["plan_id"], []).append(f["feature"])

    result = {}
    for row in rows:
        pid = row["id"]
        result[pid] = {
            **dict(row),
            "features": features_by_plan.get(pid, []),
            # Aliases used by existing templates
            "name": row["name"],
            "price_display": row["price_dom"],
        }
    return result


def _fetch_combos() -> list[dict]:
    rows = fetchall("SELECT * FROM combos WHERE active=1 ORDER BY sort_order, id")
    return [dict(r) for r in rows]


def _fetch_discounts() -> list[dict]:
    rows = fetchall(
        """SELECT * FROM discounts WHERE active=1
             AND (starts_at IS NULL OR starts_at <= CURDATE())
             AND (ends_at   IS NULL OR ends_at   >= CURDATE())
           ORDER BY id"""
    )
    return [dict(r) for r in rows]
