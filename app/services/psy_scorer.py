"""
Psychometric scoring engine — Python port of ENP_Psy_Scorer (PHP).

SECURITY: correct + reverse_scored are read from DB only, never from client.
"""
import json
from app.database import fetchall, fetchone, execute


def band_label(score: float) -> str:
    if score >= 80: return "Strong"
    if score >= 60: return "Solid"
    if score >= 40: return "Mixed"
    return "Watch"


def learning_band(index: float | None) -> str:
    if index is None: return "unknown"
    if index >= 75: return "self-directed"
    if index >= 50: return "capable-with-support"
    return "needs-structured-onboarding"


def _likert_index(total: int, n: int) -> float:
    mn, mx = n, n * 5
    if mx == mn: return 0.0
    return round((total - mn) / (mx - mn) * 100, 2)


def _apply_reverse(raw: int, flag: str) -> int:
    return (6 - raw) if flag.upper() == "Y" else raw


def _score_sec1(ids: list, item_map: dict, resp_map: dict) -> dict:
    cluster_sums, cluster_count = {}, {}
    total, n = 0, 0
    for iid in ids:
        item = item_map.get(iid)
        raw  = resp_map.get(iid)
        if item is None or raw is None:
            continue
        val = _apply_reverse(int(raw), item["reverse_scored"])
        total += val; n += 1
        cl = item["trait_or_cluster"].upper()
        cluster_sums[cl]  = cluster_sums.get(cl, 0) + val
        cluster_count[cl] = cluster_count.get(cl, 0) + 1
    index = _likert_index(total, n) if n > 0 else None
    clusters = {
        cl: _likert_index(cluster_sums[cl], cluster_count[cl])
        for cl in cluster_sums
    }
    return {"index": index, "clusters": clusters}


def _score_sec2(ids: list, resp_map: dict) -> str:
    a, b = 0, 0
    for iid in ids:
        ans = str(resp_map.get(iid, "")).upper()
        if ans == "A": a += 1
        if ans == "B": b += 1
    total = a + b
    if total == 0: return ""
    pct_a = round(a / total * 100)
    if pct_a >= 65: return "Analytical / Data-oriented"
    if pct_a <= 35: return "People-oriented / Collaborative"
    return "Balanced — analytical and people skills"


def _score_likert_index(ids: list, item_map: dict, resp_map: dict) -> float | None:
    total, n = 0, 0
    for iid in ids:
        item = item_map.get(iid)
        raw  = resp_map.get(iid)
        if item is None or raw is None:
            continue
        val = _apply_reverse(int(raw), item["reverse_scored"])
        total += val; n += 1
    return _likert_index(total, n) if n > 0 else None


def _score_sec4(ids: list, item_map: dict, resp_map: dict) -> list:
    ranked = {}
    for iid in ids:
        raw = resp_map.get(iid)
        if raw is None: continue
        rank = int(raw)
        if rank > 0:
            ranked[rank] = item_map.get(iid, {}).get("question_text", iid)
    return [ranked[k] for k in sorted(ranked.keys())][:3]


def _score_sec6(ids: list, item_map: dict, resp_map: dict) -> dict:
    trait_sums, trait_count = {}, {}
    for iid in ids:
        item = item_map.get(iid)
        raw  = resp_map.get(iid)
        if item is None or raw is None:
            continue
        val   = _apply_reverse(int(raw), item["reverse_scored"])
        trait = item["trait_or_cluster"].upper()
        trait_sums[trait]  = trait_sums.get(trait, 0) + val
        trait_count[trait] = trait_count.get(trait, 0) + 1
    result = {}
    for trait in ("C", "E", "ES", "O", "A"):
        if trait not in trait_sums:
            result[trait] = None
            continue
        sm  = trait_sums[trait]
        cnt = trait_count[trait]
        mn  = cnt            # min = cnt × 1
        mx  = cnt * 5        # max = cnt × 5
        result[trait] = round((sm - mn) / (mx - mn) * 100, 2) if mx != mn else 0.0
    return result


def _score_sec7(ids: list, item_map: dict, resp_map: dict) -> dict:
    correct = 0
    for iid in ids:
        item    = item_map.get(iid)
        given   = str(resp_map.get(iid, "")).strip()
        expected = str(item.get("correct", "")).strip() if item else ""
        if item and expected and given.lower() == expected.lower():
            correct += 1
    if correct >= 5:   band = "strong"
    elif correct >= 3: band = "adequate"
    else:              band = "gap"
    return {"score": correct, "band": band}


def _score_sec8(ids: list, item_map: dict, resp_map: dict) -> list:
    return [
        {"question": item_map.get(iid, {}).get("question_text", iid),
         "answer":   str(resp_map.get(iid, ""))}
        for iid in ids
    ]


