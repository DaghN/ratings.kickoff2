"""Check per-player game-count equality for tier A and multi-round tier C."""
from __future__ import annotations

from collections import Counter, defaultdict

from scripts.amiga.tournament_structure.materialize_legacy import (
    _connect,
    _distinct_player_ids,
    _full_round_robin_game_count,
)
from scripts.amiga.tournament_structure.verify_legacy import (
    _load_games,
    _parse_overrides,
    classify_legacy_tier,
)


def player_game_counts(games: list[dict]) -> dict[int, int]:
    counts: dict[int, int] = defaultdict(int)
    for g in games:
        counts[int(g["player_a_id"])] += 1
        counts[int(g["player_b_id"])] += 1
    return dict(counts)


def is_equal_games(counts: dict[int, int]) -> bool:
    if not counts:
        return True
    vals = set(counts.values())
    return len(vals) == 1


def rr_multiple(actual: int, expected: int) -> int | None:
    if expected <= 0 or actual < expected or actual % expected != 0:
        return None
    return actual // expected


def audit_group(
    conn,
    *,
    label: str,
    predicate,
) -> tuple[int, int, list[dict]]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, name, format_overrides FROM tournaments "
            "WHERE source_id IS NOT NULL ORDER BY id"
        )
        tournaments = list(cur.fetchall())

    total = 0
    uneven: list[dict] = []
    for row in tournaments:
        tid = int(row["id"])
        games = _load_games(conn, tid)
        overrides = _parse_overrides(row.get("format_overrides"))
        tier, _ = classify_legacy_tier(
            games,
            tournament_name=str(row["name"]),
            format_overrides=overrides,
        )
        n_players = len(_distinct_player_ids(games))
        expected = _full_round_robin_game_count(n_players)
        actual = len(games)
        mult = rr_multiple(actual, expected)

        if not predicate(tier, mult, n_players, actual, expected):
            continue

        total += 1
        counts = player_game_counts(games)
        if not is_equal_games(counts):
            vals = Counter(counts.values())
            uneven.append(
                {
                    "id": tid,
                    "name": str(row["name"]),
                    "players": n_players,
                    "games": actual,
                    "expected_1x": expected,
                    "rr_multiple": mult,
                    "per_player_counts": dict(sorted(vals.items())),
                    "sample": dict(list(sorted(counts.items()))[:6]),
                }
            )

    return total, len(uneven), uneven


def main() -> None:
    conn = _connect()
    try:
        # Tier A: single-round RR
        a_total, a_uneven_n, a_uneven = audit_group(
            conn,
            label="tier A (1x)",
            predicate=lambda tier, mult, *_: tier == "A",
        )
        print(f"Tier A (1x RR): {a_total} tournaments")
        print(f"  all players equal games: {a_total - a_uneven_n}")
        print(f"  uneven per-player games: {a_uneven_n}")
        for row in a_uneven[:10]:
            print(
                f"    id={row['id']} players={row['players']} games={row['games']} "
                f"distribution={row['per_player_counts']} name={row['name'][:55]}"
            )

        # Tier C with integer multiple >= 2
        c_total, c_uneven_n, c_uneven = audit_group(
            conn,
            label="tier C multi-round",
            predicate=lambda tier, mult, *_: tier == "C" and mult is not None and mult >= 2,
        )
        print()
        print(f"Tier C multi-round (k>=2): {c_total} tournaments")
        print(f"  all players equal games: {c_total - c_uneven_n}")
        print(f"  uneven per-player games: {c_uneven_n}")
        for row in c_uneven[:15]:
            print(
                f"    id={row['id']} {row['rr_multiple']}x players={row['players']} "
                f"games={row['games']} distribution={row['per_player_counts']} "
                f"name={row['name'][:50]}"
            )

        # Also check expected games per player for equal cases
        print()
        print("Expected per-player games (sanity):")
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, name, format_overrides FROM tournaments "
                "WHERE source_id IS NOT NULL ORDER BY id"
            )
            for row in cur.fetchall():
                tid = int(row["id"])
                games = _load_games(conn, tid)
                overrides = _parse_overrides(row.get("format_overrides"))
                tier, _ = classify_legacy_tier(
                    games,
                    tournament_name=str(row["name"]),
                    format_overrides=overrides,
                )
                n = len(_distinct_player_ids(games))
                exp = _full_round_robin_game_count(n)
                mult = rr_multiple(len(games), exp)
                if tier != "A" and not (tier == "C" and mult and mult >= 2):
                    continue
                counts = player_game_counts(games)
                if not counts:
                    continue
                per = next(iter(counts.values()))
                want = (n - 1) * (mult or 1)
                if per != want:
                    print(
                        f"  MISMATCH id={tid} tier={tier} mult={mult} "
                        f"per_player={per} want={want} name={row['name'][:40]}"
                    )
        print("  (no lines above = all match (n-1)*k)")
    finally:
        conn.close()


if __name__ == "__main__":
    main()
