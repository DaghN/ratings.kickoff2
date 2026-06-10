"""Derived player x tournament participation (games roster + placement + rating context)."""

from __future__ import annotations

import logging
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.participation_placement import (
    derive_participation_positions,
    participation_is_winner,
)
from scripts.amiga.performance_rating import backfill_performance_ratings
from scripts.amiga.tournament_honours import refresh_wc_medals

log = logging.getLogger(__name__)

# Event result points: 3 per win, 1 per draw (all phases), from games rollup.
_EVENT_POINTS_SQL = "(pg.wins * 3 + pg.draws)"

# Per-player event totals from ground-truth games (participation volume stats).
_PLAYER_GAMES_ROLLUP_SQL = """
(
  SELECT
    tournament_id,
    player_id,
    SUM(games) AS games,
    SUM(wins) AS wins,
    SUM(draws) AS draws,
    SUM(losses) AS losses,
    SUM(goals_for) AS goals_for,
    SUM(goals_against) AS goals_against
  FROM (
    SELECT
      g.tournament_id,
      g.player_a_id AS player_id,
      COUNT(*) AS games,
      SUM(CASE WHEN g.goals_a > g.goals_b THEN 1 ELSE 0 END) AS wins,
      SUM(CASE WHEN g.goals_a = g.goals_b THEN 1 ELSE 0 END) AS draws,
      SUM(CASE WHEN g.goals_a < g.goals_b THEN 1 ELSE 0 END) AS losses,
      SUM(g.goals_a) AS goals_for,
      SUM(g.goals_b) AS goals_against
    FROM amiga_games g
    GROUP BY g.tournament_id, g.player_a_id
    UNION ALL
    SELECT
      g.tournament_id,
      g.player_b_id AS player_id,
      COUNT(*) AS games,
      SUM(CASE WHEN g.goals_b > g.goals_a THEN 1 ELSE 0 END) AS wins,
      SUM(CASE WHEN g.goals_b = g.goals_a THEN 1 ELSE 0 END) AS draws,
      SUM(CASE WHEN g.goals_b < g.goals_a THEN 1 ELSE 0 END) AS losses,
      SUM(g.goals_b) AS goals_for,
      SUM(g.goals_a) AS goals_against
    FROM amiga_games g
    GROUP BY g.tournament_id, g.player_b_id
  ) side
  GROUP BY tournament_id, player_id
) pg
"""

_PARTICIPATION_INSERT_SQL = """
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
    event_points,
    games,
    wins,
    draws,
    losses,
    goals_for,
    goals_against,
    rating_before,
    rating_delta,
    rating_after,
    performance_rating,
    games_in_event,
    finalized_at,
    is_winner,
    wc_medal
) VALUES (
    %(player_id)s,
    %(tournament_id)s,
    %(event_date)s,
    %(event_chrono)s,
    %(tournament_name)s,
    %(is_cup)s,
    %(country)s,
    %(has_league)s,
    %(has_cup)s,
    %(overall_position)s,
    %(event_points)s,
    %(games)s,
    %(wins)s,
    %(draws)s,
    %(losses)s,
    %(goals_for)s,
    %(goals_against)s,
    %(rating_before)s,
    %(rating_delta)s,
    %(rating_after)s,
    %(performance_rating)s,
    %(games_in_event)s,
    %(finalized_at)s,
    %(is_winner)s,
    %(wc_medal)s
)
"""


def participation_row_from_parts(
    standing: dict[str, Any],
    tournament: dict[str, Any],
    rating_event: dict[str, Any] | None = None,
    *,
    games_rollup: dict[str, Any] | None = None,
) -> dict[str, Any]:
    """Map joined source rows to one participation insert dict (unit-test helper)."""
    rollup = games_rollup if games_rollup is not None else standing
    position = int(standing.get("position") or standing.get("overall_position") or 0)
    wins = int(rollup.get("wins") or 0)
    draws = int(rollup.get("draws") or 0)
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
        "games": int(rollup.get("games") or 0),
        "wins": wins,
        "draws": draws,
        "losses": int(rollup.get("losses") or 0),
        "event_points": wins * 3 + draws,
        "goals_for": int(rollup.get("goals_for") or 0),
        "goals_against": int(rollup.get("goals_against") or 0),
        "rating_before": None,
        "rating_delta": None,
        "rating_after": None,
        "performance_rating": None,
        "games_in_event": 0,
        "finalized_at": None,
        "is_winner": 1 if position == 1 else 0,
        "wc_medal": "none",
    }
    if rating_event is not None:
        row["rating_before"] = rating_event.get("rating_before")
        row["rating_delta"] = rating_event.get("rating_delta")
        row["rating_after"] = rating_event.get("rating_after")
        row["performance_rating"] = rating_event.get("performance_rating")
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


