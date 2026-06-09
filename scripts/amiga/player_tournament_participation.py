"""Derived player x tournament participation (overall standing + catalog + rating context)."""

from __future__ import annotations

import logging
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_honours import is_world_cup_tournament, refresh_wc_medals

log = logging.getLogger(__name__)

_REBUILD_INSERT_SQL = """
INSERT INTO amiga_player_tournament_participation (
    player_id,
    tournament_id,
    event_date,
    event_chrono,
    tournament_name,
    is_cup,
    country,
    has_league,
    has_cup,
    overall_position,
    points,
    games,
    wins,
    draws,
    losses,
    goals_for,
    goals_against,
    rating_before,
    rating_delta,
    rating_after,
    games_in_event,
    finalized_at,
    is_winner,
    wc_medal
)
SELECT
    s.player_id,
    s.tournament_id,
    t.event_date,
    t.chrono AS event_chrono,
    t.name AS tournament_name,
    t.is_cup,
    t.country,
    t.has_league,
    t.has_cup,
    s.position AS overall_position,
    s.points,
    s.games,
    s.wins,
    s.draws,
    s.losses,
    s.goals_for,
    s.goals_against,
    e.rating_before,
    e.rating_delta,
    e.rating_after,
    COALESCE(e.games_in_event, 0) AS games_in_event,
    e.finalized_at,
    CASE WHEN s.position = 1 THEN 1 ELSE 0 END AS is_winner,
    'none' AS wc_medal
FROM amiga_tournament_standings s
INNER JOIN tournaments t ON t.id = s.tournament_id
LEFT JOIN amiga_rating_events e
    ON e.tournament_id = s.tournament_id AND e.player_id = s.player_id
WHERE s.scope_type = 'overall'
  AND s.scope_key = ''
__TOURNAMENT_FILTER__
"""

_REBUILD_INSERT_ALL_SQL = _REBUILD_INSERT_SQL.replace("__TOURNAMENT_FILTER__", "")
_REBUILD_INSERT_FOR_TOURNAMENT_SQL = _REBUILD_INSERT_SQL.replace(
    "__TOURNAMENT_FILTER__",
    "AND s.tournament_id = %(tournament_id)s",
)

_WC_SUPPLEMENT_INSERT_SQL = """
INSERT INTO amiga_player_tournament_participation (
    player_id,
    tournament_id,
    event_date,
    event_chrono,
    tournament_name,
    is_cup,
    country,
    has_league,
    has_cup,
    overall_position,
    points,
    games,
    wins,
    draws,
    losses,
    goals_for,
    goals_against,
    rating_before,
    rating_delta,
    rating_after,
    games_in_event,
    finalized_at,
    is_winner,
    wc_medal
)
SELECT
    ep.player_id,
    ep.tournament_id,
    t.event_date,
    t.chrono AS event_chrono,
    t.name AS tournament_name,
    t.is_cup,
    t.country,
    t.has_league,
    t.has_cup,
    gs.position AS overall_position,
    COALESCE(gs.points, 0) AS points,
    COALESCE(gs.games, 0) AS games,
    COALESCE(gs.wins, 0) AS wins,
    COALESCE(gs.draws, 0) AS draws,
    COALESCE(gs.losses, 0) AS losses,
    COALESCE(gs.goals_for, 0) AS goals_for,
    COALESCE(gs.goals_against, 0) AS goals_against,
    e.rating_before,
    e.rating_delta,
    e.rating_after,
    COALESCE(e.games_in_event, 0) AS games_in_event,
    e.finalized_at,
    0 AS is_winner,
    'none' AS wc_medal
FROM (
    SELECT DISTINCT g.tournament_id, g.player_id
    FROM (
        SELECT tournament_id, player_a_id AS player_id FROM amiga_games
        UNION ALL
        SELECT tournament_id, player_b_id AS player_id FROM amiga_games
    ) g
) ep
INNER JOIN tournaments t ON t.id = ep.tournament_id
LEFT JOIN amiga_rating_events e
    ON e.tournament_id = ep.tournament_id AND e.player_id = ep.player_id
LEFT JOIN amiga_tournament_standings gs
    ON gs.tournament_id = ep.tournament_id
   AND gs.player_id = ep.player_id
   AND gs.scope_type = 'group'
   AND gs.scope_key = (
       SELECT MIN(s2.scope_key)
       FROM amiga_tournament_standings s2
       WHERE s2.tournament_id = ep.tournament_id
         AND s2.player_id = ep.player_id
         AND s2.scope_type = 'group'
   )
WHERE t.name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
  AND NOT EXISTS (
      SELECT 1
      FROM amiga_player_tournament_participation p
      WHERE p.tournament_id = ep.tournament_id
        AND p.player_id = ep.player_id
  )
__WC_TOURNAMENT_FILTER__
"""

