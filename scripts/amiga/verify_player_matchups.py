#!/usr/bin/env python3
"""Assert amiga_player_matchup_summary invariants (player universe contract §8)."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config

_SAMPLE_PAIR_LIMIT = 12


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


_PAIR_EXPECTED_SQL = """
SELECT
    COUNT(*) AS games,
    SUM(
        CASE
            WHEN player_a_id = %(player_id)s AND player_b_id = %(opponent_id)s AND goals_a > goals_b
            THEN 1
            WHEN player_b_id = %(player_id)s AND player_a_id = %(opponent_id)s AND goals_b > goals_a
            THEN 1
            ELSE 0
        END
    ) AS wins,
    SUM(
        CASE
            WHEN goals_a = goals_b THEN 1 ELSE 0
        END
    ) AS draws,
    SUM(
        CASE
            WHEN player_a_id = %(player_id)s AND player_b_id = %(opponent_id)s AND goals_a < goals_b
            THEN 1
            WHEN player_b_id = %(player_id)s AND player_a_id = %(opponent_id)s AND goals_b < goals_a
            THEN 1
            ELSE 0
        END
    ) AS losses,
    SUM(
        CASE
            WHEN player_a_id = %(player_id)s AND player_b_id = %(opponent_id)s THEN goals_a
            WHEN player_b_id = %(player_id)s AND player_a_id = %(opponent_id)s THEN goals_b
            ELSE 0
        END
    ) AS goals_for,
    SUM(
        CASE
            WHEN player_a_id = %(player_id)s AND player_b_id = %(opponent_id)s THEN goals_b
            WHEN player_b_id = %(player_id)s AND player_a_id = %(opponent_id)s THEN goals_a
            ELSE 0
        END
    ) AS goals_against
FROM amiga_games
WHERE (player_a_id = %(player_id)s AND player_b_id = %(opponent_id)s)
   OR (player_b_id = %(player_id)s AND player_a_id = %(opponent_id)s)
