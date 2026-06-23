#!/usr/bin/env python3
"""Compare tail milestone counts: player_milestones vs probe."""
from __future__ import annotations

import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.oneoff.milestone_tail_gen_check import TAIL_KEYS, probe_tail  # noqa: E402
from scripts.k2_rating_core.config import load_db_config  # noqa: E402
from scripts.k2_rating_core.connection import connect  # noqa: E402


def main() -> None:
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()
    want = probe_tail(cur)
    cur.execute(
        "SELECT milestone_key, COUNT(*) AS c FROM player_milestones "
        "WHERE milestone_key IN (%s) GROUP BY milestone_key"
        % ",".join("'%s'" % k for k in TAIL_KEYS)
    )
    db = {str(r["milestone_key"]): int(r["c"]) for r in cur.fetchall()}
    con.close()
    bad = []
    for key in TAIL_KEYS:
        if db.get(key, 0) != want.get(key, 0):
            bad.append((key, want.get(key, 0), db.get(key, 0)))
    print(f"Tail keys checked: {len(TAIL_KEYS)}")
    print(f"Mismatches: {len(bad)}")
    for key, w, g in bad:
        print(f"  {key}: probe={w} db={g}")
    if bad:
        raise SystemExit(1)


if __name__ == "__main__":
    main()
