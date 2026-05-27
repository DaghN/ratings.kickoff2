-- Rebuild player_milestones from ratedresults.
-- Destructive: truncates and repopulates. Run only through a reviewed wrapper/handoff.

SET time_zone = '+00:00';

TRUNCATE TABLE `player_milestones`;

-- established_20: first game where cumulative count reaches 20
-- Uses ROW_NUMBER (not user variables) so MariaDB staging matches MySQL local.
INSERT INTO `player_milestones` (`player_id`, `milestone_key`, `achieved_at`, `value`)
SELECT player_id, 'established_20', `Date`, 20
FROM (
  SELECT pid AS player_id, `Date`,
         ROW_NUMBER() OVER (PARTITION BY pid ORDER BY `Date` ASC, game_id ASC) AS game_num
  FROM (
    SELECT id AS game_id, idA AS pid, `Date` FROM ratedresults
    UNION ALL
    SELECT id, idB, `Date` FROM ratedresults
  ) AS appearances
) AS ranked
WHERE game_num = 20;

-- dd_merchant_10: first game where a player scored 10+ goals
INSERT INTO `player_milestones` (`player_id`, `milestone_key`, `achieved_at`, `value`)
SELECT pid, 'dd_merchant_10', MIN(`Date`) AS achieved_at, 10
FROM (
  SELECT idA AS pid, `Date`, GoalsA AS goals FROM ratedresults WHERE GoalsA >= 10
  UNION ALL
  SELECT idB AS pid, `Date`, GoalsB AS goals FROM ratedresults WHERE GoalsB >= 10
) AS big_scores
GROUP BY pid;
