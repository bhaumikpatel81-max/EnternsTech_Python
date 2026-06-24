"""
Psychometric resolver — Python port of ENP_Psy_Resolver (PHP).
Builds a per-candidate paper by filtering psy_items, randomly selecting
the required count per section, and persisting item IDs.
"""
import json
import random
from app.database import fetchall, execute

SECTION_META = {
    1: {"count": 12, "type": "likert"},
    2: {"count": 10, "type": "forced_choice"},
    3: {"count":  8, "type": "likert"},
    4: {"count": 10, "type": "rank"},
    5: {"count":  8, "type": "likert"},
    6: {"count": 15, "type": "likert"},   # 3 per Big Five trait
    7: {"count":  6, "type": "mcq"},
    8: {"count":  4, "type": "open"},
}

STRIP_FIELDS = ("correct", "reverse_scored")


class PsyResolver:
    def __init__(self, region: str, edu_level: int, field: str):
        self.region    = region.upper()
        self.edu_level = int(edu_level)
        self.field     = field.upper()

    # ── Public API ──────────────────────────────────────────────────────────────

    def resolve(self, strip_sensitive: bool = False) -> dict:
        """Build paper; returns {section_num: [item_row, ...]}."""
        rows = fetchall("SELECT * FROM psy_items ORDER BY item_id")
        by_section = {}
        for row in rows:
            sec = int(row["section"])
            by_section.setdefault(sec, []).append(row)

        paper = {}
        for sec, meta in SECTION_META.items():
            pool     = by_section.get(sec, [])
            selected = self._select_section(sec, pool, meta["count"])
            if strip_sensitive:
                selected = [self._strip(r) for r in selected]
            paper[sec] = selected
        return paper

    def resolve_and_persist(self, assessment_id: int) -> str:
        paper = self.resolve(strip_sensitive=False)
        persisted = {
            sec: [item["item_id"] for item in items]
            for sec, items in paper.items()
        }
        json_str = json.dumps(persisted)
        execute(
            "UPDATE psy_assessments SET selected_items=%s WHERE id=%s",
            (json_str, assessment_id),
        )
        return json_str

    def rebuild_from_persisted(self, selected_items_json: str, strip_sensitive: bool = True) -> dict:
        persisted = json.loads(selected_items_json)
        if not isinstance(persisted, dict):
            return {}
        all_ids = [iid for ids in persisted.values() for iid in ids]
        if not all_ids:
            return {}
        ph = ",".join(["%s"] * len(all_ids))
        rows = fetchall(f"SELECT * FROM psy_items WHERE item_id IN ({ph})", tuple(all_ids))
        by_id = {r["item_id"]: r for r in rows}

        paper = {}
        for sec, ids in persisted.items():
            items = []
            for iid in ids:
                row = by_id.get(iid)
                if row is None:
                    continue
                if strip_sensitive:
                    row = self._strip(dict(row))
                row = self._shuffle_options(dict(row))
                items.append(row)
            paper[int(sec)] = items
        return paper

    # ── Private helpers ─────────────────────────────────────────────────────────

    def _select_section(self, sec: int, pool: list, required: int) -> list:
        eligible = [r for r in pool if self._is_eligible(r)]

        if sec == 6:
            return self._select_sec6(eligible)
        if sec == 7:
            return self._select_sec7(eligible, required)

        if len(eligible) >= required:
            selected = random.sample(eligible, required)
        else:
            selected = list(eligible)
            all_field = [
                r for r in pool
                if r["field"].upper() == "ALL"
                and r["item_id"] not in {s["item_id"] for s in selected}
            ]
            needed = required - len(selected)
            if needed > 0 and all_field:
                pick = min(needed, len(all_field))
                selected.extend(random.sample(all_field, pick))
            if len(selected) < required:
                print(f"[psy_resolver] Sec{sec}: needed {required}, got {len(selected)} "
                      f"(region={self.region}, edu={self.edu_level}, field={self.field})")

        random.shuffle(selected)
        return selected

    def _select_sec6(self, eligible: list) -> list:
        by_trait: dict[str, list] = {}
        for item in eligible:
            trait = item["trait_or_cluster"].upper()
            by_trait.setdefault(trait, []).append(item)

        selected = []
        for trait in ("C", "E", "ES", "O", "A"):
            pool_t = by_trait.get(trait, [])
            if len(pool_t) >= 3:
                selected.extend(random.sample(pool_t, 3))
            else:
                selected.extend(pool_t)
                if len(pool_t) < 3:
                    print(f"[psy_resolver] Sec6 trait {trait}: needed 3, got {len(pool_t)}")
        random.shuffle(selected)
        return selected

    def _select_sec7(self, eligible: list, required: int) -> list:
        preferred_diffs = (1, 2) if self.edu_level <= 2 else (3, 4)
        other_diffs     = (3, 4) if self.edu_level <= 2 else (1, 2)

        field_items   = [r for r in eligible if r["field"].upper() == self.field]
        generic_items = [r for r in eligible if r["field"].upper() != self.field]

        def diff_filter(items, diffs): return [r for r in items if int(r["difficulty"] or 1) in diffs]

        pref_field  = diff_filter(field_items,   preferred_diffs)
        other_field = diff_filter(field_items,   other_diffs)
        pref_gen    = diff_filter(generic_items, preferred_diffs)
        other_gen   = diff_filter(generic_items, other_diffs)

        selected: list = []
        for bucket in (pref_field, pref_gen, other_field, other_gen):
            needed = required - len(selected)
            if needed <= 0 or not bucket:
                continue
            pick = min(needed, len(bucket))
            selected.extend(random.sample(bucket, pick))

        if len(selected) < required:
            print(f"[psy_resolver] Sec7: needed {required}, got {len(selected)}")

        random.shuffle(selected)
        return selected[:required]

    def _is_eligible(self, item: dict) -> bool:
        region = item["region"].upper()
        if region != "ALL" and region != self.region:
            return False
        edu_min = item["edu_min"]
        edu_max = item["edu_max"]
        if str(edu_min).upper() != "ALL" and self.edu_level < int(edu_min):
            return False
        if str(edu_max).upper() != "ALL" and self.edu_level > int(edu_max):
            return False
        field = item["field"].upper()
        if field != "ALL" and field != self.field:
            return False
        return True

    @staticmethod
    def _strip(item: dict) -> dict:
        item = dict(item)
        for f in STRIP_FIELDS:
            item.pop(f, None)
        return item

    @staticmethod
    def _shuffle_options(item: dict) -> dict:
        if item.get("type") not in ("mcq", "forced_choice"):
            return item
        opts = {k: item[k] for k in ("option_a","option_b","option_c","option_d")
                if item.get(k)}
        if len(opts) <= 1:
            return item
        values = list(opts.values())
        random.shuffle(values)
        for i, key in enumerate(opts.keys()):
            item[key] = values[i]
        return item
