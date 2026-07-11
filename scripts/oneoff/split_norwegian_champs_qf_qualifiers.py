"""Norwegian Champs (121): split Round 2 play-in into 4 knockout QF Qualifiers stages."""
from __future__ import annotations

from scripts.amiga.tournament_fixtures import create_stage
from scripts.amiga.tournament_structure.materialize_legacy import _connect

TID = 121
STAGE_NAME = "QF Qualifiers"
# (stage_key_suffix, player_a_id, player_b_id) — game order 3729-3732
QUALIFIERS = (
    ("ko-qf-qualifiers-193-425", 193, 425),
    ("ko-qf-qualifiers-211-430", 211, 430),
    ("ko-qf-qualifiers-246-371", 246, 371),
    ("ko-qf-qualifiers-104-209", 104, 209),
)


def main() -> None:
    conn = _connect()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id FROM tournament_stages WHERE tournament_id=%s AND stage_key=%s",
                (TID, "round-2"),
            )
            old = cur.fetchone()
            if not old:
                raise SystemExit("round-2 stage missing")
            old_id = int(old["id"])

        stage_ids: dict[str, int] = {}
        for key, _pa, _pb in QUALIFIERS:
            stage_ids[key] = create_stage(
                conn,
                tournament_id=TID,
                stage_key=key,
                name=STAGE_NAME,
                stage_type="knockout",
                sequence_no=0,
                config={"materialized_by": "manual_ko_split", "legacy_import": True},
            )

        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT f.id AS fixture_id, g.player_a_id, g.player_b_id
                FROM tournament_fixtures f
                JOIN amiga_games g ON g.fixture_id = f.id
                WHERE f.stage_id = %s
                ORDER BY g.id
                """,
                (old_id,),
            )
            rows = cur.fetchall()
            key_by_pair = {
                (min(int(pa), int(pb)), max(int(pa), int(pb))): key
                for key, pa, pb in QUALIFIERS
            }
            for row in rows:
                pa, pb = int(row["player_a_id"]), int(row["player_b_id"])
                pair = (min(pa, pb), max(pa, pb))
                key = key_by_pair.get(pair)
                if key is None:
                    raise SystemExit(f"unexpected fixture pair {pa} vs {pb}")
                cur.execute(
                    "UPDATE tournament_fixtures SET stage_id=%s, phase_label=NULL WHERE id=%s",
                    (stage_ids[key], int(row["fixture_id"])),
                )

            cur.execute(
                "DELETE FROM amiga_tournament_standings WHERE stage_id = %s", (old_id,)
            )
            cur.execute("DELETE FROM tournament_stages WHERE id = %s", (old_id,))

            order = [
                ("round-1-group-a", 1),
                ("round-1-group-b", 2),
                ("round-1-group-c", 3),
                ("round-1-group-d", 4),
                ("ko-qf-qualifiers-193-425", 5),
                ("ko-qf-qualifiers-211-430", 6),
                ("ko-qf-qualifiers-246-371", 7),
                ("ko-qf-qualifiers-104-209", 8),
                ("ko-quarter-finals-1-211", 9),
                ("ko-quarter-finals-153-193", 10),
                ("ko-quarter-finals-209-347", 11),
                ("ko-quarter-finals-228-371", 12),
                ("ko-semi-finals-1-153", 13),
                ("ko-semi-finals-209-228", 14),
                ("ko-3rd-place-final-1-209", 15),
                ("ko-final-153-228", 16),
            ]
            for key, seq in order:
                cur.execute(
                    "UPDATE tournament_stages SET sequence_no = %s "
                    "WHERE tournament_id = %s AND stage_key = %s",
                    (seq, TID, key),
                )

        conn.commit()
        print(f"QF Qualifiers split OK: {len(rows)} fixtures -> 4 KO stages")
    finally:
        conn.close()


if __name__ == "__main__":
    main()