def _load_tournament(conn: pymysql.connections.Connection, tournament_id: int) -> dict[str, Any] | None:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT id, name, event_date, chrono, is_cup, country, has_league, has_cup
            FROM tournaments
            WHERE id = %s
            """,
            (tournament_id,),
        )
        return cur.fetchone()


def _load_standing_rows(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> list[dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT scope_type, scope_key, player_id, position
            FROM amiga_tournament_standings
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        return list(cur.fetchall())


def _load_games_rollups(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> dict[int, dict[str, Any]]:
    sql = f"""
        SELECT pg.player_id, pg.games, pg.wins, pg.draws, pg.losses,
               pg.goals_for, pg.goals_against
        FROM {_PLAYER_GAMES_ROLLUP_SQL}
        WHERE pg.tournament_id = %s
    """
    with conn.cursor() as cur:
        cur.execute(sql, (tournament_id,))
        rows = cur.fetchall()
    return {int(row["player_id"]): row for row in rows}


def _load_rating_events(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> dict[int, dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT player_id, rating_before, rating_delta, rating_after,
                   performance_rating, games_in_event, finalized_at
            FROM amiga_rating_events
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        rows = cur.fetchall()
    return {int(row["player_id"]): row for row in rows}


def build_participation_rows_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> list[dict[str, Any]]:
    """Build participation insert dicts from games roster + derived placement."""
    tournament = _load_tournament(conn, tournament_id)
    if tournament is None:
        return []

    rollups = _load_games_rollups(conn, tournament_id)
    if not rollups:
        return []

    standing_rows = _load_standing_rows(conn, tournament_id)
    rating_events = _load_rating_events(conn, tournament_id)
    player_ids = sorted(rollups.keys())
    positions = derive_participation_positions(
        standing_rows,
        tournament_name=str(tournament["name"]),
        player_ids=player_ids,
    )

    rows: list[dict[str, Any]] = []
    for player_id in player_ids:
        rollup = rollups[player_id]
        overall_position = int(positions.get(player_id, 0))
        rating_event = rating_events.get(player_id)
        row = participation_row_from_parts(
            {
                "player_id": player_id,
                "tournament_id": tournament_id,
                "position": overall_position,
            },
            tournament,
            rating_event,
            games_rollup=rollup,
        )
        row["is_winner"] = (
            1
            if participation_is_winner(
                tournament_name=str(tournament["name"]),
                overall_position=overall_position,
                wc_medal="none",
            )
            else 0
        )
        rows.append(row)
    return rows


def _insert_participation_rows(
    conn: pymysql.connections.Connection,
    rows: list[dict[str, Any]],
) -> int:
    if not rows:
        return 0
    with conn.cursor() as cur:
        cur.executemany(_PARTICIPATION_INSERT_SQL, rows)
    return len(rows)


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


def _tournament_ids_with_games(conn: pymysql.connections.Connection) -> list[int]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT DISTINCT tournament_id
            FROM amiga_games
            WHERE tournament_id IS NOT NULL
            ORDER BY tournament_id
            """
        )
        return [int(row["tournament_id"]) for row in cur.fetchall()]


def rebuild_all_participation(
    conn: pymysql.connections.Connection,
    *,
    dry_run: bool = False,
) -> int:
    tournament_ids = _tournament_ids_with_games(conn)
    log.info("rebuild_all_participation: %s tournaments with games", len(tournament_ids))
    if dry_run:
        return len(tournament_ids)
    clear_participation(conn, dry_run=False)
    written = 0
    for tournament_id in tournament_ids:
        rows = build_participation_rows_for_tournament(conn, tournament_id)
        written += _insert_participation_rows(conn, rows)
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
    player_ids = player_ids_for_tournament(conn, tournament_id)
    log.info(
        "rebuild_participation_for_tournament: tournament_id=%s, %s player(s) with games",
        tournament_id,
        len(player_ids),
    )
    if dry_run:
        return len(player_ids)
    with conn.cursor() as cur:
        cur.execute(
            "DELETE FROM amiga_player_tournament_participation WHERE tournament_id = %s",
            (tournament_id,),
        )
    rows = build_participation_rows_for_tournament(conn, tournament_id)
    written = _insert_participation_rows(conn, rows)
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
        if not dry_run:
            backfill_performance_ratings(conn)
        participation_rows = rebuild_all_participation(conn, dry_run=dry_run)
        totals_rows = rebuild_all_participation_totals(conn, dry_run=dry_run)
        return participation_rows, totals_rows
    finally:
        conn.close()
