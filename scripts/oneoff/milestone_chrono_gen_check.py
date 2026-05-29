#!/usr/bin/env python3
"""Verify gen_milestone_chrono_sql row counts match run_chronological probe."""
from __future__ import annotations

import re
import sys
from collections import Counter
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.ladder.config import load_db_config  # noqa: E402
from scripts.ladder.engine import connect  # noqa: E402
from scripts.oneoff.milestone_unlock_counts import EXCLUDED_KEYS, run_chronological  # noqa: E402

SKIP = frozenset({"peace_streak"})


def main() -> None:
    sql = (_REPO / "scripts/ladder/sql/player_milestones_rebuild_chrono.sql").read_text()
    gen = Counter(re.findall(r"VALUES \(\d+, '([^']+)'", sql))
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()
    probe = run_chronological(cur)
    con.close()
    bad = []
    for key in sorted(gen):
        if key in EXCLUDED_KEYS or key in SKIP:
            continue
        want = probe[key].unlock
        got = gen[key]
        if want != got:
            bad.append((key, want, got))
    print(f"Mismatches: {len(bad)}")
    for row in bad:
        print(f"  {row[0]}: probe={row[1]} gen={row[2]}")
    if bad:
        raise SystemExit(1)
    print("OK — all generated keys match probe counts")


if __name__ == "__main__":
    main()
