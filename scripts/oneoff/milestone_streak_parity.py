#!/usr/bin/env python3
"""Parity: streak milestones in player_milestones vs playertable proxies."""
from __future__ import annotations

import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.k2_rating_core.config import load_db_config  # noqa: E402
from scripts.k2_rating_core.connection import connect  # noqa: E402

PROXY = {
    "win_hat_trick": "LongestWinningStreak >= 3",
    "ten_wins_straight": "LongestWinningStreak >= 10",
    "rampage": "LongestWinningStreak >= 15",
    "win_streak_30": "LongestWinningStreak >= 30",
    "cold_streak": "LongestLosingStreak >= 5",
    "win_drought": "LongestNonWinStreak >= 10",
    "ten_wins": "NumberWins >= 10",
}


def main() -> None:
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()
    cur.execute("SET time_zone = '+00:00'")
    cur.execute(
        "SELECT milestone_key, COUNT(*) AS c FROM player_milestones "
        "WHERE milestone_key IN (%s) GROUP BY milestone_key"
        % ",".join("'%s'" % k for k in PROXY)
    )
    db = {str(r["milestone_key"]): int(r["c"]) for r in cur.fetchall()}
    bad = []
    for key, cond in PROXY.items():
        cur.execute(
            f"SELECT COUNT(*) AS n FROM playertable WHERE NumberGames >= 1 AND ({cond})"
        )
        want = int(cur.fetchone()["n"])
        got = db.get(key, 0)
        if got != want:
            bad.append((key, want, got))
    # peace_streak: probe uses chronological only — count DB row
    cur.execute(
        "SELECT COUNT(*) AS n FROM player_milestones WHERE milestone_key = 'peace_streak'"
    )
    print(f"peace_streak rows: {cur.fetchone()['n']}")
    print(f"Proxy mismatches: {len(bad)}")
    for row in bad:
        print(f"  {row[0]}: playertable={row[1]} db={row[2]}")
    con.close()
    if bad:
        raise SystemExit(1)


if __name__ == "__main__":
    main()