_WC_SUPPLEMENT_INSERT_ALL_SQL = _WC_SUPPLEMENT_INSERT_SQL.replace("__WC_TOURNAMENT_FILTER__", "")
_WC_SUPPLEMENT_INSERT_FOR_TOURNAMENT_SQL = _WC_SUPPLEMENT_INSERT_SQL.replace(
    "__WC_TOURNAMENT_FILTER__",
    "AND ep.tournament_id = %(tournament_id)s",
)


def _insert_wc_participation_supplement(
    conn: pymysql.connections.Connection,
    *,
    tournament_id: int | None = None,
) -> int:
    with conn.cursor() as cur:
        if tournament_id is not None:
            cur.execute(
                "SELECT name FROM tournaments WHERE id = %s",
                (tournament_id,),
            )
            row = cur.fetchone()
            if not row or not is_world_cup_tournament(str(row["name"])):
                return 0
            cur.execute(
                _WC_SUPPLEMENT_INSERT_FOR_TOURNAMENT_SQL,
                {"tournament_id": tournament_id},
            )
        else:
            cur.execute(_WC_SUPPLEMENT_INSERT_ALL_SQL)
        inserted = int(cur.rowcount)
    if inserted:
        log.info("wc_participation_supplement: %s rows", inserted)
    return inserted


def participation_row_from_parts(
    standing: dict[str, Any],
    tournament: dict[str, Any],
    rating_event: dict[str, Any] | None = None,
) -> dict[str, Any]:
    """Map joined source rows to one participation insert dict (unit-test helper)."""
    position = int(standing["position"])
    row: dict[str, Any] = {
        "player_id": int(standing["player_id"]),
        "tournament_id": int(standing["tournament_id"]),
        "event_date": tournament.get("event_date"),
        "event_chrono": tournament.get("chrono"),
        "tournament_name": tournament["name"],
        "is_cup": int(tournament.get("is_cup") or 0),
        "country": tournament.get("country"),
        "has_league": int(tournament.get("has_league") or 0),
        "has_cup": int(tournament.get("has_cup") or 0),
        "overall_position": position,
        "points": int(standing.get("points") or 0),
        "games": int(standing.get("games") or 0),
        "wins": int(standing.get("wins") or 0),
        "draws": int(standing.get("draws") or 0),
        "losses": int(standing.get("losses") or 0),
        "goals_for": int(standing.get("goals_for") or 0),
        "goals_against": int(standing.get("goals_against") or 0),
        "rating_before": None,
        "rating_delta": None,
        "rating_after": None,
        "games_in_event": 0,
        "finalized_at": None,
        "is_winner": 1 if position == 1 else 0,
        "wc_medal": "none",
    }
    if rating_event is not None:
        row["rating_before"] = rating_event.get("rating_before")
        row["rating_delta"] = rating_event.get("rating_delta")
        row["rating_after"] = rating_event.get("rating_after")
        row["games_in_event"] = int(rating_event.get("games_in_event") or 0)
        row["finalized_at"] = rating_event.get("finalized_at")
    return row


def _connect() -> pymysql.connections.Connection:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
        autocommit=False,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def clear_participation(conn: pymysql.connections.Connection, *, dry_run: bool = False) -> None:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_tournament_participation")
        n = int(cur.fetchone()["n"])
    log.info("clear_participation: %s existing rows", n)
    if dry_run:
        return
    with conn.cursor() as cur:
        cur.execute("DELETE FROM amiga_player_tournament_participation")
    conn.commit()


