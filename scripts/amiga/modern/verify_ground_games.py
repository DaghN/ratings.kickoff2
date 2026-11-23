"""Three cheap checks on the game results for simul (copied from verify_rating_events, MG11).

The rest of verify-rating-events and all of verify-player-matchups are covered by the recount
(docs/amiga-recount-policy.md), so simul runs these three instead of those two slow checks.
"""

from __future__ import annotations

import sys

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config

#: Live-ops tournaments keep their scores in fixtures until Make official.
_RUNNING_FILTER = """
    t.source_id IS NULL
    AND (COALESCE(t.format_overrides, '') LIKE %s OR COALESCE(t.format_overrides, '') LIKE %s)
"""
_RUNNING_ARGS = ("%fixtures%", "%tournament_builder%")


def verify_ground_games(conn: pymysql.connections.Connection) -> list[str]:
    errors: list[str] = []
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id IS NULL")
        n = int(cur.fetchone()["n"])
        if n:
            errors.append(f"amiga_games with NULL tournament_id: {n} (expected 0)")

        cur.execute(
            f"""
            SELECT COUNT(*) AS n FROM tournaments t
            WHERE t.rating_finalized = 0
              AND EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id)
              AND NOT ({_RUNNING_FILTER})
            """,
            _RUNNING_ARGS,
        )
        n = int(cur.fetchone()["n"])
        if n:
            errors.append(f"tournaments with games but rating_finalized=0: {n} (expected 0)")

        cur.execute(
            f"""
            SELECT COUNT(*) AS n FROM tournaments t
            WHERE t.rating_finalized = 0
              AND ({_RUNNING_FILTER})
              AND EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id)
            """,
            _RUNNING_ARGS,
        )
        n = int(cur.fetchone()["n"])
        if n:
            errors.append(
                f"live-ops tournaments with games before Make official: {n} "
                "(RTB — run verify-running-tournament-boundary)"
            )
    return errors


def main() -> int:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password,
        database=cfg.database, charset="utf8mb4", cursorclass=DictCursor,
    )
    try:
        errors = verify_ground_games(conn)
    finally:
        conn.close()
    if errors:
        for err in errors:
            print(f"FAIL: {err}", file=sys.stderr)
        return 1
    print("OK: every game belongs to a tournament, and every finished tournament with games is finalized")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
