"""Add the extra_time step to knockout stages that still store the old catalog chain.

Old chain: aggregate_goal_difference, goals_for, penalty_shootout. A tie that was level on goals and
decided in extra time went to the winner of the leg that has the note. New chain: extra_time before
penalty_shootout, the same as LEGACY_KNOCKOUT_BRIDGE_STEPS (scripts/amiga/scoring_contract.py).

    python scripts/oneoff/amiga_add_extra_time_step_2026_11.py            # report only
    python scripts/oneoff/amiga_add_extra_time_step_2026_11.py --apply    # ko2amiga_work, then simul

Running it twice is harmless. Run it after restoring an older checkpoint.
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from scripts.amiga.scoring_contract import LEGACY_KNOCKOUT_BRIDGE_STEPS

WORK_DB = "ko2amiga_work"
OLD_CHAIN = "aggregate_goal_difference,goals_for,penalty_shootout"


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()

    conn = pymysql.connect(
        host="127.0.0.1", user="root", password="", database=WORK_DB, charset="utf8mb4", cursorclass=DictCursor
    )
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT s.id, t.name
            FROM tournament_stage_scoring_steps x
            INNER JOIN tournament_stages s ON s.id = x.stage_id
            INNER JOIN tournaments t ON t.id = s.tournament_id
            GROUP BY s.id, t.name
            HAVING GROUP_CONCAT(x.step ORDER BY x.sequence_no) = %s
            """,
            (OLD_CHAIN,),
        )
        stages = cur.fetchall()
        print(f"{len(stages)} stages with the old chain in {len({s['name'] for s in stages})} tournaments")
        if args.apply:
            for stage in stages:
                cur.execute("DELETE FROM tournament_stage_scoring_steps WHERE stage_id = %s", (stage["id"],))
                cur.executemany(
                    "INSERT INTO tournament_stage_scoring_steps (stage_id, sequence_no, step) VALUES (%s, %s, %s)",
                    [(stage["id"], n, step) for n, step in enumerate(LEGACY_KNOCKOUT_BRIDGE_STEPS, start=1)],
                )
            conn.commit()
            print(f"updated {len(stages)} stages in {WORK_DB}; now run: python -m scripts.amiga simul")
    conn.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
