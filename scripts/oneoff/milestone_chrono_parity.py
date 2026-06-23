#!/usr/bin/env python3
"""Compare chronological milestone counts: player_milestones vs probe."""
from __future__ import annotations

import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.k2_rating_core.config import load_db_config  # noqa: E402
from scripts.k2_rating_core.connection import connect  # noqa: E402
from scripts.oneoff.milestone_unlock_counts import (  # noqa: E402
    EXCLUDED_KEYS,
    run_chronological,
)

SKIP = frozenset({"peace_streak"})


def main() -> None:
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()
    cur.execute("SET time_zone = '+00:00'")
    probe = run_chronological(cur)
    keys = [k for k in probe if k not in EXCLUDED_KEYS and k not in SKIP]
    cur.execute(
        "SELECT milestone_key, COUNT(*) AS c FROM player_milestones "
        "WHERE milestone_key IN (%s) GROUP BY milestone_key"
        % ",".join("'%s'" % k for k in keys)
    )
    db = {str(r["milestone_key"]): int(r["c"]) for r in cur.fetchall()}
    bad = []
    for key in keys:
        want = probe[key].unlock
        got = db.get(key, 0)
        if got != want:
            bad.append((key, want, got))
    print(f"Chrono keys checked: {len(keys)}")
    print(f"Mismatches: {len(bad)}")
    for key, want, got in bad:
        print(f"  {key}: probe={want} db={got}")
    con.close()
    if bad:
        raise SystemExit(1)


if __name__ == "__main__":
    main()
