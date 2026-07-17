"""Promote running fixture scores into amiga_games (RTB Make official)."""

from __future__ import annotations

import json
import logging
from datetime import datetime, timedelta, timezone
from typing import Any

import pymysql

from scripts.amiga.tournament_fixtures import (
    GENERATED_FIXTURE_PREFIXES,
    _next_append_only_game_date,
    _next_live_source_scores_id,
    next_tournament_chrono,
)

log = logging.getLogger(__name__)

__all__ = [
    "PromoteSkipped",
    "promote_running_tournament",
    "running_tournament_games",
]


class PromoteSkipped(RuntimeError):
    pass


def _is_live_ops_generated(row: dict[str, Any]) -> bool:
    if row.get("source_id") is not None:
        return False
    overrides = json.loads(str(row.get("format_overrides") or "{}"))
    generated_by = str(overrides.get("generated_by") or "")
    return any(generated_by.startswith(prefix) for prefix in GENERATED_FIXTURE_PREFIXES)


def running_tournament_games(conn: pymysql.connections.Connection, tournament_id: int) -> list[dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT f.id AS fixture_id, f.player_a_id, f.player_b_id, f.goals_a, f.goals_b,
                   f.extra, f.goals_et_a, f.goals_et_b, f.pens_a, f.pens_b,
                   f.phase_label AS phase, f.phase_label AS fixture_phase_label,
                   f.leg_no, s.id AS stage_id, s.tournament_id, s.stage_key, s.name AS stage_name,
                   s.stage_type, s.track_key
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s AND f.status = 'played'
              AND f.player_a_id IS NOT NULL AND f.player_b_id IS NOT NULL
              AND f.goals_a IS NOT NULL AND f.goals_b IS NOT NULL
            ORDER BY s.sequence_no ASC, s.id ASC, f.leg_no ASC, f.id ASC
            """,
            (tournament_id,),
        )
        rows = list(cur.fetchall())
    games: list[dict[str, Any]] = []
    for row in rows:
        games.append(
            {
                "tournament_id": int(row["tournament_id"]),
                "fixture_id": int(row["fixture_id"]),
                "player_a_id": int(row["player_a_id"]),
                "player_b_id": int(row["player_b_id"]),
                "goals_a": int(row["goals_a"]),
                "goals_b": int(row["goals_b"]),
                "extra": row["extra"],
                "goals_et_a": row["goals_et_a"],
                "goals_et_b": row["goals_et_b"],
                "pens_a": row["pens_a"],
                "pens_b": row["pens_b"],
                "phase": row["phase"],
                "fixture_phase_label": row["fixture_phase_label"],
                "leg_no": int(row["leg_no"]),
                "stage_id": int(row["stage_id"]),
                "stage_key": row["stage_key"],
                "stage_name": row["stage_name"],
                "stage_type": row["stage_type"],
                "track_key": row["track_key"],
            }
        )
    return games


def promote_running_tournament(
    conn: pymysql.connections.Connection,
    tournament_id: int,
    *,
    dry_run: bool = False,
) -> dict[str, Any]:
    with conn.cursor() as cur:
        cur.execute(
            "SELECT id, source_id, format_overrides, rating_finalized, lifecycle_status, chrono, event_date "
            "FROM tournaments WHERE id = %s LIMIT 1",
            (tournament_id,),
        )
        tournament = cur.fetchone()
    if tournament is None:
        raise ValueError(f"tournament_id={tournament_id} not found")
    if not _is_live_ops_generated(tournament):
        raise ValueError(f"tournament_id={tournament_id} is not live-ops generated")
    if int(tournament["rating_finalized"]) == 1:
        return {
            "tournament_id": tournament_id,
            "promoted": 0,
            "game_ids": [],
            "skipped": True,
            "skip_reason": "already_finalized",
        }

    if tournament.get("chrono") is None and tournament.get("event_date") is not None:
        next_chrono = next_tournament_chrono(
            conn,
            tournament["event_date"],
            exclude_tournament_id=tournament_id,
        )
        with conn.cursor() as cur:
            cur.execute(
                "UPDATE tournaments SET chrono = %s WHERE id = %s",
                (next_chrono, tournament_id),
            )

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s", (tournament_id,))
        if int(cur.fetchone()["n"]) > 0:
            return {
                "tournament_id": tournament_id,
                "promoted": 0,
                "game_ids": [],
                "skipped": True,
                "skip_reason": "games_already_exist",
            }
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            WHERE s.tournament_id = %s AND f.status = 'scheduled'
            """,
            (tournament_id,),
        )
        scheduled = int(cur.fetchone()["n"])
        if scheduled > 0:
            cur.execute(
                """
                UPDATE tournament_fixtures f
                INNER JOIN tournament_stages s ON s.id = f.stage_id
                SET f.status = 'void'
                WHERE s.tournament_id = %s AND f.status = 'scheduled'
                """,
                (tournament_id,),
            )
            log.info(
                "promote_running_tournament: voided %s scheduled fixture(s) for tournament_id=%s dry_run=%s",
                scheduled,
                tournament_id,
                dry_run,
            )
            if not dry_run:
                conn.commit()
            # dry_run: leave void UPDATE in the open transaction; rollback with promote dry_run below

    fixtures = running_tournament_games(conn, tournament_id)
    if not fixtures:
        raise ValueError(f"tournament_id={tournament_id} has no played fixtures to promote")

    game_ids: list[int] = []
    try:
        with conn.cursor() as cur:
            for fixture in fixtures:
                source_scores_id = _next_live_source_scores_id(conn)
                game_date = _next_append_only_game_date(conn)
                cur.execute(
                    """
                    INSERT INTO amiga_games
                      (source_scores_id, game_date, player_a_id, player_b_id, tournament_id, fixture_id,
                       phase, goals_a, goals_b, extra, goals_et_a, goals_et_b, pens_a, pens_b)
                    VALUES
                      (%(source_scores_id)s, %(game_date)s, %(player_a_id)s, %(player_b_id)s,
                       %(tournament_id)s, %(fixture_id)s, %(phase)s, %(goals_a)s, %(goals_b)s, %(extra)s,
                       %(goals_et_a)s, %(goals_et_b)s, %(pens_a)s, %(pens_b)s)
                    """,
                    {
                        "source_scores_id": source_scores_id,
                        "game_date": game_date.strftime("%Y-%m-%d %H:%M:%S"),
                        "player_a_id": int(fixture["player_a_id"]),
                        "player_b_id": int(fixture["player_b_id"]),
                        "tournament_id": tournament_id,
                        "fixture_id": int(fixture["fixture_id"]),
                        "phase": fixture["phase"],
                        "goals_a": int(fixture["goals_a"]),
                        "goals_b": int(fixture["goals_b"]),
                        "extra": fixture["extra"].strip()
                        if fixture.get("extra") and str(fixture["extra"]).strip()
                        else None,
                        "goals_et_a": fixture.get("goals_et_a"),
                        "goals_et_b": fixture.get("goals_et_b"),
                        "pens_a": fixture.get("pens_a"),
                        "pens_b": fixture.get("pens_b"),
                    },
                )
                game_ids.append(int(cur.lastrowid))
        if dry_run:
            conn.rollback()
        else:
            conn.commit()
    except Exception:
        conn.rollback()
        raise

    log.info(
        "promote_running_tournament: tournament_id=%s promoted=%s dry_run=%s",
        tournament_id,
        len(game_ids),
        dry_run,
    )
    return {
        "tournament_id": tournament_id,
        "promoted": len(game_ids),
        "game_ids": game_ids,
        "skipped": False,
        "skip_reason": None,
    }