"""Correct seventeen World Cup results: wrong or missing extra-time notes, two scores, two shootouts.

The corrected values live in SCORE_CORRECTIONS (scripts/amiga/import_corrections.py), so an Access
import gives the same rows. Evidence: docs/amiga-l3-legacy-fixes-inventory.md section 5.2.

    python scripts/oneoff/amiga_fix_world_cup_extra_time_2026_11.py            # report only
    python scripts/oneoff/amiga_fix_world_cup_extra_time_2026_11.py --apply    # ko2amiga_work, then simul

Site convention: goals_a/goals_b = normal time (Elo uses these), goals_et_* = goals in extra time only,
extra = the leg's score after extra time in the row's player order, e.g. "3-4 e.t." or "(8-6) 12-11 p.k.".
Refuses any row that is neither the Access value nor the corrected one. Running it twice is harmless.
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

import pymysql
from pymysql.cursors import DictCursor

sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from scripts.amiga.import_corrections import SCORE_CORRECTIONS
from scripts.amiga.match_extensions import extract_structured_from_extra

WORK_DB = "ko2amiga_work"
FIELDS = ("goals_a", "goals_b", "extra", "goals_et_a", "goals_et_b", "pens_a", "pens_b")

# source_scores_id -> the Access values (FIELDS order)
BEFORE = {
    11390: (3, 3, "3-5 e.t.", 0, 2, None, None),  # VII Places 25-28
    18836: (2, 4, None, None, None, None, None),  # X Places 25-28
    18840: (4, 5, None, None, None, None, None),  # X 27th
    18842: (2, 6, None, None, None, None, None),  # X 25th
    18857: (4, 4, None, None, None, None, None),  # X 17th
    23551: (1, 2, "(2-1) 4-5 p.k.", 2, 1, 4, 5),  # XV 29th
    24904: (2, 2, "(8-6) 12-11 p.k.", 6, 4, 12, 11),  # XVII Places 9-16 (note belongs to 24928)
    24922: (2, 3, "3-3 e.t.", 1, 0, None, None),  # XVII Places 9-12
    24928: (6, 4, None, None, None, None, None),  # XVII quarter-final
    26887: (3, 3, "4-3 e.t.", 1, 0, None, None),  # XXI 17th
    26896: (2, 2, None, None, None, None, None),  # XXI 29th
    20483: (1, 2, None, None, None, None, None),  # XI Places 25-32
    20504: (5, 2, None, None, None, None, None),  # XI 19th
    26929: (6, 5, None, None, None, None, None),  # XXI 5th
    26913: (2, 9, None, None, None, None, None),  # XXI 11th
    4652: (1, 7, None, None, None, None, None),  # IV Round 1 Group B
    27482: (3, 3, "4-5 a.e.t.", 1, 2, None, None),  # XXII Positions 5-8 (penalties)
    27484: (2, 2, "3-2 a.e.t.", 1, 0, None, None),  # XXII 5th (penalties)
}


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()

    corrections = {c.source_scores_id: c for c in SCORE_CORRECTIONS}
    conn = pymysql.connect(
        host="127.0.0.1", user="root", password="", database=WORK_DB, charset="utf8mb4", cursorclass=DictCursor
    )
    changed = 0
    with conn.cursor() as cur:
        for source_id, before in BEFORE.items():
            corr = corrections[source_id]
            after = tuple(getattr(corr, field) for field in FIELDS)
            parsed = extract_structured_from_extra(corr.extra, goals_a=corr.goals_a, goals_b=corr.goals_b)
            structured = (None,) * 4 if parsed is None else tuple(getattr(parsed, field) for field in FIELDS[3:])
            if structured != after[3:]:
                raise SystemExit(f"{source_id}: note {corr.extra!r} reads as {structured}, columns say {after[3:]}")
            cur.execute(
                f"""
                SELECT g.id, t.name AS tournament, g.phase, pa.name AS a, pb.name AS b, g.fixture_id,
                       {", ".join("g." + field for field in FIELDS)}
                FROM amiga_games g
                INNER JOIN tournaments t ON t.id = g.tournament_id
                INNER JOIN amiga_players pa ON pa.id = g.player_a_id
                INNER JOIN amiga_players pb ON pb.id = g.player_b_id
                WHERE g.source_scores_id = %s
                """,
                (source_id,),
            )
            row = cur.fetchone()
            if row is None or (row["a"], row["b"]) != (corr.team_a, corr.team_b) or row["fixture_id"] is not None:
                raise SystemExit(f"{source_id}: unexpected row {row}")
            now = tuple(row[field] for field in FIELDS)
            label = f"{row['tournament']} {row['phase']} (game {row['id']}): {row['a']} - {row['b']}"
            if now == after:
                print(f"{label}: already corrected")
                continue
            if now != before:
                raise SystemExit(f"{label}: unexpected values {now}")
            print(f"{label}: {before} -> {after}")
            if args.apply:
                cur.execute(
                    f"UPDATE amiga_games SET {', '.join(field + ' = %s' for field in FIELDS)} WHERE id = %s",
                    (*after, row["id"]),
                )
                changed += 1
    if args.apply:
        conn.commit()
        print(f"applied {changed} rows to {WORK_DB}; now run: python -m scripts.amiga simul")
    conn.close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