def rebuild_all_participation(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool = False,
) -> int:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_tournament_standings
            WHERE scope_type = 'overall' AND scope_key = ''
            """
        )
        source_rows = int(cur.fetchone()["n"])
    log.info("rebuild_all_participation: %s overall standing rows", source_rows)
    if dry_run:
        return source_rows
    clear_participation(conn, dry_run=False)
    with conn.cursor() as cur:
        cur.execute(_REBUILD_INSERT_ALL_SQL)
    _insert_wc_participation_supplement(conn)
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_tournament_participation")
        written = int(cur.fetchone()["n"])
    refresh_wc_medals(conn, dry_run=False)
    conn.commit()
    log.info("amiga_player_tournament_participation: %s rows", written)
    return written


def player_ids_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> list[int]:
    """Distinct players with at least one game in the tournament."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT DISTINCT player_id
            FROM (
                SELECT player_a_id AS player_id
                FROM amiga_games
                WHERE tournament_id = %s
                UNION ALL
                SELECT player_b_id AS player_id
                FROM amiga_games
                WHERE tournament_id = %s
            ) g
            ORDER BY player_id
            """,
            (tournament_id, tournament_id),
        )
        return [int(row["player_id"]) for row in cur.fetchall()]


def rebuild_participation_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
) -> int:
    """Delete + reinsert participation for one tournament (incremental path)."""
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_tournament_standings
            WHERE tournament_id = %s AND scope_type = 'overall' AND scope_key = ''
            """,
            (tournament_id,),
        )
        source_rows = int(cur.fetchone()["n"])
    log.info(
        "rebuild_participation_for_tournament: tournament_id=%s, %s overall rows",
        tournament_id,
        source_rows,
    )
    if dry_run:
        return source_rows
    with conn.cursor() as cur:
        cur.execute(
            "DELETE FROM amiga_player_tournament_participation WHERE tournament_id = %s",
            (tournament_id,),
        )
        cur.execute(
            _REBUILD_INSERT_FOR_TOURNAMENT_SQL,
            {"tournament_id": tournament_id},
        )
    _insert_wc_participation_supplement(conn, tournament_id=tournament_id)
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_tournament_participation
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        written = int(cur.fetchone()["n"])
    refresh_wc_medals(conn, tournament_id=tournament_id, dry_run=False)
    conn.commit()
    log.info(
        "amiga_player_tournament_participation tournament_id=%s: %s rows",
        tournament_id,
        written,
    )
    return written


_TOTALS_AGG_SELECT = """
SELECT
    p.player_id,
    COUNT(*) AS tournaments_played,
    SUM(p.is_winner) AS tournaments_won,
    SUM(CASE WHEN p.wc_medal = 'gold' THEN 1 ELSE 0 END) AS wc_gold,
    SUM(CASE WHEN p.wc_medal = 'silver' THEN 1 ELSE 0 END) AS wc_silver,
    SUM(CASE WHEN p.wc_medal = 'bronze' THEN 1 ELSE 0 END) AS wc_bronze,
    SUM(
        CASE
            WHEN p.is_cup = 1
             AND p.tournament_name NOT REGEXP '^World Cup[[:space:]]+[^[:space:]]'
             AND p.overall_position = 1
            THEN 1 ELSE 0
        END
    ) AS cup_gold,
    SUM(
        CASE
            WHEN p.is_cup = 1
             AND p.tournament_name NOT REGEXP '^World Cup[[:space:]]+[^[:space:]]'
             AND p.overall_position = 2
            THEN 1 ELSE 0
        END
    ) AS cup_silver,
    SUM(
        CASE
            WHEN p.is_cup = 1
             AND p.tournament_name NOT REGEXP '^World Cup[[:space:]]+[^[:space:]]'
             AND p.overall_position = 3
            THEN 1 ELSE 0
        END
    ) AS cup_bronze,
    SUM(CASE WHEN p.overall_position <= 3 THEN 1 ELSE 0 END) AS podiums,
    MAX(p.event_date) AS last_event_date,
    CAST(
        SUBSTRING_INDEX(
            GROUP_CONCAT(
                p.tournament_id
                ORDER BY p.event_chrono DESC, p.event_date DESC, p.tournament_id DESC
            ),
            ',',
            1
        ) AS UNSIGNED
    ) AS last_tournament_id
FROM amiga_player_tournament_participation p
WHERE __PLAYER_FILTER__
GROUP BY p.player_id
"""

_TOTALS_INSERT_PREFIX = """
INSERT INTO amiga_player_tournament_totals (
    player_id,
    tournaments_played,
    tournaments_won,
    wc_gold,
    wc_silver,
    wc_bronze,
    cup_gold,
    cup_silver,
    cup_bronze,
    podiums,
    last_event_date,
    last_tournament_id
)
"""

_TOTALS_REBUILD_SQL = _TOTALS_INSERT_PREFIX + _TOTALS_AGG_SELECT.replace(
    "__PLAYER_FILTER__",
    "1 = 1",
)


def _totals_insert_sql_for_players(player_ids: list[int]) -> tuple[str, list[int]]:
    placeholders = ", ".join(["%s"] * len(player_ids))
    sql = _TOTALS_INSERT_PREFIX + _TOTALS_AGG_SELECT.replace(
        "__PLAYER_FILTER__",
        f"p.player_id IN ({placeholders})",
    )
    return sql, list(player_ids)


