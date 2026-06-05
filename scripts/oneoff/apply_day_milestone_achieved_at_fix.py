#!/usr/bin/env python3
"""Re-apply perfect_day / nightmare_day rows from regenerated chrono SQL."""
from __future__ import annotations

import sys
from pathlib import Path

_REPO = Path(__file__).resolve().parents[2]
if str(_REPO) not in sys.path:
    sys.path.insert(0, str(_REPO))

from scripts.ladder.config import load_db_config  # noqa: E402
from scripts.ladder.engine import connect  # noqa: E402

CHRONO = _REPO / "scripts" / "ladder" / "sql" / "archive" / "batch-2026-05" / "player_milestones_rebuild_chrono.sql"
OUT_SQL = _REPO / "scripts" / "ladder" / "sql" / "player_milestones_fix_day_close.sql"


def export_sql() -> None:
    lines = [
        ln
        for ln in CHRONO.read_text(encoding="utf-8").splitlines()
        if ln.startswith("INSERT")
        and ("'perfect_day'" in ln or "'nightmare_day'" in ln)
    ]
    body = [
        "-- Surgical: perfect_day + nightmare_day (day-close achieved_at)",
        "-- mysql -u MYSQL_USER -p kooldb < staging-sql/milestones/player_milestones_fix_day_close.sql",
        "SET time_zone = '+00:00';",
        "",
        "DELETE FROM player_milestones WHERE milestone_key IN ('perfect_day', 'nightmare_day');",
        "",
        *lines,
        "",
    ]
    OUT_SQL.write_text("\n".join(body), encoding="utf-8")
    print(f"Wrote {OUT_SQL} ({len(lines)} INSERTs)")


def main() -> None:
    lines = [
        ln
        for ln in CHRONO.read_text(encoding="utf-8").splitlines()
        if ln.startswith("INSERT")
        and ("'perfect_day'" in ln or "'nightmare_day'" in ln)
    ]
    con = connect(load_db_config(), dry_run=False)
    cur = con.cursor()
    cur.execute(
        "DELETE FROM player_milestones WHERE milestone_key IN ('perfect_day', 'nightmare_day')"
    )
    deleted = cur.rowcount
    for ln in lines:
        cur.execute(ln)
    con.commit()
    cur.execute(
        """
        SELECT milestone_key, achieved_at, source_game_id
        FROM player_milestones
        WHERE milestone_key IN ('perfect_day', 'nightmare_day')
        ORDER BY achieved_at DESC
        LIMIT 3
        """
    )
    print(f"Deleted {deleted}, inserted {len(lines)}")
    for r in cur.fetchall():
        print(dict(r))
    con.close()


if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "--export-sql":
        export_sql()
    else:
        main()
