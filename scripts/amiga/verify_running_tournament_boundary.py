#!/usr/bin/env python3
"""RTB oracle: live-ops unfinalized tournaments must not write official L3 rows."""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config


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


def verify_running_tournament_boundary(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []
    live_ops_filter = """
        t.source_id IS NULL
        AND (
          COALESCE(t.format_overrides, '') LIKE %s
          OR COALESCE(t.format_overrides, '') LIKE %s
        )
    """
    patterns = ("%fixtures%", "%tournament_builder%")

    with conn.cursor() as cur:
        cur.execute(
            f"""
            SELECT t.id, t.name, COUNT(g.id) AS game_count
            FROM tournaments t
            INNER JOIN amiga_games g ON g.tournament_id = t.id
            WHERE t.rating_finalized = 0 AND {live_ops_filter}
            GROUP BY t.id, t.name
            ORDER BY t.id
            """,
            patterns,
        )
        for row in cur.fetchall():
            errors.append(
                f"live-ops tournament {row['id']} ({row['name']!r}) has "
                f"{int(row['game_count'])} amiga_games row(s) while rating_finalized=0"
            )

        cur.execute(
            f"""
            SELECT f.id AS fixture_id, s.tournament_id
            FROM tournament_fixtures f
            INNER JOIN tournament_stages s ON s.id = f.stage_id
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE t.rating_finalized = 0 AND {live_ops_filter}
              AND f.status = 'played'
              AND (f.goals_a IS NULL OR f.goals_b IS NULL)
            ORDER BY f.id
            """,
            patterns,
        )
        for row in cur.fetchall():
            errors.append(
                f"fixture {row['fixture_id']} in tournament {row['tournament_id']} "
                "is played but missing running goals"
            )

        cur.execute(
            f"""
            SELECT DISTINCT st.tournament_id
            FROM amiga_tournament_standings st
            INNER JOIN tournaments t ON t.id = st.tournament_id
            WHERE t.rating_finalized = 0 AND {live_ops_filter}
            ORDER BY st.tournament_id
            """,
            patterns,
        )
        for row in cur.fetchall():
            errors.append(
                f"live-ops tournament {row['tournament_id']} has amiga_tournament_standings rows before official"
            )

    return errors


def main(argv: list[str] | None = None) -> int:
    _ = argv
    cfg = load_amiga_db_config()
    conn = _connect(cfg)
    try:
        errors = verify_running_tournament_boundary(conn)
    finally:
        conn.close()
    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1
    print("OK: running tournament boundary")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())