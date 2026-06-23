#!/usr/bin/env python3
"""Verify gen_milestone_tail_sql row counts match probe."""
from __future__ import annotations

import json
import re
import sys
from collections import Counter
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.k2_rating_core.config import load_db_config  # noqa: E402
from scripts.k2_rating_core.connection import connect  # noqa: E402
from scripts.oneoff.milestone_unlock_counts import (  # noqa: E402
    clean_sheet_spread_count,
    dd_distinct_opponents_count,
    matchup_counts,
    playertable_counts,
    travelling_salesman_count,
)

TAIL_KEYS = [
    "half_century_50",
    "centurion_100",
    "marathoner_250",
    "millennium_merchant_1000",
    "first_victory",
    "first_goal",
    "first_handshake",
    "welcome_to_the_ladder",
    "first_shutout",
    "century_of_wins",
    "battle_scarred",
    "ten_draws",
    "hundred_goals",
    "thousand_goal_club",
    "fortress_builder",
    "clean_sheet_artist",
    "ten_opponents",
    "wide_net",
    "fifty_faces",
    "century_of_rivals",
    "five_victims",
    "twenty_five_victims",
    "ten_culprits",
    "ten_match_saga",
    "lifetime_rivalry",
    "regular_customer",
    "bogeyman",
    "diversity_merchant",
    "travelling_salesman",
    "clean_sheet_spread",
]


def probe_tail(cur) -> dict[str, int]:
    pt = playertable_counts(cur)
    mu = matchup_counts(cur)
    out: dict[str, int] = {}
    for k in TAIL_KEYS:
        if k in pt:
            out[k] = pt[k].unlock
        elif k in mu:
            out[k] = mu[k].unlock
    out["diversity_merchant"] = dd_distinct_opponents_count(cur, 5).unlock
    out["travelling_salesman"] = travelling_salesman_count(cur).unlock
    out["clean_sheet_spread"] = clean_sheet_spread_count(cur).unlock
    return out


def main() -> None:
    sql = (_REPO / "docs/archive/batch-rebuild-sql-2026-05/player_milestones_rebuild_tail.sql").read_text()
    gen = Counter(re.findall(r"VALUES \(\d+, '([^']+)'", sql))
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()
    want = probe_tail(cur)
    con.close()
    bad = []
    for key in TAIL_KEYS:
        if gen.get(key, 0) != want.get(key, 0):
            bad.append((key, want.get(key, 0), gen.get(key, 0)))
    print(f"Mismatches: {len(bad)}")
    for row in bad:
        print(f"  {row[0]}: probe={row[1]} gen={row[2]}")
    if bad:
        raise SystemExit(1)
    print("OK — all tail keys match probe counts")


if __name__ == "__main__":
    main()
