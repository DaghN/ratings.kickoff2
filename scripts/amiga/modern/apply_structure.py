"""L4 structure overlay on ko2amiga_work (fork entry; logic from legacy apply_structure)."""

from __future__ import annotations

import logging

from scripts.amiga.apply_structure import (
    ApplyStructureStats,
    apply_structure_from_disposition,
)
from scripts.amiga.modern.work_db import connect_work

log = logging.getLogger(__name__)


def _living_l4_on_work(conn) -> bool:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM tournament_stages")
        stages = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM tournament_fixtures")
        fixtures = int(cur.fetchone()["n"])
    return stages > 0 or fixtures > 0


def run_apply_structure_work(
    *,
    tournament_id: int | None = None,
    limit: int | None = None,
    dry_run: bool = False,
    destroy_work: bool = False,
    confirm_destroy: str | None = None,
    force: bool = False,
) -> ApplyStructureStats:
    conn = connect_work()
    try:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
            if int(cur.fetchone()["n"]) == 0:
                raise SystemExit("No L3 games in work DB — run seed-work first")

        if tournament_id is None and _living_l4_on_work(conn):
            from scripts.amiga.modern.work_safety import assert_safe_to_nuke_work

            assert_safe_to_nuke_work(
                operation="apply-structure-work",
                cli_destroy_flag=destroy_work,
                confirm_phrase=confirm_destroy,
            )

        stats = apply_structure_from_disposition(
            conn,
            tournament_id=tournament_id,
            limit=limit,
            dry_run=dry_run,
            clear_existing=True,
            force=force,
        )
        log.info("apply-structure-work: %s", stats.to_dict())
        return stats
    finally:
        conn.close()
