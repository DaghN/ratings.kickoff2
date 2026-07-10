"""Groningen VII (48): split Round 2 into Group D/E (3x3 double RR each)."""
from __future__ import annotations

from scripts.amiga.tournament_fixtures import create_stage
from scripts.amiga.tournament_structure.materialize_legacy import _connect

TID = 48
GROUP_D = {224, 405, 259}  # Kees V, Sjoerd K, Luitzen B (games 1051-1056)
GROUP_E = {280, 379, 105}  # Mark P, Riemer P, Evert V (games 1057-1062)
GROUPS = (
    ("round-2-group-d", "Round 2 - Group D", GROUP_D),
    ("round-2-group-e", "Round 2 - Group E", GROUP_E),
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
        for key, name, _players in GROUPS:
            stage_ids[key] = create_stage(
                conn,
                tournament_id=TID,
                stage_key=key,
                name=name,
                stage_type="round_robin",
                sequence_no=0,
                config={"materialized_by": "manual_group_split", "legacy_import": True},
            )

        with conn.cursor() as cur:
            cur.execute(
                """
                SELECT f.id AS fixture_id, g.id AS game_id, g.player_a_id, g.player_b_id
                FROM tournament_fixtures f
                JOIN amiga_games g ON g.fixture_id = f.id
                WHERE f.stage_id = %s
                ORDER BY g.id
                """,
                (old_id,),
            )
            rows = cur.fetchall()
            for row in rows:
                pa, pb = int(row["player_a_id"]), int(row["player_b_id"])
                if pa in GROUP_D and pb in GROUP_D:
                    new_stage = stage_ids["round-2-group-d"]
                elif pa in GROUP_E and pb in GROUP_E:
                    new_stage = stage_ids["round-2-group-e"]
                else:
                    raise SystemExit(
                        f"cross-group fixture {row['fixture_id']} g{row['game_id']}: {pa} vs {pb}"
                    )
                cur.execute(
                    "UPDATE tournament_fixtures SET stage_id=%s, phase_label=NULL WHERE id=%s",
                    (new_stage, int(row["fixture_id"])),
                )

            cur.execute(
                "DELETE FROM amiga_tournament_standings WHERE stage_id = %s", (old_id,)
            )
            cur.execute("DELETE FROM tournament_stages WHERE id = %s", (old_id,))

            order = [
                ("round-1-group-a", 1),
                ("round-1-group-b", 2),
                ("round-1-group-c", 3),
                ("round-2-group-d", 4),
                ("round-2-group-e", 5),
                ("league-7-9", 6),
                ("ko-semi-final-224-379", 7),
                ("ko-semi-final-259-280", 8),
                ("ko-5th-place-final-105-405", 9),
                ("ko-3rd-place-final-259-379", 10),
                ("ko-final-224-280", 11),
            ]
            for key, seq in order:
                cur.execute(
                    "UPDATE tournament_stages SET sequence_no = %s "
                    "WHERE tournament_id = %s AND stage_key = %s",
                    (seq, TID, key),
                )

        conn.commit()
        print(f"Round 2 split OK: {len(rows)} fixtures -> Group D/E")
    finally:
        conn.close()


if __name__ == "__main__":
    main()