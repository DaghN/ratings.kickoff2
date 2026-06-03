#!/usr/bin/env python3
"""Verify ratedresults derived columns match elo.py + outcome.py from stored RatingA/B and goals.

Use after PHP (or Python) replay on work DB — does not replace full prepare → PHP → prepare → Python A/B.

  python scripts/oneoff/verify_ratedresults_derived_rows.py --target sandbox --limit 100
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.ladder.config import load_db_config
from scripts.ladder.elo import compute_elo
from scripts.ladder.engine import connect
from scripts.ladder.outcome import outcome_from_goals

_DEFAULT_INI = _REPO / "site/config/ladder-work.ini"

DERIVED_FLOAT = (
    "RatingA",
    "RatingB",
    "RatingDifference",
    "ExpectedScoreA",
    "ExpectedScoreB",
    "AdjustmentA",
    "AdjustmentB",
    "NewRatingA",
    "NewRatingB",
    "ActualScore",
)
TOL = 0.001


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--target", default="sandbox")
    parser.add_argument("--limit", type=int, default=100)
    parser.add_argument("--until-game-id", type=int, default=None)
    args = parser.parse_args()

    ini = _DEFAULT_INI if _DEFAULT_INI.is_file() else None
    cfg = load_db_config(ini)
    conn = connect(cfg, dry_run=False, target=args.target)
    try:
        with conn.cursor() as cur:
            sql = (
                "SELECT id, idA, idB, GoalsA, GoalsB, "
                + ", ".join(DERIVED_FLOAT)
                + ", WinnerID, SumOfGoals, GoalDifference, HomeWin, Draw, AwayWin, "
                "DDPlayerA, DDPlayerB, CSPlayerA, CSPlayerB "
                "FROM ratedresults ORDER BY Date ASC, id ASC"
            )
            cur.execute(sql)
            rows = cur.fetchall()
        if args.until_game_id is not None:
            rows = [r for r in rows if int(r["id"]) <= args.until_game_id]
        if args.limit is not None:
            rows = rows[: args.limit]

        mismatches = 0
        for row in rows:
            if row["NewRatingA"] is None:
                print(f"SKIP id={row['id']} (not processed)")
                mismatches += 1
                continue
            gid = int(row["id"])
            id_a = int(row["idA"])
            id_b = int(row["idB"])
            ga = int(row["GoalsA"])
            gb = int(row["GoalsB"])
            outcome = outcome_from_goals(ga, gb, id_a, id_b)
            elo = compute_elo(float(row["RatingA"]), float(row["RatingB"]), outcome.actual_score)
            checks = {
                "ExpectedScoreA": elo.expected_a,
                "ExpectedScoreB": elo.expected_b,
                "AdjustmentA": elo.adjustment_a,
                "AdjustmentB": elo.adjustment_b,
                "NewRatingA": elo.new_rating_a,
                "NewRatingB": elo.new_rating_b,
                "RatingDifference": elo.rating_difference,
                "ActualScore": outcome.actual_score,
                "WinnerID": outcome.winner_id,
                "SumOfGoals": outcome.sum_of_goals,
                "GoalDifference": outcome.goal_difference,
                "HomeWin": outcome.home_win,
                "Draw": outcome.draw,
                "AwayWin": outcome.away_win,
                "DDPlayerA": outcome.dd_player_a,
                "DDPlayerB": outcome.dd_player_b,
                "CSPlayerA": outcome.cs_player_a,
                "CSPlayerB": outcome.cs_player_b,
            }
            for col, expected in checks.items():
                actual = row[col]
                if isinstance(expected, float):
                    if actual is None or abs(float(actual) - expected) > TOL:
                        print(f"MISMATCH id={gid} {col}: db={actual} expected={expected}")
                        mismatches += 1
                        break
                elif int(actual) != int(expected):
                    print(f"MISMATCH id={gid} {col}: db={actual} expected={expected}")
                    mismatches += 1
                    break

        n = len(rows)
        if mismatches == 0:
            print(f"OK: {n} games verified (tol={TOL})")
            return 0
        print(f"FAIL: {mismatches} problem(s) in {n} games")
        return 1
    finally:
        conn.close()


if __name__ == "__main__":
    raise SystemExit(main())
