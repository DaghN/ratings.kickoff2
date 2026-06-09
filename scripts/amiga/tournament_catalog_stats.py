"""Derived catalog aggregates for /amiga/tournaments.php index (one row per tournament)."""

from __future__ import annotations

import logging
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config

log = logging.getLogger(__name__)

_REBUILD_SQL = """
INSERT INTO amiga_tournament_catalog_stats (
    tournament_id, game_count, standing_players, standing_rows, group_scopes, knockout_ties
)
SELECT
    t.id,
    COALESCE(g.game_count, 0),
    COALESCE(s.standing_players, 0),
    COALESCE(s.standing_rows, 0),
    COALESCE(s.group_scopes, 0),
    COALESCE(s.knockout_ties, 0)
FROM tournaments t
LEFT JOIN (
    SELECT tournament_id, COUNT(*) AS game_count
    FROM amiga_games
    GROUP BY tournament_id
) g ON g.tournament_id = t.id
LEFT JOIN (
    SELECT tournament_id,
           COUNT(DISTINCT player_id) AS standing_players,
           COUNT(*) AS standing_rows,
           COUNT(DISTINCT CASE WHEN scope_type = 'group' THEN scope_key END) AS group_scopes,
           COUNT(DISTINCT CASE WHEN scope_type = 'knockout' THEN scope_key END) AS knockout_ties
    FROM amiga_tournament_standings
    GROUP BY tournament_id
) s ON s.tournament_id = t.id
ON DUPLICATE KEY UPDATE
    game_count = VALUES(game_count),
    standing_players = VALUES(standing_players),
    standing_rows = VALUES(standing_rows),
    group_scopes = VALUES(group_scopes),
    knockout_ties = VALUES(knockout_ties)
"""


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
        autocommit=False,
    )


def clear_catalog_stats(conn: pymysql.connections.Connection, *, dry_run: bool) -> None:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_tournament_catalog_stats")
        n = int(cur.fetchone()["n"])
    log.info("clear_catalog_stats: %s existing rows", n)
    if dry_run:
        return
    with conn.cursor() as cur:
        cur.execute("DELETE FROM amiga_tournament_catalog_stats")
    conn.commit()


def rebuild_all_catalog_stats(conn: pymysql.connections.Connection, *, dry_run: bool = False) -> int:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM tournaments")
        n = int(cur.fetchone()["n"])
    log.info("rebuild_all_catalog_stats: %s tournaments", n)
    if dry_run:
        return n
    with conn.cursor() as cur:
        cur.execute(_REBUILD_SQL)
        cur.execute("SELECT COUNT(*) AS n FROM amiga_tournament_catalog_stats")
        written = int(cur.fetchone()["n"])
    conn.commit()
    log.info("amiga_tournament_catalog_stats: %s rows", written)
    return written


_CATALOG_REFRESH_ONE_SQL = """
INSERT INTO amiga_tournament_catalog_stats (
    tournament_id, game_count, standing_players, standing_rows, group_scopes, knockout_ties
)
SELECT
    %s,
    (SELECT COUNT(*) FROM amiga_games WHERE tournament_id = %s),
    COALESCE((
        SELECT COUNT(DISTINCT player_id) FROM amiga_tournament_standings WHERE tournament_id = %s
    ), 0),
    COALESCE((SELECT COUNT(*) FROM amiga_tournament_standings WHERE tournament_id = %s), 0),
    COALESCE((
        SELECT COUNT(DISTINCT scope_key) FROM amiga_tournament_standings
        WHERE tournament_id = %s AND scope_type = 'group'
    ), 0),
    COALESCE((
        SELECT COUNT(DISTINCT scope_key) FROM amiga_tournament_standings
        WHERE tournament_id = %s AND scope_type = 'knockout'
    ), 0)
ON DUPLICATE KEY UPDATE
    game_count = VALUES(game_count),
    standing_players = VALUES(standing_players),
    standing_rows = VALUES(standing_rows),
    group_scopes = VALUES(group_scopes),
    knockout_ties = VALUES(knockout_ties)
"""


def refresh_catalog_stats_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> None:
    """Upsert one tournament row in amiga_tournament_catalog_stats."""
    params = (tournament_id,) * 6
    with conn.cursor() as cur:
        cur.execute(_CATALOG_REFRESH_ONE_SQL, params)
    conn.commit()


def run_catalog_stats_rebuild(*, dry_run: bool = False) -> int:
    conn = _connect()
    try:
        return rebuild_all_catalog_stats(conn, dry_run=dry_run)
    finally:
        conn.close()
