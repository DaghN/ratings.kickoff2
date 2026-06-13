"""One-off: bucket tier-C tournaments by game count vs full RR expectation."""
from __future__ import annotations

from collections import Counter

from scripts.amiga.tournament_structure.materialize_legacy import (
    _distinct_player_ids,
    _full_round_robin_game_count,
    _connect,
)
from scripts.amiga.tournament_structure.verify_legacy import (
    _load_games,
    _parse_overrides,
    classify_legacy_tier,
)


def bucket(row: dict) -> str:
    expected = row["expected_rr"]
    actual = row["games"]
    if expected == 0:
        return "no_rr_formula"
    if actual == expected:
        return "exact_1x"
    if actual == 2 * expected:
        return "exact_2x"
    if actual == 3 * expected:
        return "exact_3x"
    if actual % expected == 0:
        return f"exact_{actual // expected}x"
    if actual < expected:
        return "under_rr"
    return "other"


def main() -> None:
    conn = _connect()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id, name, format_overrides FROM tournaments "
                "WHERE source_id IS NOT NULL ORDER BY id"
            )
            tournaments = list(cur.fetchall())

        tier_c: list[dict] = []
        for row in tournaments:
            tid = int(row["id"])
            games = _load_games(conn, tid)
            overrides = _parse_overrides(row.get("format_overrides"))
            tier, detail = classify_legacy_tier(
                games,
                tournament_name=str(row["name"]),
                format_overrides=overrides,
            )
            if tier != "C":
                continue
            n_players = len(_distinct_player_ids(games))
            expected = _full_round_robin_game_count(n_players)
            actual = len(games)
            tier_c.append(
                {
                    "id": tid,
                    "name": str(row["name"]),
                    "players": n_players,
                    "games": actual,
                    "expected_rr": expected,
                    "detail": detail,
                }
            )

        bc = Counter(bucket(r) for r in tier_c)
        print(f"Tier C count: {len(tier_c)}")
        print("Ratio buckets:")
        for key, count in sorted(bc.items(), key=lambda x: (-x[1], x[0])):
            print(f"  {key}: {count}")

        samples: dict[str, list[dict]] = {}
        for row in sorted(tier_c, key=bucket):
            key = bucket(row)
            samples.setdefault(key, [])
            if len(samples[key]) < 5:
                samples[key].append(row)

        print()
        for key in sorted(samples, key=lambda k: -bc[k]):
            print(f"--- {key} ({bc[key]}) samples ---")
            for s in samples[key]:
                print(
                    f"  id={s['id']} players={s['players']} games={s['games']} "
                    f"expected={s['expected_rr']} name={s['name'][:70]}"
                )

        multi = [
            r
            for r in tier_c
            if r["expected_rr"] and r["games"] % r["expected_rr"] == 0 and r["games"] >= r["expected_rr"]
        ]
        print()
        print(f"Integer-multiple of expected RR: {len(multi)}")
        for mult in sorted({r["games"] // r["expected_rr"] for r in multi}):
            count = sum(1 for r in multi if r["games"] // r["expected_rr"] == mult)
            print(f"  {mult}x: {count}")
    finally:
        conn.close()


if __name__ == "__main__":
    main()
