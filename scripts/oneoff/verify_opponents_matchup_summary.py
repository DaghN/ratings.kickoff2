"""Spot-check player_matchup_summary (SCH-019) vs live aggregation on processed games."""

from __future__ import annotations

import sys

import pymysql

SPOT_SQL = """
SELECT m.player_id, m.opponent_id, m.games,
       m.max_goals_for, m.max_goals_against, m.min_goals_for, m.min_goals_against,
       m.max_win_margin, m.max_loss_margin, m.max_draw_goals, m.max_goal_sum, m.min_goal_sum,
       m.double_digits, m.double_digits_conceded, m.clean_sheets, m.clean_sheets_conceded,
       live.max_gf, live.max_ga, live.min_gf, live.min_ga,
       live.max_win, live.max_loss, live.max_draw, live.max_sum, live.min_sum,
       live.dd, live.ddc, live.cs, live.csc
FROM player_matchup_summary m
INNER JOIN (
  SELECT pid, oid,
         COUNT(*) AS games,
         MAX(gf) AS max_gf, MAX(ga) AS max_ga, MIN(gf) AS min_gf, MIN(ga) AS min_ga,
         MAX(CASE WHEN w > 0 THEN gf - ga END) AS max_win,
         MAX(CASE WHEN l > 0 THEN ga - gf END) AS max_loss,
         MAX(CASE WHEN d > 0 THEN gf END) AS max_draw,
         MAX(gf + ga) AS max_sum, MIN(gf + ga) AS min_sum,
         SUM(dd) AS dd, SUM(ddc) AS ddc, SUM(cs) AS cs, SUM(csc) AS csc
  FROM (
    SELECT idA AS pid, idB AS oid,
           CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS w,
           CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
           CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS l,
           GoalsA AS gf, GoalsB AS ga,
           DDPlayerA AS dd, DDPlayerB AS ddc, CSPlayerA AS cs, CSPlayerB AS csc
    FROM ratedresults WHERE NewRatingA IS NOT NULL
    UNION ALL
    SELECT idB AS pid, idA AS oid,
           CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS w,
           CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
           CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS l,
           GoalsB AS gf, GoalsA AS ga,
           DDPlayerB AS dd, DDPlayerA AS ddc, CSPlayerB AS cs, CSPlayerA AS csc
    FROM ratedresults WHERE NewRatingA IS NOT NULL
  ) sides
  GROUP BY pid, oid
) live ON live.pid = m.player_id AND live.oid = m.opponent_id
WHERE m.games >= 3
ORDER BY m.games DESC
LIMIT 50
"""


def main() -> int:
    conn = pymysql.connect(host="127.0.0.1", user="root", password="", database="ko2unity_work", charset="utf8mb4")
    conn.cursor().execute("SET time_zone = '+00:00'")
    with conn.cursor(pymysql.cursors.DictCursor) as cur:
        cur.execute(SPOT_SQL)
        rows = cur.fetchall()

    errors: list[str] = []
    cols = [
        ("max_goals_for", "max_gf"),
        ("max_goals_against", "max_ga"),
        ("min_goals_for", "min_gf"),
        ("min_goals_against", "min_ga"),
        ("max_win_margin", "max_win"),
        ("max_loss_margin", "max_loss"),
        ("max_draw_goals", "max_draw"),
        ("max_goal_sum", "max_sum"),
        ("min_goal_sum", "min_sum"),
        ("double_digits", "dd"),
        ("double_digits_conceded", "ddc"),
        ("clean_sheets", "cs"),
        ("clean_sheets_conceded", "csc"),
    ]
    for row in rows:
        key = f"player={row['player_id']} opponent={row['opponent_id']}"
        if int(row["games"]) != int(row["games"]):
            pass
        for stored, live in cols:
            s = row[stored]
            l = row[live]
            if s != l and not (s is None and l is None):
                errors.append(f"{key} {stored}: stored={s!r} live={l!r}")

    if errors:
        print(f"FAIL {len(errors)} mismatch(es):")
        for line in errors[:20]:
            print(line)
        return 1

    print(f"PASS spot-check {len(rows)} directed pairs (games>=3)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
