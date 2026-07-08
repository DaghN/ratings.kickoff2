"""L4 structure overlay on ko2amiga_work (fork entry; logic from legacy apply_structure)."""

from __future__ import annotations

import logging

from scripts.amiga.apply_structure import (
    ApplyStructureStats,
    apply_structure_from_disposition,
)
from scripts.amiga.modern.work_db import connect_work

log = logging.getLogger(__name__)


def run_apply_structure_work(
    *,
    tournament_id: int | None = None,
    limit: int | None = None,
    dry_run: bool = False,
) -> ApplyStructureStats:
    conn = connect_work()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
            if int(cur.fetchone()["n"]) == 0:
                raise SystemExit("No L3 games in work DB — run seed-work first")

        stats = apply_structure_from_disposition(
            conn,
            tournament_id=tournament_id,
            limit=limit,
            dry_run=dry_run,
            clear_existing=True,
        )
        log.info("apply-structure-work: %s", stats.to_dict())
        return stats
    finally:
        conn.close()