"""Zero derived — §4.3 core ladder + §4.5 aggregate truncates."""

from __future__ import annotations

import logging

from scripts.ladder.engine import reset_universe

from .constants import AGGREGATE_TABLES_TRUNCATE, CATALOG_TABLES_NEVER_TRUNCATE
from .db import connect
from .guards import assert_mutate_work_target
from .seed_lobby import seed_lobby_milestones
from .targets import WorkTarget

log = logging.getLogger(__name__)


def _table_exists(conn, table: str) -> bool:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) AS n FROM information_schema.tables "
            "WHERE table_schema = DATABASE() AND table_name = %s",
            (table,),
        )
        return int(cur.fetchone()["n"]) > 0


def truncate_aggregate_tables(target: WorkTarget, *, dry_run: bool) -> None:
    conn = connect(target)
    try:
        for table in AGGREGATE_TABLES_TRUNCATE:
            if table in CATALOG_TABLES_NEVER_TRUNCATE:
                continue
            if not _table_exists(conn, table):
                log.info("truncate skip (missing): %s", table)
                continue
            log.info("truncate %s", table)
            if not dry_run:
                with conn.cursor() as cur:
                    cur.execute(f"TRUNCATE TABLE `{table}`")
        if not dry_run:
            conn.commit()
    finally:
        conn.close()


def zero_derived(target: WorkTarget, *, dry_run: bool = False) -> None:
    assert_mutate_work_target(target)
    log.info("zero_derived profile=%s database=%s dry_run=%s", target.profile, target.work_database, dry_run)

    conn = connect(target)
    try:
        reset_universe(conn, dry_run=dry_run)
    finally:
        conn.close()

    truncate_aggregate_tables(target, dry_run=dry_run)
    seed_lobby_milestones(target, dry_run=dry_run)
    log.info("[OK] zero_derived complete on %s", target.work_database)
