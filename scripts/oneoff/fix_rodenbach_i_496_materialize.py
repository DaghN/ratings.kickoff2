"""Post-materialize fix for Rodenbach I (496): League display name + Round 2 as KO ties."""
from __future__ import annotations

import pymysql

from scripts.amiga.config import load_amiga_db_config
from scripts.amiga.tournament_fixtures import create_stage
from scripts.amiga.tournament_structure.materialize_legacy import (
    MATERIALIZE_SOURCE,
    _import_create_fixture,
    _slug_key,
)
from scripts.amiga.tournament_standings import rebuild_standings_for_tournament

TOURNAMENT_ID = 496


def main() -> None:
    cfg = load_amiga_db_config()
    conn = pymysql.connect(
        host=cfg.host,
        port=cfg.port,
        user=cfg.user,
        password=cfg.password,
        database=cfg.database,
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
    )
    try:
        with conn.cursor() as cur:
            cur.execute(
                "UPDATE tournament_stages SET name = %s WHERE tournament_id = %s AND stage_key = %s",
                ("League", TOURNAMENT_ID, "round-1"),
            )
            cur.execute(
                "SELECT id FROM tournament_stages WHERE tournament_id = %s AND stage_key = %s",
                (TOURNAMENT_ID, "round-2"),
            )
            row = cur.fetchone()
            if row is None:
                raise RuntimeError("round-2 stage missing")
            old_stage_id = int(row["id"])
            cur.execute(
                """
                SELECT g.id, g.player_a_id, g.player_b_id, g.fixture_id
                FROM amiga_games g
                JOIN tournament_fixtures f ON f.id = g.fixture_id
                WHERE g.tournament_id = %s AND f.stage_id = %s
                ORDER BY g.id
                """,
                (TOURNAMENT_ID, old_stage_id),
            )
            games = cur.fetchall()

        for i, game in enumerate(games):
            pa, pb = int(game["player_a_id"]), int(game["player_b_id"])
            lo, hi = min(pa, pb), max(pa, pb)
            stage_key = f"ko-{_slug_key('Round 2')}-{lo}-{hi}"
            stage_id = create_stage(
                conn,
                tournament_id=TOURNAMENT_ID,
                stage_key=stage_key,
                name="Round 2",
                stage_type="knockout",
                sequence_no=3 + i,
                config={
                    "materialized_by": MATERIALIZE_SOURCE,
                    "legacy_import": True,
                    "round_2_knockout_fix": True,
                },
            )
            fixture_id = _import_create_fixture(
                conn,
                stage_id=stage_id,
                fixture_key=f"legacy-g{int(game['id'])}",
                player_a_id=pa,
                player_b_id=pb,
                leg_no=1,
                phase_label=None,
            )
            with conn.cursor() as cur:
                cur.execute(
                    "UPDATE amiga_games SET fixture_id = %s WHERE id = %s",
                    (fixture_id, int(game["id"])),
                )
                cur.execute(
                    "DELETE FROM tournament_fixtures WHERE id = %s",
                    (int(game["fixture_id"]),),
                )

        with conn.cursor() as cur:
            cur.execute("DELETE FROM tournament_stages WHERE id = %s", (old_stage_id,))

        rows = rebuild_standings_for_tournament(conn, TOURNAMENT_ID)
        conn.commit()
        print(f"fix_rodenbach_i_496: round2_knockout_ties={len(games)} standings_rows={rows}")
    finally:
        conn.close()


if __name__ == "__main__":
    main()