"""Groningen VII (48): split Round 1 into Group A/B/C (3x3 double RR)."""
from __future__ import annotations

from scripts.amiga.tournament_fixtures import create_stage
from scripts.amiga.tournament_structure.materialize_legacy import _connect

TID = 48
GROUP_A = {280, 332, 405}  # Mark P, Niels T, Sjoerd K
GROUP_B = {224, 105, 442}  # Kees V, Evert V, Tim K
GROUP_C = {379, 259, 160}  # Riemer P, Luitzen B, Gunther W
GROUPS = (
    ("round-1-group-a", "Round 1 - Group A", GROUP_A),
    ("round-1-group-b", "Round 1 - Group B", GROUP_B),
    ("round-1-group-c", "Round 1 - Group C", GROUP_C),
)


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

        stage_ids: dict[str, int] = {}
        for seq, (key, name, _players) in enumerate(GROUPS, start=1):
            stage_ids[key] = create_stage(
                conn,
                tournament_id=TID,
                stage_key=key,
                name=name,
                stage_type="round_robin",
                sequence_no=seq,
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
                    new_stage = stage_ids["round-1-group-a"]
                elif pa in GROUP_B and pb in GROUP_B:
                    new_stage = stage_ids["round-1-group-b"]
                elif pa in GROUP_C and pb in GROUP_C:
                    new_stage = stage_ids["round-1-group-c"]
                else:
                    raise SystemExit(f"cross-group fixture {row['fixture_id']}: {pa} vs {pb}")
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
                ("round-2", 4),
                ("league-7-9", 5),
                ("ko-semi-final-224-379", 6),
                ("ko-semi-final-259-280", 7),
                ("ko-5th-place-final-105-405", 8),
                ("ko-3rd-place-final-259-379", 9),
                ("ko-final-224-280", 10),
            ]
            for key, seq in order:
                cur.execute(
                    "UPDATE tournament_stages SET sequence_no = %s "
                    "WHERE tournament_id = %s AND stage_key = %s",
                    (seq, TID, key),
                )

        conn.commit()
        print(f"Round 1 split OK: {len(rows)} fixtures -> Group A/B/C")
    finally:
        conn.close()


if __name__ == "__main__":
    main()