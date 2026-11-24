"""Player x tournament participation rows (games, placement, rating context).

Finish builds them into the event snapshots (``finalize_tournament``); the old tables
``amiga_player_tournament_participation`` and ``_totals`` are retired (amiga-data-contract.md).
"""

from __future__ import annotations

import logging
from decimal import ROUND_HALF_UP, Decimal
from typing import Any

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.perfect_event import is_perfect_event_from_rollup
from scripts.amiga.participation_placement import (
    derive_best_knockout_phase,
    derive_event_finish_position,
    participation_is_winner,
)

log = logging.getLogger(__name__)


def participation_avg_goals_per_game(goals: int, games: int) -> float | None:
    """Goals per game in event (4 d.p.; MySQL ROUND half-up for verify parity)."""
    if games <= 0:
        return None
    value = (Decimal(goals) / Decimal(games)).quantize(Decimal("0.0001"), rounding=ROUND_HALF_UP)
    return float(value)

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


def participation_row_from_parts(
    standing: dict[str, Any],
    tournament: dict[str, Any],
    rating_event: dict[str, Any] | None = None,
    *,
    games_rollup: dict[str, Any] | None = None,
) -> dict[str, Any]:
    """Map joined source rows to one participation insert dict (unit-test helper)."""
    rollup = games_rollup if games_rollup is not None else standing
    event_finish_position = standing.get("event_finish_position")
    if event_finish_position is not None:
        event_finish_position = int(event_finish_position)
    elif standing.get("position") is not None:
        event_finish_position = int(standing["position"])
    best_knockout_phase = standing.get("best_knockout_phase")
    if best_knockout_phase is not None:
        best_knockout_phase = str(best_knockout_phase)
    wins = int(rollup.get("wins") or 0)
    draws = int(rollup.get("draws") or 0)
    games_count = int(rollup.get("games") or 0)
    goals_for = int(rollup.get("goals_for") or 0)
    goals_against = int(rollup.get("goals_against") or 0)
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
        "is_world_cup": int(tournament.get("is_world_cup") or 0),
        "event_finish_position": event_finish_position,
        "best_knockout_phase": best_knockout_phase,
        "games": games_count,
        "wins": wins,
        "draws": draws,
        "losses": int(rollup.get("losses") or 0),
        "event_points": wins * 3 + draws,
        "goals_for": goals_for,
        "goals_against": goals_against,
        "avg_goals_for": participation_avg_goals_per_game(goals_for, games_count),
        "avg_goals_against": participation_avg_goals_per_game(goals_against, games_count),
        "rating_before": None,
        "rating_delta": None,
        "rating_after": None,
        "performance_rating": None,
        "games_in_event": 0,
        "finalized_at": None,
        "is_winner": (
            1
            if participation_is_winner(
                tournament_name=str(tournament["name"]),
                event_finish_position=event_finish_position,
            )
            else 0
        ),
        "is_perfect_event": (
            1
            if is_perfect_event_from_rollup(games_count, wins, draws, int(rollup.get("losses") or 0))
            else 0
        ),
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
            SELECT id, name, event_date, chrono, is_cup, country, has_league, has_cup, is_world_cup
            FROM tournaments
            WHERE id = %s
            """,
            (tournament_id,),
        )
        return cur.fetchone()


def _load_finish_overrides(
    conn: pymysql.connections.Connection,
    tournament_id: int,
) -> dict[int, int]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT player_id, event_finish_position
            FROM amiga_tournament_finish_override
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        rows = cur.fetchall()
    return {int(row["player_id"]): int(row["event_finish_position"]) for row in rows}


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
            FROM amiga_player_event_snapshots
            WHERE tournament_id = %s
            """,
            (tournament_id,),
        )
        rows = cur.fetchall()
    return {int(row["player_id"]): row for row in rows}


def build_participation_rows_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    rating_events_by_player: dict[int, dict[str, Any]] | None = None,
) -> list[dict[str, Any]]:
    """Build participation-shaped dicts from games roster + derived placement."""
    tournament = _load_tournament(conn, tournament_id)
    if tournament is None:
        return []

    rollups = _load_games_rollups(conn, tournament_id)
    if not rollups:
        return []

    standing_rows = _load_standing_rows(conn, tournament_id)
    finish_overrides = _load_finish_overrides(conn, tournament_id)
    rating_events = (
        rating_events_by_player
        if rating_events_by_player is not None
        else _load_rating_events(conn, tournament_id)
    )
    player_ids = sorted(rollups.keys())
    tournament_name = str(tournament["name"])
    event_finishes = derive_event_finish_position(
        standing_rows,
        tournament_name=tournament_name,
        has_league=bool(tournament.get("has_league")),
        has_cup=bool(tournament.get("has_cup")),
        is_world_cup=bool(int(tournament.get("is_world_cup") or 0)),
        player_ids=player_ids,
        overrides=finish_overrides or None,
    )

    rows: list[dict[str, Any]] = []
    for player_id in player_ids:
        rollup = rollups[player_id]
        event_finish_position = event_finishes.get(player_id)
        rating_event = rating_events.get(player_id)
        row = participation_row_from_parts(
            {
                "player_id": player_id,
                "tournament_id": tournament_id,
                "event_finish_position": event_finish_position,
                "best_knockout_phase": derive_best_knockout_phase(
                    standing_rows,
                    player_id,
                ),
            },
            tournament,
            rating_event,
            games_rollup=rollup,
        )
        rows.append(row)
    return rows


def refresh_event_finish_snapshots_for_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
) -> int:
    """
    Rewrite ``event_finish_position`` + ``is_winner`` on existing event snapshots.

    Uses ``derive_event_finish_position`` (tiers A–E incl. ``amiga_tournament_finish_override``).
    For already-finalized tournaments when L3 overrides change without full re-finalize.
    """
    participation_rows = build_participation_rows_for_tournament(conn, tournament_id)
    if not participation_rows:
        return 0
    if dry_run:
        return len(participation_rows)

    updated = 0
    with conn.cursor() as cur:
        for row in participation_rows:
            player_id = int(row["player_id"])
            finish = row.get("event_finish_position")
            is_winner = 1 if finish == 1 else 0
            cur.execute(
                """
                UPDATE amiga_player_event_snapshots
                SET event_finish_position = %s,
                    is_winner = %s
                WHERE tournament_id = %s AND player_id = %s
                """,
                (finish, is_winner, tournament_id, player_id),
            )
            updated += int(cur.rowcount)
    conn.commit()
    log.info(
        "refresh_event_finish_snapshots_for_tournament: tournament_id=%s rows=%s updated=%s",
        tournament_id,
        len(participation_rows),
        updated,
    )
    return updated


def run_refresh_event_finish_snapshots(tournament_id: int, *, dry_run: bool = False) -> int:
    conn = _connect()
    try:
        return refresh_event_finish_snapshots_for_tournament(
            conn, tournament_id, dry_run=dry_run
        )
    finally:
        conn.close()