def clear_participation_totals(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool = False,
) -> None:
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_tournament_totals")
        n = int(cur.fetchone()["n"])
    log.info("clear_participation_totals: %s existing rows", n)
    if dry_run:
        return
    with conn.cursor() as cur:
        cur.execute("DELETE FROM amiga_player_tournament_totals")
    conn.commit()


def rebuild_totals_for_players(
    conn: pymysql.connections.Connection,
    player_ids: list[int],
    *,
    dry_run: bool = False,
) -> int:
    """Re-aggregate career totals for the given players from participation rows."""
    unique_ids = sorted({int(player_id) for player_id in player_ids})
    if not unique_ids:
        return 0
    log.info("rebuild_totals_for_players: %s player(s)", len(unique_ids))
    if dry_run:
        return len(unique_ids)

    placeholders = ", ".join(["%s"] * len(unique_ids))
    insert_sql, insert_params = _totals_insert_sql_for_players(unique_ids)

    with conn.cursor() as cur:
        cur.execute(
            f"DELETE FROM amiga_player_tournament_totals WHERE player_id IN ({placeholders})",
            unique_ids,
        )
        cur.execute(insert_sql, insert_params)
        cur.execute(
            f"""
            DELETE t
            FROM amiga_player_tournament_totals t
            WHERE t.player_id IN ({placeholders})
              AND NOT EXISTS (
                  SELECT 1
                  FROM amiga_player_tournament_participation p
                  WHERE p.player_id = t.player_id
              )
            """,
            unique_ids,
        )
    conn.commit()
    log.info("amiga_player_tournament_totals: refreshed %s player(s)", len(unique_ids))
    return len(unique_ids)


def rebuild_participation_and_totals_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
) -> tuple[int, int]:
    """Incremental rebuild: one tournament participation + totals for players in that event."""
    player_ids = player_ids_for_tournament(conn, tournament_id)
    participation_rows = rebuild_participation_for_tournament(
        conn,
        tournament_id,
        dry_run=dry_run,
    )
    if dry_run:
        return participation_rows, len(player_ids)
    totals_players = rebuild_totals_for_players(conn, player_ids, dry_run=False)
    return participation_rows, totals_players


def rebuild_all_participation_totals(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool = False,
) -> int:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(DISTINCT player_id) AS n FROM amiga_player_tournament_participation"
        )
        source_players = int(cur.fetchone()["n"])
    log.info("rebuild_all_participation_totals: %s players with participation", source_players)
    if dry_run:
        return source_players
    clear_participation_totals(conn, dry_run=False)
    with conn.cursor() as cur:
        cur.execute(_TOTALS_REBUILD_SQL)
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_tournament_totals")
        written = int(cur.fetchone()["n"])
    conn.commit()
    log.info("amiga_player_tournament_totals: %s rows", written)
    return written


def refresh_tournament_participation_stack(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    skip_standings: bool = False,
    dry_run: bool = False,
) -> tuple[int, int]:
    """
    Live finalize hook: standings (+ catalog) then participation + totals for one event.

    Batch ``replay`` uses ``defer_heavy_derived`` and rebuilds globally instead.
    """
    if dry_run:
        player_ids = player_ids_for_tournament(conn, tournament_id)
        return len(player_ids), len(player_ids)

    if not skip_standings:
        from scripts.amiga.tournament_catalog_stats import refresh_catalog_stats_for_tournament
        from scripts.amiga.tournament_standings import rebuild_standings_for_tournament

        rebuild_standings_for_tournament(conn, tournament_id)
        refresh_catalog_stats_for_tournament(conn, tournament_id)

    return rebuild_participation_and_totals_for_tournament(conn, tournament_id, dry_run=False)


def run_participation_refresh_tournament(
    tournament_id: int,
    *,
    skip_standings: bool = False,
    dry_run: bool = False,
) -> tuple[int, int]:
    conn = _connect()
    try:
        return refresh_tournament_participation_stack(
            conn,
            tournament_id,
            skip_standings=skip_standings,
            dry_run=dry_run,
        )
    finally:
        conn.close()


def run_participation_rebuild(*, dry_run: bool = False) -> tuple[int, int]:
    conn = _connect()
    try:
        participation_rows = rebuild_all_participation(conn, dry_run=dry_run)
        totals_rows = rebuild_all_participation_totals(conn, dry_run=dry_run)
        return participation_rows, totals_rows
    finally:
        conn.close()
