"""Groningen VII (48): Playouts witness = double RR for 7th-9th, not KO."""
from __future__ import annotations

from scripts.amiga.tournament_fixtures import create_stage
from scripts.amiga.tournament_structure.materialize_legacy import _connect

TID = 48
PLAYOUT_GAME_IDS = (1045, 1046, 1047, 1048, 1049, 1050)
OLD_STAGE_KEYS = (
    "ko-playouts-160-332",
    "ko-playouts-160-442",
    "ko-playouts-332-442",
)


def main() -> None:
    conn = _connect()
    try:
        stage_id = create_stage(
            conn,
            tournament_id=TID,
            stage_key="league-7-9",
            name="League 7-9",
            stage_type="round_robin",
            sequence_no=3,
            config={
                "materialized_by": "manual_league_7_9_fix",
                "legacy_import": True,
                "phase_provenance": "labeled",
            },
        )
        with conn.cursor() as cur:
            for gid in PLAYOUT_GAME_IDS:
                cur.execute(
                    """
                    UPDATE tournament_fixtures f
                    JOIN amiga_games g ON g.fixture_id = f.id
                    SET f.stage_id = %s, f.phase_label = NULL
                    WHERE g.tournament_id = %s AND g.id = %s
                    """,
                    (stage_id, TID, gid),
                )
            for key in OLD_STAGE_KEYS:
                cur.execute(
                    "SELECT id FROM tournament_stages WHERE tournament_id = %s AND stage_key = %s",
                    (TID, key),
                )
                row = cur.fetchone()
                if row:
                    old_id = int(row["id"])
                    cur.execute(
                        "DELETE FROM amiga_tournament_standings WHERE stage_id = %s",
                        (old_id,),
                    )
                    cur.execute("DELETE FROM tournament_stages WHERE id = %s", (old_id,))

            order = [
                ("round-1", 1),
                ("round-2", 2),
                ("league-7-9", 3),
                ("ko-semi-final-224-379", 4),
                ("ko-semi-final-259-280", 5),
                ("ko-5th-place-final-105-405", 6),
                ("ko-3rd-place-final-259-379", 7),
                ("ko-final-224-280", 8),
            ]
            for key, seq in order:
                cur.execute(
                    "UPDATE tournament_stages SET sequence_no = %s "
                    "WHERE tournament_id = %s AND stage_key = %s",
                    (seq, TID, key),
                )
        conn.commit()
        print(f"League 7-9 stage {stage_id}: moved {len(PLAYOUT_GAME_IDS)} fixtures")
    finally:
        conn.close()


if __name__ == "__main__":
    main()