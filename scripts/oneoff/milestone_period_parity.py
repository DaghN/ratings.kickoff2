#!/usr/bin/env python3
"""Compare period-burst milestone counts: player_milestones vs probe."""
from __future__ import annotations

import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.k2_rating_core.config import load_db_config  # noqa: E402
from scripts.k2_rating_core.connection import connect  # noqa: E402
from scripts.oneoff.milestone_unlock_counts import period_burst_counts  # noqa: E402

PERIOD_KEYS = (
    "hot_day",
    "marathon_day",
    "absurd_day",
    "ultra_day_30",
    "grind_month",
)


def main() -> None:
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()
    cur.execute("SET time_zone = '+00:00'")
    probe = period_burst_counts(cur)
    cur.execute(
        "SELECT milestone_key, COUNT(*) AS c FROM player_milestones "
        "WHERE milestone_key IN (%s) GROUP BY milestone_key"
        % ",".join("'%s'" % k for k in PERIOD_KEYS)
    )
    db = {str(r["milestone_key"]): int(r["c"]) for r in cur.fetchall()}
    bad = []
    for key in PERIOD_KEYS:
        want = probe[key].unlock if key in probe else 0
        got = db.get(key, 0)
        if got != want:
            bad.append((key, want, got, probe.get(key, None)))
    print(f"Period keys in DB: {len(db)}")
    print(f"Mismatches: {len(bad)}")
    for key, want, got, pr in bad:
        note = pr.note if pr else ""
        print(f"  {key}: probe={want} db={got} ({note})")
    con.close()
    if bad:
        raise SystemExit(1)


if __name__ == "__main__":
    main()
