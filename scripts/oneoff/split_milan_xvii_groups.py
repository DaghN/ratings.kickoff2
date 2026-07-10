"""Split Milan XVII (198) round-1 into Group A / Group B after legacy materialize."""
from __future__ import annotations

from scripts.amiga.tournament_fixtures import create_stage
from scripts.amiga.tournament_structure.materialize_legacy import _connect

GROUP_A = {109, 149, 269, 304}
GROUP_B = {9, 257, 272}
TID = 198


def main() -> None:
    conn = _connect()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id FROM tournament_stages WHERE tournament_id=%s AND stage_key=%s",
                (TID, "round-1"),
            )
            old = cur.fetchone()
            if not old:
                raise SystemExit("round-1 stage missing")
            old_id = int(old["id"])

        stage_a = create_stage(
            conn,
            tournament_id=TID,
            stage_key="round-1-group-a",
            name="Group A",
            stage_type="round_robin",
            sequence_no=1,
            config={"materialized_by": "manual_group_split", "legacy_import": True},
        )
        stage_b = create_stage(
            conn,
            tournament_id=TID,
            stage_key="round-1-group-b",
            name="Group B",
            stage_type="round_robin",
            sequence_no=2,
            config={"materialized_by": "manual_group_split", "legacy_import": True},
        )

        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT f.id AS fixture_id, g.player_a_id, g.player_b_id
                FROM tournament_fixtures f
                JOIN amiga_games g ON g.fixture_id = f.id
                WHERE f.stage_id = %s
                """,
                (old_id,),
            )
            rows = cur.fetchall()
            for row in rows:
                pa, pb = int(row["player_a_id"]), int(row["player_b_id"])
                if pa in GROUP_A and pb in GROUP_A:
                    new_stage = stage_a
                elif pa in GROUP_B and pb in GROUP_B:
                    new_stage = stage_b
                else:
                    raise SystemExit(
                        f"cross-group fixture {row['fixture_id']}: {pa} vs {pb}"
                    )
                cur.execute(
                    "UPDATE tournament_fixtures SET stage_id=%s WHERE id=%s",
                    (new_stage, int(row["fixture_id"])),
                )

            cur.execute(
                """
                UPDATE tournament_fixtures f
                JOIN tournament_stages s ON s.id = f.stage_id
                SET f.phase_label = NULL
                WHERE s.tournament_id = %s AND s.stage_type = 'round_robin'
                """,
                (TID,),
            )

            cur.execute(
                "DELETE FROM amiga_tournament_standings WHERE stage_id=%s", (old_id,)
            )
            cur.execute("DELETE FROM tournament_stages WHERE id=%s", (old_id,))

            order = [
                ("round-1-group-a", 1),
                ("round-1-group-b", 2),
                ("ko-quarter-finals-109-272", 3),
                ("ko-quarter-finals-257-304", 4),
                ("ko-quarter-finals-9-269", 5),
                ("ko-semi-finals-149-272", 6),
                ("ko-semi-finals-257-269", 7),
                ("ko-playouts-5-7-109-304", 8),
                ("ko-5th-place-final-9-109", 9),
                ("ko-3rd-place-final-269-272", 10),
                ("ko-final-149-257", 11),
            ]
            for key, seq in order:
                cur.execute(
                    "UPDATE tournament_stages SET sequence_no=%s WHERE tournament_id=%s AND stage_key=%s",
                    (seq, TID, key),
                )

        conn.commit()
        print(
            f"Group split OK: stage_a={stage_a} stage_b={stage_b} moved {len(rows)} fixtures"
        )
    finally:
        conn.close()


if __name__ == "__main__":
    main()