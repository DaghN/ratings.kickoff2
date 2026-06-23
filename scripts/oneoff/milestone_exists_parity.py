#!/usr/bin/env python3
"""Compare exists-milestone row counts: player_milestones vs probe."""
from __future__ import annotations

import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.k2_rating_core.connection import connect  # noqa: E402
from scripts.k2_rating_core.config import load_db_config  # noqa: E402
from scripts.oneoff.milestone_unlock_counts import (  # noqa: E402
    ratedresults_exists_counts,
)

SKIP = frozenset({"dd_merchant_10", "six_goal_draw"})


def main() -> None:
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()
    cur.execute("SET time_zone = '+00:00'")
    probe = ratedresults_exists_counts(cur)
    cur.execute(
        "SELECT milestone_key, COUNT(*) AS c FROM player_milestones GROUP BY milestone_key"
    )
    db = {str(r["milestone_key"]): int(r["c"]) for r in cur.fetchall()}
    bad = []
    for key, pr in sorted(probe.items()):
        if key in SKIP:
            continue
        got = db.get(key, 0)
        if got != pr.unlock:
            bad.append((key, pr.unlock, got))
    print(f"Distinct keys in player_milestones: {len(db)}")
    print(f"Exists probe keys (excl skip): {len(probe) - len(SKIP)}")
    print(f"Mismatches: {len(bad)}")
    for row in bad:
        print(f"  {row[0]}: probe={row[1]} db={row[2]}")
    con.close()
    if bad:
        raise SystemExit(1)


if __name__ == "__main__":
    main()
