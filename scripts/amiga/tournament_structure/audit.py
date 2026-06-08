"""Audit Access tournaments that look like unstructured marathons."""

from __future__ import annotations

import argparse
import json
import sys
from collections import Counter, defaultdict
from datetime import date, datetime
from pathlib import Path
from typing import Any

from scripts.amiga.import_access import _DEFAULT_MDB, connect_access, load_access_scores, load_access_tournaments
from scripts.amiga.tournament_names import resolve_tournament_name
from scripts.amiga.tournament_structure.registry import registry_status_for_catalog


def _full_round_robin_count(player_count: int) -> int:
    if player_count < 2:
        return 0
    return player_count * (player_count - 1) // 2


def _games_by_parent(scores: list) -> dict[str, list]:
    by_parent: dict[str, list] = defaultdict(list)
    for s in scores:
        parent = resolve_tournament_name(s.raw_tournament)
        if parent:
            by_parent[parent].append(s)
    return dict(by_parent)


def _player_game_counts(games: list) -> Counter[str]:
    counts: Counter[str] = Counter()
    for g in games:
        counts[g.team_a] += 1
        counts[g.team_b] += 1
    return counts


def audit_suspicious_marathons(
    *,
    mdb: Path,
    min_games: int = 10,
    min_players: int = 4,
) -> dict[str, Any]:
    """Scan Access for tournaments with NULL phases and uneven / non-RR game counts."""
    acc = connect_access(mdb)
    acc_cur = acc.cursor()
    tournaments = load_access_tournaments(acc_cur)
    scores = load_access_scores(acc_cur)
    acc.close()

    catalog_by_name = {t["name"]: t for t in tournaments}
    games_by_parent = _games_by_parent(scores)

    suspicious: list[dict[str, Any]] = []
    for name, games in sorted(games_by_parent.items()):
        if len(games) < min_games:
            continue

        players = {g.team_a for g in games} | {g.team_b for g in games}
        if len(players) < min_players:
            continue

        null_phases = sum(1 for g in games if not g.phase)
        null_phase_rate = null_phases / len(games)
        if null_phase_rate < 1.0:
            continue

        counts = _player_game_counts(games)
        min_g = min(counts.values())
        max_g = max(counts.values())
        uneven = min_g != max_g

        rr_expected = _full_round_robin_count(len(players))
        not_full_rr = len(games) != rr_expected

        if not (uneven or not_full_rr):
            continue

        meta = catalog_by_name.get(name, {})
        ev = meta.get("event_date")
        event_date = None
        if isinstance(ev, datetime):
            event_date = ev.date().isoformat()
        elif isinstance(ev, date):
            event_date = ev.isoformat()

        spec_status = registry_status_for_catalog(name)
        suspicious.append(
            {
                "catalog_name": name,
                "structure_spec_status": spec_status,
                "source_id": meta.get("source_id"),
                "event_date": event_date,
                "player_count_catalog": meta.get("player_count"),
                "player_count_scores": len(players),
                "game_count": len(games),
                "null_phase_count": null_phases,
                "null_phase_rate": round(null_phase_rate, 4),
                "games_per_player_min": min_g,
                "games_per_player_max": max_g,
                "uneven_game_counts": uneven,
                "full_round_robin_expected": rr_expected,
                "not_full_round_robin": not_full_rr,
            }
        )

    suspicious.sort(key=lambda row: (-row["game_count"], row["catalog_name"]))

    return {
        "audit": "suspicious_marathons",
        "source_mdb": str(mdb.resolve()),
        "criteria": {
            "min_games": min_games,
            "min_players": min_players,
            "null_phase_rate_min": 1.0,
            "flags": ["uneven_game_counts", "not_full_round_robin"],
        },
        "count": len(suspicious),
        "tournaments": suspicious,
    }


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="JSON report: Access tournaments with NULL phases and uneven/non-RR game counts",
    )
    parser.add_argument("--mdb", type=Path, default=_DEFAULT_MDB)
    parser.add_argument("--min-games", type=int, default=10)
    parser.add_argument("--min-players", type=int, default=4)
    parser.add_argument("--out", type=Path, default=None, help="Write JSON to file (default: stdout)")
    args = parser.parse_args(argv)

    report = audit_suspicious_marathons(
        mdb=args.mdb,
        min_games=args.min_games,
        min_players=args.min_players,
    )
    payload = json.dumps(report, indent=2) + "\n"
    if args.out:
        args.out.parent.mkdir(parents=True, exist_ok=True)
        args.out.write_text(payload, encoding="utf-8")
        print(f"Wrote {args.out} ({report['count']} tournaments)")
    else:
        print(payload, end="")
    return 0


if __name__ == "__main__":
    sys.exit(main())