"""


def _int_field(row: dict, key: str) -> int:
    value = row.get(key)
    return int(value or 0)


def verify_player_matchups(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
        game_count = int(cur.fetchone()["n"])
        cur.execute("SELECT COALESCE(SUM(games), 0) AS n FROM amiga_player_matchup_summary")
        games_sum = int(cur.fetchone()["n"])
        expected_sum = game_count * 2
        if games_sum != expected_sum:
            errors.append(
                f"SUM(games) ({games_sum}) != 2 × COUNT(amiga_games) ({expected_sum})"
            )

        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_matchup_summary")
        summary_rows = int(cur.fetchone()["n"])
        if game_count > 0 and summary_rows == 0:
            errors.append("amiga_player_matchup_summary is empty but amiga_games has rows")

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_matchup_summary
            WHERE games != wins + draws + losses
            """
        )
        wdl_mismatch = int(cur.fetchone()["n"])
        if wdl_mismatch:
            cur.execute(
                """
                SELECT player_id, opponent_id, games, wins, draws, losses
                FROM amiga_player_matchup_summary
                WHERE games != wins + draws + losses
                LIMIT 5
                """
            )
            sample = cur.fetchall()
            row = sample[0]
            errors.append(
                f"rows where games != wins+draws+losses: {wdl_mismatch} "
                f"(first player_id={row['player_id']}, opponent_id={row['opponent_id']})"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM (
                SELECT player_a_id AS player_id, player_b_id AS opponent_id
                FROM amiga_games
                UNION
                SELECT player_b_id AS player_id, player_a_id AS opponent_id
                FROM amiga_games
            ) pairs
            LEFT JOIN amiga_player_matchup_summary m
                ON m.player_id = pairs.player_id
               AND m.opponent_id = pairs.opponent_id
            WHERE m.player_id IS NULL
            """
        )
        missing_pairs = int(cur.fetchone()["n"])
        if missing_pairs:
            cur.execute(
                """
                SELECT pairs.player_id, pairs.opponent_id
                FROM (
                    SELECT player_a_id AS player_id, player_b_id AS opponent_id
                    FROM amiga_games
                    UNION
                    SELECT player_b_id AS player_id, player_a_id AS opponent_id
                    FROM amiga_games
                ) pairs
                LEFT JOIN amiga_player_matchup_summary m
                    ON m.player_id = pairs.player_id
                   AND m.opponent_id = pairs.opponent_id
                WHERE m.player_id IS NULL
                LIMIT 5
                """
            )
            sample = cur.fetchall()
            row = sample[0]
            errors.append(
                f"game pairs missing summary row: {missing_pairs} "
                f"(first player_id={row['player_id']}, opponent_id={row['opponent_id']})"
            )

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_matchup_summary m
            WHERE NOT EXISTS (
                SELECT 1
                FROM amiga_games g
                WHERE (g.player_a_id = m.player_id AND g.player_b_id = m.opponent_id)
                   OR (g.player_b_id = m.player_id AND g.player_a_id = m.opponent_id)
            )
            """
        )
        orphan_rows = int(cur.fetchone()["n"])
        if orphan_rows:
            errors.append(f"summary rows with no games for pair: {orphan_rows}")

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_matchup_summary a
            INNER JOIN amiga_player_matchup_summary b
                ON a.player_id = b.opponent_id
               AND a.opponent_id = b.player_id
            WHERE a.games != b.games
               OR a.wins != b.losses
               OR a.draws != b.draws
               OR a.losses != b.wins
               OR a.goals_for != b.goals_against
               OR a.goals_against != b.goals_for
            """
        )
        mirror_mismatch = int(cur.fetchone()["n"])
        if mirror_mismatch:
            cur.execute(
                """
                SELECT a.player_id, a.opponent_id, a.games, b.games AS rev_games
                FROM amiga_player_matchup_summary a
                INNER JOIN amiga_player_matchup_summary b
                    ON a.player_id = b.opponent_id
                   AND a.opponent_id = b.player_id
                WHERE a.games != b.games
                   OR a.wins != b.losses
                   OR a.draws != b.draws
                   OR a.losses != b.wins
                   OR a.goals_for != b.goals_against
                   OR a.goals_against != b.goals_for
                LIMIT 5
                """
            )
            sample = cur.fetchall()
            row = sample[0]
            errors.append(
                f"reverse-pair mirror mismatches: {mirror_mismatch} "
                f"(first player_id={row['player_id']}, opponent_id={row['opponent_id']})"
            )

        cur.execute(
            """
            SELECT player_id, opponent_id, games, wins, draws, losses,
                   goals_for, goals_against
            FROM amiga_player_matchup_summary
            ORDER BY games DESC, player_id ASC, opponent_id ASC
            LIMIT %s
            """,
            (_SAMPLE_PAIR_LIMIT,),
        )
        sample_pairs = cur.fetchall()

    for row in sample_pairs:
        player_id = int(row["player_id"])
        opponent_id = int(row["opponent_id"])
        with conn.cursor() as cur:
            cur.execute(
                _PAIR_EXPECTED_SQL,
                {"player_id": player_id, "opponent_id": opponent_id},
            )
            expected = cur.fetchone()
        for field in ("games", "wins", "draws", "losses", "goals_for", "goals_against"):
            if _int_field(row, field) != _int_field(expected, field):
                errors.append(
                    f"spot-check mismatch player_id={player_id} opponent_id={opponent_id} "
                    f"field={field}: summary={_int_field(row, field)} "
                    f"raw_games={_int_field(expected, field)}"
                )
                break

    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
        game_count = int(cur.fetchone()["n"])
        cur.execute("SELECT COUNT(*) AS n FROM amiga_player_matchup_at_event")
        at_event_rows = int(cur.fetchone()["n"])
        if game_count > 0 and at_event_rows < 1:
            errors.append("amiga_player_matchup_at_event is empty but amiga_games has rows")

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_matchup_summary s
            INNER JOIN amiga_player_matchup_at_event e
              ON e.player_id = s.player_id
             AND e.opponent_id = s.opponent_id
            INNER JOIN (
                SELECT player_id, opponent_id, as_of_tournament_id AS tid
                FROM (
                    SELECT player_id, opponent_id, as_of_tournament_id,
                           ROW_NUMBER() OVER (
                               PARTITION BY player_id, opponent_id
                               ORDER BY event_date DESC, event_chrono DESC,
                                        as_of_tournament_id DESC
                           ) AS rn
                    FROM amiga_player_matchup_at_event
                ) ranked
                WHERE rn = 1
            ) latest
              ON latest.player_id = e.player_id
             AND latest.opponent_id = e.opponent_id
             AND latest.tid = e.as_of_tournament_id
            WHERE s.games <> e.games OR s.wins <> e.wins
               OR s.draws <> e.draws OR s.losses <> e.losses
               OR s.goals_for <> e.goals_for OR s.goals_against <> e.goals_against
            """
        )
        if int(cur.fetchone()["n"]):
            errors.append("summary differs from latest at-event row for at least one pair")

        cur.execute(
            """
            SELECT COUNT(*) AS n
            FROM amiga_player_event_snapshots s
            INNER JOIN (
                SELECT player_id, as_of_tournament_id, COUNT(*) AS pair_count
                FROM amiga_player_matchup_at_event
                GROUP BY player_id, as_of_tournament_id
            ) m
              ON m.player_id = s.player_id
             AND m.as_of_tournament_id = s.tournament_id
            WHERE s.DifferentOpponents <> m.pair_count
            """
        )
        if int(cur.fetchone()["n"]):
            errors.append(
                "snapshot DifferentOpponents != at-event pair count for at least one row"
            )

    return errors


def main() -> int:
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_player_matchups(conn)
    finally:
        conn.close()

    if errors:
        print(f"FAIL: {len(errors)} verify-player-matchups issue(s):", file=sys.stderr)
        for err in errors[:20]:
            print(f"  - {err}", file=sys.stderr)
        if len(errors) > 20:
            print(f"  ... and {len(errors) - 20} more", file=sys.stderr)
        return 1

    with _connect(cfg) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT COUNT(*) AS n FROM amiga_player_matchup_summary")
            summary_rows = int(cur.fetchone()["n"])
            cur.execute("SELECT COALESCE(SUM(games), 0) AS n FROM amiga_player_matchup_summary")
            games_sum = int(cur.fetchone()["n"])
            cur.execute("SELECT COUNT(*) AS n FROM amiga_player_matchup_at_event")
            at_event_rows = int(cur.fetchone()["n"])
            cur.execute("SELECT COUNT(*) AS n FROM amiga_games")
            game_count = int(cur.fetchone()["n"])

    print(
        f"OK: player matchups verified ({summary_rows} summary pairs, "
        f"{at_event_rows} at-event rows, SUM(games)={games_sum} = 2×{game_count})"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
