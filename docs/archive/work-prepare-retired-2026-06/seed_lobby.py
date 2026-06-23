"""Seed ground-truth lobby milestones after zero-derived (entered_arena from JoinDate)."""

from __future__ import annotations

import logging

from .constants import JOIN_DATE_VALID_WHERE
from .db import connect
from .guards import assert_mutate_work_target
from .targets import WorkTarget

log = logging.getLogger(__name__)

_LOBBY_INSERT_SQL = f"""
INSERT INTO `player_milestones` (
  `player_id`, `milestone_key`, `achieved_at`, `value`,
  `source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`
)
SELECT
  `ID`, 'entered_arena', `JoinDate`, 1,
  'lobby', NULL, NULL, NULL, NULL
FROM `playertable`
WHERE {JOIN_DATE_VALID_WHERE}
  AND NOT EXISTS (
    SELECT 1 FROM `player_milestones` pm
    WHERE pm.`player_id` = `playertable`.`ID` AND pm.`milestone_key` = 'entered_arena'
  )
"""


def count_join_date_eligible(conn) -> int:
    with conn.cursor() as cur:
        cur.execute(f"SELECT COUNT(*) AS n FROM playertable WHERE {JOIN_DATE_VALID_WHERE}")
        return int(cur.fetchone()["n"])


def seed_lobby_milestones(target: WorkTarget, *, dry_run: bool = False) -> int:
    """Insert entered_arena for every player with valid JoinDate. Returns rows inserted."""
    assert_mutate_work_target(target)
    log.info(
        "seed_lobby_milestones profile=%s database=%s dry_run=%s",
        target.profile,
        target.work_database,
        dry_run,
    )

    conn = connect(target)
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT COUNT(*) AS n FROM information_schema.tables "
                "WHERE table_schema = DATABASE() AND table_name = 'player_milestones'"
            )
            if int(cur.fetchone()["n"]) == 0:
                log.info("player_milestones missing — skip seed_lobby")
                return 0

        eligible = count_join_date_eligible(conn)
        if dry_run:
            log.info("seed_lobby dry-run: eligible players=%s", eligible)
            return 0

        with conn.cursor() as cur:
            cur.execute("SET time_zone = '+00:00'")
            cur.execute(_LOBBY_INSERT_SQL)
            inserted = cur.rowcount
        conn.commit()
        log.info("[OK] seed_lobby_milestones: inserted=%s eligible=%s", inserted, eligible)
        return inserted
    finally:
        conn.close()
