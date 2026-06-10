#!/usr/bin/env python3
"""Assert player tournament participation + totals invariants (contract §8)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.player_tournament_participation import _PLAYER_GAMES_ROLLUP_SQL

_TOLERANCE = 1e-5
_SAMPLE_LIMIT = 5


def _connect(cfg) -> pymysql.connections.Connection:
    return pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=DictCursor,
    )


def verify_player_participation(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []

    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_tournament_participation p
            WHERE NOT EXISTS (
                SELECT 1
                FROM amiga_games g
                WHERE g.tournament_id = p.tournament_id
                  AND (g.player_a_id = p.player_id OR g.player_b_id = p.player_id)
            )
            """
        )
        no_games = int(cur.fetchone()["n"])
        if no_games:
            cur.execute(
                """
                SELECT p.player_id, p.tournament_id
                FROM amiga_player_tournament_participation p
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM amiga_games g
                    WHERE g.tournament_id = p.tournament_id
                      AND (g.player_a_id = p.player_id OR g.player_b_id = p.player_id)
                )
                LIMIT %s
                """,
                (_SAMPLE_LIMIT,),
            )
            sample = cur.fetchall()
            errors.append(
                f"participation rows without >=1 game: {no_games} "
                f"(first player_id={sample[0]['player_id']}, "
                f"tournament_id={sample[0]['tournament_id']})"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_tournament_standings s
            WHERE s.scope_type = 'overall'
              AND s.scope_key = ''
              AND NOT EXISTS (
                  SELECT 1
                  FROM amiga_player_tournament_participation p
                  WHERE p.player_id = s.player_id
                    AND p.tournament_id = s.tournament_id
              )
            """
        )
        missing_participation = int(cur.fetchone()["n"])
        if missing_participation:
            cur.execute(
                """
                SELECT s.player_id, s.tournament_id
                FROM amiga_tournament_standings s
                WHERE s.scope_type = 'overall'
                  AND s.scope_key = ''
                  AND NOT EXISTS (
                      SELECT 1
                      FROM amiga_player_tournament_participation p
                      WHERE p.player_id = s.player_id
                        AND p.tournament_id = s.tournament_id
                  )
                LIMIT %s
                """,
                (_SAMPLE_LIMIT,),
            )
            sample = cur.fetchall()
            errors.append(
                f"overall standings missing participation: {missing_participation} "
                f"(first player_id={sample[0]['player_id']}, "
                f"tournament_id={sample[0]['tournament_id']})"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_tournament_participation p
            WHERE p.event_points != (p.wins * 3 + p.draws)
            """
        )
        event_points_mismatch = int(cur.fetchone()["n"])
        if event_points_mismatch:
            cur.execute(
                """
                SELECT p.player_id, p.tournament_id, p.event_points, p.wins, p.draws
                FROM amiga_player_tournament_participation p
                WHERE p.event_points != (p.wins * 3 + p.draws)
                LIMIT %s
                """,
                (_SAMPLE_LIMIT,),
            )
            sample = cur.fetchall()
            row = sample[0]
            expected = int(row["wins"]) * 3 + int(row["draws"])
            errors.append(
                f"participation event_points != wins*3+draws: {event_points_mismatch} "
                f"(first player_id={row['player_id']}, tournament_id={row['tournament_id']}, "
                f"event_points={row['event_points']}, expected={expected})"
            )

        cur.execute(
            f"""
            SELECT COUNT(*) AS n
            FROM amiga_player_tournament_participation p
            INNER JOIN {_PLAYER_GAMES_ROLLUP_SQL}
                ON pg.tournament_id = p.tournament_id AND pg.player_id = p.player_id
            WHERE p.games != pg.games
               OR p.wins != pg.wins
               OR p.draws != pg.draws
               OR p.losses != pg.losses
               OR p.goals_for != pg.goals_for
               OR p.goals_against != pg.goals_against
            """
        )
        games_rollup_mismatch = int(cur.fetchone()["n"])
        if games_rollup_mismatch:
            cur.execute(
                f"""
                SELECT p.player_id, p.tournament_id, p.games, pg.games AS expected_games
                FROM amiga_player_tournament_participation p
                INNER JOIN {_PLAYER_GAMES_ROLLUP_SQL}
                    ON pg.tournament_id = p.tournament_id AND pg.player_id = p.player_id
                WHERE p.games != pg.games
                   OR p.wins != pg.wins
                   OR p.draws != pg.draws
                   OR p.losses != pg.losses
                   OR p.goals_for != pg.goals_for
                   OR p.goals_against != pg.goals_against
                LIMIT %s
                """,
                (_SAMPLE_LIMIT,),
            )
            sample = cur.fetchall()
            row = sample[0]
            errors.append(
                f"participation volume stats != amiga_games rollup: {games_rollup_mismatch} "
                f"(first player_id={row['player_id']}, tournament_id={row['tournament_id']}, "
                f"games={row['games']}, expected={row['expected_games']})"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_tournament_participation p
            INNER JOIN amiga_rating_events e
                ON e.tournament_id = p.tournament_id
               AND e.player_id = p.player_id
            WHERE ABS(p.rating_before - e.rating_before) > %s
               OR ABS(p.rating_delta - e.rating_delta) > %s
               OR ABS(p.rating_after - e.rating_after) > %s
               OR p.games != e.games_in_event
               OR p.games_in_event != e.games_in_event
               OR p.finalized_at IS NULL
               OR e.finalized_at IS NULL
               OR p.finalized_at != e.finalized_at
            """,
            (_TOLERANCE, _TOLERANCE, _TOLERANCE),
        )
        rating_mismatch = int(cur.fetchone()["n"])
        if rating_mismatch:
            cur.execute(
                """
                SELECT p.player_id, p.tournament_id
                FROM amiga_player_tournament_participation p
                INNER JOIN amiga_rating_events e
                    ON e.tournament_id = p.tournament_id
                   AND e.player_id = p.player_id
                WHERE ABS(p.rating_before - e.rating_before) > %s
                   OR ABS(p.rating_delta - e.rating_delta) > %s
                   OR ABS(p.rating_after - e.rating_after) > %s
                   OR p.games != e.games_in_event
                   OR p.games_in_event != e.games_in_event
                   OR p.finalized_at IS NULL
                   OR e.finalized_at IS NULL
                   OR p.finalized_at != e.finalized_at
                LIMIT %s
                """,
                (_TOLERANCE, _TOLERANCE, _TOLERANCE, _SAMPLE_LIMIT),
            )
            sample = cur.fetchall()
            errors.append(
                f"participation rating columns != amiga_rating_events: {rating_mismatch} "
                f"(first player_id={sample[0]['player_id']}, "
                f"tournament_id={sample[0]['tournament_id']})"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_tournament_participation p
            LEFT JOIN amiga_rating_events e
                ON e.tournament_id = p.tournament_id
               AND e.player_id = p.player_id
            WHERE e.id IS NULL
              AND (
                  p.rating_before IS NOT NULL
                  OR p.rating_delta IS NOT NULL
                  OR p.rating_after IS NOT NULL
                  OR p.games_in_event != 0
                  OR p.finalized_at IS NOT NULL
              )
            """
        )
        orphan_rating = int(cur.fetchone()["n"])
        if orphan_rating:
            errors.append(
                f"participation rows with rating fields but no rating_event: {orphan_rating}"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_tournament_totals t
            INNER JOIN (
                SELECT player_id, COUNT(*) AS participation_count
                FROM amiga_player_tournament_participation
                GROUP BY player_id
            ) p ON p.player_id = t.player_id
            WHERE t.tournaments_played != p.participation_count
            """
        )
        played_mismatch = int(cur.fetchone()["n"])
        if played_mismatch:
            cur.execute(
                """
                SELECT t.player_id, t.tournaments_played, p.participation_count
                FROM amiga_player_tournament_totals t
                INNER JOIN (
                    SELECT player_id, COUNT(*) AS participation_count
                    FROM amiga_player_tournament_participation
                    GROUP BY player_id
                ) p ON p.player_id = t.player_id
                WHERE t.tournaments_played != p.participation_count
                LIMIT %s
                """,
                (_SAMPLE_LIMIT,),
            )
            sample = cur.fetchall()
            row = sample[0]
            errors.append(
                f"totals.tournaments_played != participation count: {played_mismatch} players "
                f"(first player_id={row['player_id']}, "
                f"totals={row['tournaments_played']}, participation={row['participation_count']})"
            )

        cur.execute(
            """
            SELECT COUNT(DISTINCT player_id) AS n
            FROM amiga_player_tournament_participation
            """
        )
        players_with_participation = int(cur.fetchone()["n"])

        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_tournament_totals")
        totals_rows = int(cur.fetchone()["n"])
        if totals_rows != players_with_participation:
            errors.append(
                f"totals row count ({totals_rows}) != players with participation "
                f"({players_with_participation})"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_tournament_participation p
            LEFT JOIN amiga_player_tournament_totals t ON t.player_id = p.player_id
            WHERE t.player_id IS NULL
            """
        )
        participation_without_totals = int(cur.fetchone()["n"])
        if participation_without_totals:
            errors.append(
                f"participation rows for players missing totals row: "
                f"{participation_without_totals} rows"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_tournament_totals t
            WHERE NOT EXISTS (
                SELECT 1
                FROM amiga_player_tournament_participation p
                WHERE p.player_id = t.player_id
            )
            """
        )
        totals_without_participation = int(cur.fetchone()["n"])
        if totals_without_participation:
            errors.append(
                f"totals rows without any participation: {totals_without_participation}"
            )

        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_tournament_participation")
        participation_rows = int(cur.fetchone()["n"])
        cur.execute("SELECT COALESCE(SUM(tournaments_played), 0) AS n FROM amiga_player_tournament_totals")
        sum_played = int(cur.fetchone()["n"])
        if participation_rows and sum_played != participation_rows:
            errors.append(
                f"SUM(tournaments_played) ({sum_played}) != participation rows ({participation_rows})"
            )

    return errors


def main() -> int:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_player_participation(conn)
    finally:
        conn.close()

    if errors:
        print(f"FAIL: {len(errors)} verify-player-participation issue(s):", file=sys.stderr)
        for err in errors[:20]:
            print(f"  - {err}", file=sys.stderr)
        if len(errors) > 20:
            print(f"  ... and {len(errors) - 20} more", file=sys.stderr)
        return 1

    with _connect(cfg) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM amiga_player_tournament_participation")
            participation_rows = int(cur.fetchone()["n"])
            cur.execute("SELECT COUNT(*) AS n FROM amiga_player_tournament_totals")
            totals_rows = int(cur.fetchone()["n"])

    print(
        f"OK: player participation verified ({participation_rows} participation rows, "
        f"{totals_rows} player totals)"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
