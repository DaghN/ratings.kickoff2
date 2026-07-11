"""Split Milan I (89) forum Extra games into round-1-group-b-extra stage."""
from __future__ import annotations

from scripts.amiga.tournament_fixtures import create_stage
from scripts.amiga.tournament_structure.materialize_legacy import _connect

TID = 89
EXTRA_GAME_IDS = (2394, 2395, 2396, 2397, 2398)
STAGE_NAME = "Group B extra"


def main() -> None:
    conn = _connect()
    try:
        extra_stage_id = create_stage(
            conn,
            tournament_id=TID,
            stage_key="round-1-group-b-extra",
            name=STAGE_NAME,
            stage_type="round_robin",
            sequence_no=3,
            config={"materialized_by": "manual_group_b_extra_split", "legacy_import": True},
        )

        with conn.cursor() as cur:
            cur.execute(
                """
                UPDATE tournament_stages
                SET sequence_no = sequence_no + 1
                WHERE tournament_id = %s AND sequence_no >= 3 AND id <> %s
                """,
                (TID, extra_stage_id),
            )

            placeholders = ",".join(["%s"] * len(EXTRA_GAME_IDS))
            cur.execute(
                f"""
                SELECT fixture_id FROM amiga_games
                WHERE tournament_id = %s AND id IN ({placeholders}) AND fixture_id IS NOT NULL
                """,
                (TID, *EXTRA_GAME_IDS),
            )
            fixture_ids = [int(r["fixture_id"]) for r in cur.fetchall()]
            if len(fixture_ids) != len(EXTRA_GAME_IDS):
                raise SystemExit(f"expected {len(EXTRA_GAME_IDS)} fixtures, got {fixture_ids}")

            for fid in fixture_ids:
                cur.execute(
                    "UPDATE tournament_fixtures SET stage_id = %s, phase_label = NULL WHERE id = %s",
                    (extra_stage_id, fid),
                )

        conn.commit()
        print(
            f"Milan I ({TID}): stage_id={extra_stage_id} name={STAGE_NAME!r}; "
            f"moved {len(fixture_ids)} fixtures; witness phase unchanged on games {EXTRA_GAME_IDS}"
        )
    finally:
        conn.close()


if __name__ == "__main__":
    main()