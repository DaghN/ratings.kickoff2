"""One-off: curate non-WC tier-B tournaments for slice 6 register."""

from __future__ import annotations

import json
import re
from collections import Counter, defaultdict
from dataclasses import dataclass, field
from typing import Any

from scripts.amiga.tournament_phases import ScopeType, is_knockout_phase, parse_phase
from scripts.amiga.tournament_structure.materialize_legacy import _connect
from scripts.amiga.tournament_structure.tier_b_non_wc_register import is_world_cup_catalog_name
from scripts.amiga.tournament_structure.verify_legacy import TIER_B, audit_legacy_tier_inventory, _load_games

# Labels that should be knockout but fall through to unknown league today.
_PARSER_FIX_PATTERNS: tuple[re.Pattern[str], ...] = (
    re.compile(r"^Positions\s+\d", re.IGNORECASE),
    re.compile(r"^Playouts\b", re.IGNORECASE),
    re.compile(r"^Play\s+Outs\b", re.IGNORECASE),
)

# Distrust even if parseable — manual review.
_MANUAL_REVIEW_NAME_PATTERNS: tuple[re.Pattern[str], ...] = (
    re.compile(r"Athens\s+LXXXV", re.IGNORECASE),
)

# Known-good phase families for auto bulk (non-exhaustive allow).
_KNOWN_KO_LOWER = frozenset(
    s.lower()
    for s in (
        "quarter finals",
        "semi finals",
        "final",
        "3rd place final",
        "5th place final",
        "7th place final",
        "9th place final",
    )
)


def _phase_family(label: str) -> str:
    label = label.strip()
    if not label:
        return "NULL"
    if is_knockout_phase(label):
        return "knockout"
    scope = parse_phase(label)
    if scope.scope_type == ScopeType.KNOCKOUT:
        return "knockout"
    if re.match(r"^Group\s+[A-Z]", label, re.IGNORECASE):
        return "group"
    if re.search(r"\bGroup\s+[A-Z]", label, re.IGNORECASE):
        return "group"
    if re.match(r"^Round\s+\d+", label, re.IGNORECASE):
        return "round_group"
    if label.lower() in _KNOWN_KO_LOWER:
        return "knockout"
    for pat in _PARSER_FIX_PATTERNS:
        if pat.search(label):
            return "parser_fix"
    if scope.scope_type == ScopeType.LEAGUE and scope.scope_key == label:
        return "unknown_league"
    return "other_league"


@dataclass
class CuratedEvent:
    tournament_id: int
    name: str
    game_count: int
    null_phase_games: int
    phase_families: dict[str, int] = field(default_factory=dict)
    unique_phases: list[str] = field(default_factory=list)
    bucket: str = ""
    reason: str = ""