def _overall_band(result: dict) -> str:
    keys = ["strengths_index", "learning_index", "engagement_index",
            "trait_c", "trait_e", "trait_es", "trait_o", "trait_a"]
    vals = [result[k] for k in keys if result.get(k) is not None and isinstance(result[k], (int, float))]
    if not vals: return "unknown"
    return band_label(sum(vals) / len(vals))


# ── Public entry point ──────────────────────────────────────────────────────────

def score(assessment_id: int, selected_items_json: str) -> dict:
    persisted = json.loads(selected_items_json)
    if not isinstance(persisted, dict):
        return _empty_result()

    all_ids = [iid for ids in persisted.values() for iid in ids]
    if not all_ids:
        return _empty_result()

    # Load items with secure fields (correct, reverse_scored)
    placeholders = ",".join(["%s"] * len(all_ids))
    items = fetchall(
        f"SELECT item_id, section, type, reverse_scored, trait_or_cluster, question_text, correct "
        f"FROM psy_items WHERE item_id IN ({placeholders})",
        tuple(all_ids),
    )
    item_map = {r["item_id"]: r for r in items}

    # Load responses
    responses = fetchall(
        "SELECT item_id, answer_value FROM psy_responses WHERE assessment_id = %s",
        (assessment_id,),
    )
    resp_map = {r["item_id"]: r["answer_value"] for r in responses}

    def ids(sec): return persisted.get(str(sec), persisted.get(sec, []))

    sec1 = _score_sec1(ids(1), item_map, resp_map)
    sec6 = _score_sec6(ids(6), item_map, resp_map)
    sec7 = _score_sec7(ids(7), item_map, resp_map)

    result = {
        "strengths_index":   sec1["index"],
        "strengths_clusters": sec1["clusters"],
        "preference_profile": _score_sec2(ids(2), resp_map),
        "learning_index":    _score_likert_index(ids(3), item_map, resp_map),
        "motivation_top3":   _score_sec4(ids(4), item_map, resp_map),
        "engagement_index":  _score_likert_index(ids(5), item_map, resp_map),
        "trait_c":  sec6.get("C"),
        "trait_e":  sec6.get("E"),
        "trait_es": sec6.get("ES"),
        "trait_o":  sec6.get("O"),
        "trait_a":  sec6.get("A"),
        "reasoning_score": sec7["score"],
        "reasoning_band":  sec7["band"],
        "open_responses":  _score_sec8(ids(8), item_map, resp_map),
    }
    result["overall_band"] = _overall_band(result)
    return result


def persist_scores(assessment_id: int, scores: dict) -> None:
    existing = fetchone("SELECT id FROM psy_scores WHERE assessment_id = %s", (assessment_id,))
    row = (
        assessment_id,
        scores.get("strengths_index"),
        json.dumps(scores.get("strengths_clusters")),
        scores.get("preference_profile", ""),
        scores.get("learning_index"),
        json.dumps(scores.get("motivation_top3")),
        scores.get("engagement_index"),
        scores.get("trait_c"),
        scores.get("trait_e"),
        scores.get("trait_es"),
        scores.get("trait_o"),
        scores.get("trait_a"),
        scores.get("reasoning_score"),
        scores.get("reasoning_band", ""),
        json.dumps(scores.get("open_responses")),
        scores.get("overall_band", ""),
    )
    if existing:
        execute(
            """UPDATE psy_scores SET
               strengths_index=%s, strengths_clusters=%s, preference_profile=%s,
               learning_index=%s, motivation_top3=%s, engagement_index=%s,
               trait_c=%s, trait_e=%s, trait_es=%s, trait_o=%s, trait_a=%s,
               reasoning_score=%s, reasoning_band=%s, open_responses=%s, overall_band=%s
               WHERE assessment_id=%s""",
            row[1:] + (assessment_id,),
        )
    else:
        execute(
            """INSERT INTO psy_scores
               (assessment_id, strengths_index, strengths_clusters, preference_profile,
                learning_index, motivation_top3, engagement_index,
                trait_c, trait_e, trait_es, trait_o, trait_a,
                reasoning_score, reasoning_band, open_responses, overall_band, recommendation)
               VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,'')""",
            row,
        )


def _empty_result() -> dict:
    return {
        "strengths_index": None, "strengths_clusters": None,
        "preference_profile": "", "learning_index": None,
        "motivation_top3": None, "engagement_index": None,
        "trait_c": None, "trait_e": None, "trait_es": None, "trait_o": None, "trait_a": None,
        "reasoning_score": None, "reasoning_band": "",
        "open_responses": None, "overall_band": "unknown",
    }
