from __future__ import annotations

import json
from datetime import date
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.player_registry import create_player
from scripts.amiga.tournament_builder import create_kitchen_marathon_tournament
from scripts.amiga.tournament_fixtures import (
    record_fixture_result,
    set_tournament_lifecycle_status,
)
from scripts.amiga.tournament_honours import is_world_cup_tournament

VETERANS = [382, 14, 149, 417, 441, 134, 418]  # Robert S … Steve E
EVENT_DATE = date(2026, 7, 16)
HOST_COUNTRY = "Iceland"
NEWCOMER_COUNTRY = "Iceland"
NEWCOMER_NAME = "Inga H"
TOURNEY_NAME = "World Cup Parity Probe I"


def connect_work():
    cfg = load_amiga_db_config()
    assert cfg.database == "ko2amiga_work", cfg.database
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        autocommit=False,
        cursorclass=DictCursor,
    )
    with conn.cursor() as cur:
        cur.execute("SET time_zone = '+00:00'")
    return conn


def score_for_index(i: int) -> tuple[int, int]:
    # Deterministic mixed results (wins + some draws).
    patterns = [
        (2, 1), (3, 0), (1, 1), (4, 2), (0, 1), (2, 2), (5, 1), (1, 0),
        (3, 3), (2, 0), (0, 2), (1, 3), (4, 1), (2, 3),
    ]
    return patterns[i % len(patterns)]


def main() -> None:
    assert is_world_cup_tournament(TOURNEY_NAME), TOURNEY_NAME
    conn = connect_work()
    try:
        created = create_player(NEWCOMER_NAME, country=NEWCOMER_COUNTRY, conn=conn)
        newcomer_id = int(created["player_id"])
        conn.commit()

        player_ids = VETERANS + [newcomer_id]
        built = create_kitchen_marathon_tournament(
            conn,
            name=TOURNEY_NAME,
            event_date=EVENT_DATE,
            country=HOST_COUNTRY,
            player_ids=player_ids,
            legs=1,
        )
        tid = int(built["tournament_id"])

        with conn.cursor() as cur:
            cur.execute(
                """
                UPDATE tournaments
                SET is_world_cup = 1
                WHERE id = %s
                """,
                (tid,),
            )
        conn.commit()

        set_tournament_lifecycle_status(conn, tournament_id=tid, status="running")
        conn.commit()

        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT f.id
                FROM tournament_fixtures f
                INNER JOIN tournament_stages s ON s.id = f.stage_id
                WHERE s.tournament_id = %s
                ORDER BY f.fixture_key ASC, f.id ASC
                """,
                (tid,),
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
                (tid,),
            )
            tour = cur.fetchone()
            cur.execute(
                "SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = %s",
                (tid,),
            )
            games = int(cur.fetchone()["n"])
            cur.execute(
                """
                SELECT COUNT(*) AS n FROM tournament_fixtures f
                INNER JOIN tournament_stages s ON s.id = f.stage_id
                WHERE s.tournament_id = %s AND f.status = 'played'
                """,
                (tid,),
            )
            played = int(cur.fetchone()["n"])
            cur.execute(
                "SELECT id, name, country, player_source FROM amiga_players WHERE id = %s",
                (newcomer_id,),
            )
            newcomer = cur.fetchone()

        meta = {
            "probe": "php-finalize-parity",
            "shape": "kitchen_rr_stamped_wc",
            "base_checkpoint": "work-2026-07-16-php-parity-base",
            "tournament": tour,
            "newcomer": newcomer,
            "veteran_player_ids": VETERANS,
            "fixture_count": len(fixture_ids),
            "fixtures_played": played,
            "amiga_games_for_t": games,
            "score_pattern": "deterministic cycling patterns in seed script",
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