def curate_event(tid: int, name: str, games: list[dict[str, Any]]) -> CuratedEvent:
    nulls = sum(1 for g in games if not str(g.get("phase") or "").strip())
    phases = [str(g.get("phase") or "").strip() for g in games if str(g.get("phase") or "").strip()]
    families: Counter[str] = Counter()
    for p in phases:
        families[_phase_family(p)] += 1
    unique = sorted(set(phases))

    ev = CuratedEvent(
        tournament_id=tid,
        name=name,
        game_count=len(games),
        null_phase_games=nulls,
        phase_families=dict(families),
        unique_phases=unique,
    )

    for pat in _MANUAL_REVIEW_NAME_PATTERNS:
        if pat.search(name):
            ev.bucket = "manual_review"
            ev.reason = "mixed or distrusted structure (name/rule)"
            return ev

    if nulls > 0:
        ev.bucket = "manual_review"
        ev.reason = f"mixed NULL + labeled ({nulls}/{len(games)} NULL games)"
        return ev

    if families.get("parser_fix", 0) > 0:
        ev.bucket = "parser_fix"
        ev.reason = "phase label needs parser fix (Positions/Playouts/Play Outs)"
        return ev

    if families.get("unknown_league", 0) > 0:
        # Singleton odd labels: flag for pilot first unless clearly harmless
        unknown_labels = [p for p in unique if _phase_family(p) == "unknown_league"]
        if len(unique_labels := unknown_labels) == 1 and len(unique) <= 3:
            ev.bucket = "parser_fix"
            ev.reason = f"odd scope label(s): {unknown_labels!r}"
            return ev
        ev.bucket = "manual_review"
        ev.reason = f"unknown league scope labels: {unknown_labels[:5]}"
        return ev

    if not phases:
        ev.bucket = "manual_review"
        ev.reason = "no phase labels despite tier B"
        return ev

    # Cups / champs with KO + optional group-like labels
    if families.get("knockout", 0) > 0 or families.get("group", 0) > 0 or families.get("round_group", 0) > 0:
        ev.bucket = "auto_ok"
        ev.reason = "uniform labeled phases; known KO/group patterns"
        return ev

    if families.get("other_league", 0) > 0 and len(families) == 1:
        ev.bucket = "parser_fix"
        ev.reason = "only non-standard league scopes"
        return ev

    ev.bucket = "auto_ok"
    ev.reason = "uniform labeled phases"
    return ev


def main() -> None:
    conn = _connect()
    try:
        report = audit_legacy_tier_inventory(conn)
        tier_b = report["tiers"][TIER_B]
        curated: list[CuratedEvent] = []
        deferred_wc: list[dict[str, Any]] = []

        for row in tier_b:
            tid = int(row["tournament_id"])
            name = str(row["name"])
            if is_world_cup_catalog_name(name):
                deferred_wc.append({"tournament_id": tid, "name": name})
                continue
            games = _load_games(conn, tid)
            curated.append(curate_event(tid, name, games))

        by_bucket: dict[str, list[CuratedEvent]] = defaultdict(list)
        for ev in curated:
            by_bucket[ev.bucket].append(ev)

        print("Non-WC tier B curation")
        print("======================")
        print(f"Tier B total: {len(tier_b)}")
        print(f"Deferred WC: {len(deferred_wc)}")
        print(f"Non-WC curated: {len(curated)}")
        for bucket in ("auto_ok", "parser_fix", "manual_review"):
            print(f"  {bucket}: {len(by_bucket[bucket])}")

        print("\n--- manual_review ---")
        for ev in sorted(by_bucket["manual_review"], key=lambda e: e.tournament_id):
            print(f"  id={ev.tournament_id} {ev.name!r} — {ev.reason}")

        print("\n--- parser_fix ---")
        for ev in sorted(by_bucket["parser_fix"], key=lambda e: e.tournament_id):
            print(f"  id={ev.tournament_id} {ev.name!r} — {ev.reason}")
            print(f"    phases: {ev.unique_phases[:8]}")

        print("\n--- auto_ok (first 10) ---")
        for ev in sorted(by_bucket["auto_ok"], key=lambda e: e.tournament_id)[:10]:
            print(f"  id={ev.tournament_id} {ev.name!r} — {ev.reason}")

        payload = {
            "deferred_world_cup": deferred_wc,
            "auto_ok": [e.tournament_id for e in by_bucket["auto_ok"]],
            "parser_fix": [
                {"id": e.tournament_id, "name": e.name, "reason": e.reason}
                for e in by_bucket["parser_fix"]
            ],
            "manual_review": [
                {"id": e.tournament_id, "name": e.name, "reason": e.reason}
                for e in by_bucket["manual_review"]
            ],
        }
        out_path = "scripts/oneoff/tier_b_non_wc_curation.json"
        with open(out_path, "w", encoding="utf-8") as fh:
            json.dump(payload, fh, indent=2)
        print(f"\nWrote {out_path}")
        print("auto_ok ids:", sorted(payload["auto_ok"]))
    finally:
        conn.close()


if __name__ == "__main__":
    main()
