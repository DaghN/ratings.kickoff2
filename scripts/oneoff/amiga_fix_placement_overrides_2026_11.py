"""Correct finish overrides that list the loser of a placement final above the winner.

The overrides came from the Access [Tables] standings. Dagh, 2026-11-22: the results are right and
the manual placings are wrong, so the result decides.

    python scripts/oneoff/amiga_fix_placement_overrides_2026_11.py            # report only
    python scripts/oneoff/amiga_fix_placement_overrides_2026_11.py --apply    # ko2amiga_work, then simul

Refuses unless the games prove the winner and the two players hold exactly the two places in question.
Running it twice is harmless.
"""

from __future__ import annotations

import argparse

import pymysql
from pymysql.cursors import DictCursor

WORK_DB = "ko2amiga_work"

# tournament_id, better place, winner id, loser id, phase of the final
FIXES = [
    (25, 25, 232, 322, "25th Place Final"),  # World Cup XXIII (Milan): Klaus Le beat Mick C
    (25, 27, 351, 299, "27th Place Final"),  # World Cup XXIII (Milan): Paolo A beat Matthias K
    (9, 11, 124, 9, "11th Place Final"),  # World Cup XIX (Bremen): Frank F beat Alessandro V
]


def final_score(cur, tournament_id: int, phase: str, winner: int, loser: int) -> tuple[int, int, int]:
    cur.execute(
        """
        SELECT player_a_id, goals_a, goals_b FROM amiga_games
        WHERE tournament_id = %s AND phase = %s
          AND ((player_a_id = %s AND player_b_id = %s) OR (player_a_id = %s AND player_b_id = %s))
        """,
        (tournament_id, phase, winner, loser, loser, winner),
    )
    games = cur.fetchall()
    won = sum(int(g["goals_a"] if g["player_a_id"] == winner else g["goals_b"]) for g in games)
    lost = sum(int(g["goals_b"] if g["player_a_id"] == winner else g["goals_a"]) for g in games)
    return len(games), won, lost


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()

    conn = pymysql.connect(
        host="127.0.0.1", user="root", password="", database=WORK_DB, charset="utf8mb4", cursorclass=DictCursor
    )
    changed = 0
    with conn.cursor() as cur:
        for tournament_id, place, winner, loser, phase in FIXES:
            cur.execute(
                """
                SELECT o.player_id, o.event_finish_position AS pos, p.name
                FROM amiga_tournament_finish_override o INNER JOIN amiga_players p ON p.id = o.player_id
                WHERE o.tournament_id = %s AND o.player_id IN (%s, %s)
                """,
                (tournament_id, winner, loser),
            )
            rows = {int(r["player_id"]): r for r in cur.fetchall()}
            if set(rows) != {winner, loser}:
                raise SystemExit(f"tournament {tournament_id}: override rows missing for {winner}/{loser}")
            legs, won, lost = final_score(cur, tournament_id, phase, winner, loser)
            if legs == 0 or won <= lost:
                raise SystemExit(f"tournament {tournament_id} {phase}: games do not prove the winner ({won}-{lost})")
            label = (
                f"tournament {tournament_id} {phase}: {rows[winner]['name']} beat {rows[loser]['name']} "
                f"{won}-{lost} ({legs} games)"
            )
            places = (int(rows[winner]["pos"]), int(rows[loser]["pos"]))
            if places == (place, place + 1):
                print(f"{label}: already {places[0]}/{places[1]}")
                continue
            if places != (place + 1, place):
                raise SystemExit(f"{label}: unexpected places {places}")
            print(f"{label}: {places[0]}/{places[1]} -> {place}/{place + 1}")
            if args.apply:
                cur.executemany(
                    "UPDATE amiga_tournament_finish_override SET event_finish_position = %s "
                    "WHERE tournament_id = %s AND player_id = %s",
                    [(place, tournament_id, winner), (place + 1, tournament_id, loser)],
                )
                changed += 1
    if args.apply:
        conn.commit()
        print(f"applied {changed} swaps to {WORK_DB}; now run: python -m scripts.amiga simul")
    conn.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
