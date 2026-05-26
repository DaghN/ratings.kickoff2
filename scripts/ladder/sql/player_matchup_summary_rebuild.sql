-- Rebuild player_matchup_summary from ratedresults.
-- Destructive: truncates and repopulates. Run only through a reviewed wrapper/handoff.

SET time_zone = '+00:00';

TRUNCATE TABLE `player_matchup_summary`;

INSERT INTO `player_matchup_summary`
  (`player_id`, `opponent_id`, `games`, `wins`, `draws`, `losses`, `goals_for`, `goals_against`)
SELECT pid, oid,
       COUNT(*) AS games,
       SUM(w) AS wins, SUM(d) AS draws, SUM(l) AS losses,
       SUM(gf) AS goals_for, SUM(ga) AS goals_against
FROM (
  SELECT idA AS pid, idB AS oid,
         CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS w,
         CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
         CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS l,
         GoalsA AS gf, GoalsB AS ga
  FROM `ratedresults`
  UNION ALL
  SELECT idB AS pid, idA AS oid,
         CASE WHEN ActualScore = 0 THEN 1 ELSE 0 END AS w,
         CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END AS d,
         CASE WHEN ActualScore = 1 THEN 1 ELSE 0 END AS l,
         GoalsB AS gf, GoalsA AS ga
  FROM `ratedresults`
) AS sides
GROUP BY pid, oid;
