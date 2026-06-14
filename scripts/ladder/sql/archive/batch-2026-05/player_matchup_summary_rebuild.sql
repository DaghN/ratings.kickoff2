-- Rebuild player_matchup_summary from ratedresults (SCH-008 core + SCH-019 extension).
-- Destructive: truncates and repopulates. Run only through a reviewed wrapper/handoff.

SET time_zone = '+00:00';

TRUNCATE TABLE `player_matchup_summary`;

INSERT INTO `player_matchup_summary`
  (`player_id`, `opponent_id`, `games`, `wins`, `draws`, `losses`, `goals_for`, `goals_against`,
   `max_goals_for`, `max_goals_against`, `min_goals_for`, `min_goals_against`,
   `max_win_margin`, `max_loss_margin`, `max_draw_goals`, `max_goal_sum`, `min_goal_sum`,
   `double_digits`, `double_digits_conceded`, `clean_sheets`, `clean_sheets_conceded`)
SELECT pid, oid,
       COUNT(*) AS games,
       SUM(w) AS wins, SUM(d) AS draws, SUM(l) AS losses,
       SUM(gf) AS goals_for, SUM(ga) AS goals_against,
       MAX(gf) AS max_goals_for,
       MAX(ga) AS max_goals_against,
       MIN(gf) AS min_goals_for,
       MIN(ga) AS min_goals_against,
       MAX(CASE WHEN w > 0 THEN gf - ga END) AS max_win_margin,
       MAX(CASE WHEN l > 0 THEN ga - gf END) AS max_loss_margin,
       MAX(CASE WHEN d > 0 THEN gf END) AS max_draw_goals,
       MAX(gf + ga) AS max_goal_sum,
       MIN(gf + ga) AS min_goal_sum,
       SUM(dd) AS double_digits,
       SUM(ddc) AS double_digits_conceded,
       SUM(cs) AS clean_sheets,
       SUM(csc) AS clean_sheets_conceded
FROM (
  SELECT idA AS pid, idB AS oid,
         CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS w,
         CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
         CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS l,
         GoalsA AS gf, GoalsB AS ga,
         DDPlayerA AS dd, DDPlayerB AS ddc, CSPlayerA AS cs, CSPlayerB AS csc
  FROM `ratedresults`
  UNION ALL
  SELECT idB AS pid, idA AS oid,
         CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS w,
         CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
         CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS l,
         GoalsB AS gf, GoalsA AS ga,
         DDPlayerB AS dd, DDPlayerA AS ddc, CSPlayerB AS cs, CSPlayerA AS csc
  FROM `ratedresults`
) AS sides
GROUP BY pid, oid;
