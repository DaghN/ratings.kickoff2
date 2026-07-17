from __future__ import annotations

import json
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_fixtures import record_fixture_result

TID = 608
NEWCOMER_ID = 470


def score_for_index(i: int) -> tuple[int, int]:
    patterns = [
        (2, 1), (3, 0), (1, 1), (4, 2), (0, 1), (2, 2), (5, 1), (1, 0),
        (3, 3), (2, 0), (0, 2), (1, 3), (4, 1), (2, 3),
    ]
    return patterns[i % len(patterns)]


def main() -> None:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host, port=cfg.port, user=cfg.user, password=cfg.password,
        database=cfg.database, charset="utf8mb4", autocommit=False, cursorclass=DictCursor,
    )
    try:
        with conn.cursor() as cur:
            cur.execute("SET time_zone = '+00:00'")
            cur.execute(
                """
                SELECT f.id
                FROM tournament_fixtures f
                INNER JOIN tournament_stages s ON s.id = f.stage_id
                WHERE s.tournament_id = %s AND f.status = 'scheduled'
                ORDER BY f.fixture_key ASC, f.id ASC
                """,
                (TID,),
            )
            fixture_ids = [int(r["id"]) for r in cur.fetchall()]

        for i, fid in enumerate(fixture_ids):
            ga, gb = score_for_index(i)
            record_fixture_result(conn, fixture_id=fid, goals_a=ga, goals_b=gb)
        conn.commit()

        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT id, name, event_date, country, is_world_cup, lifecycle_status,
                       rating_finalized, player_count
                FROM tournaments WHERE id = %s
                """,
                (TID,),
            )
            tour = cur.fetchone()
            cur.execute("SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s", (TID,))
            games = int(cur.fetchone()["n"])
            cur.execute(
                """
                SELECT COUNT(*) AS n FROM tournament_fixtures f
                INNER JOIN tournament_stages s ON s.id = f.stage_id
                WHERE s.tournament_id = %s AND f.status = 'played'
                """,
                (TID,),
            )
            played = int(cur.fetchone()["n"])
            cur.execute(
                "SELECT id, name, country, player_source FROM amiga_players WHERE id = %s",
                (NEWCOMER_ID,),
            )
            newcomer = cur.fetchone()
            cur.execute(
                """
                SELECT e.player_id, p.name, p.country, e.seed_no
                FROM tournament_entrants e
                INNER JOIN amiga_players p ON p.id = e.player_id
                WHERE e.tournament_id = %s
                ORDER BY e.seed_no
                """,
                (TID,),
            )
            field = cur.fetchall()

        meta = {
            "probe": "php-finalize-parity",
            "shape": "kitchen_rr_stamped_wc",
            "base_checkpoint": "work-2026-07-16-php-parity-base",
            "tournament": tour,
            "newcomer": newcomer,
            "field": field,
            "fixtures_played": played,
            "amiga_games_for_t": games,
        }
        out = Path("data/amiga/parity")
        out.mkdir(parents=True, exist_ok=True)
        meta_path = out / "nt-running-meta.json"
        meta_path.write_text(json.dumps(meta, indent=2, default=str) + "\n", encoding="utf-8")
        print(json.dumps(meta, indent=2, default=str))
        print(f"meta_written={meta_path}")
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


if __name__ == "__main__":
    main()