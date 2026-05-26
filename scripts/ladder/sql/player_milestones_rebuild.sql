-- Rebuild player_milestones from ratedresults.
-- Destructive: truncates and repopulates. Run only through a reviewed wrapper/handoff.

SET time_zone = '+00:00';

TRUNCATE TABLE `player_milestones`;

-- established_20: first game where cumulative count reaches 20
INSERT INTO `player_milestones` (`player_id`, `milestone_key`, `achieved_at`, `value`)
SELECT pid, 'established_20', achieved_at, 20
FROM (
  SELECT pid, MIN(`Date`) AS achieved_at
  FROM (
    SELECT pid, `Date`, @rn := IF(@prev = pid, @rn + 1, 1) AS cumulative, @prev := pid
    FROM (
      SELECT idA AS pid, `Date` FROM ratedresults
      UNION ALL
      SELECT idB AS pid, `Date` FROM ratedresults
    ) AS all_appearances
    ORDER BY pid, `Date`
  ) AS numbered
  CROSS JOIN (SELECT @rn := 0, @prev := 0) AS vars_unused
  WHERE cumulative >= 20
  GROUP BY pid
) AS milestone_20;

-- dd_merchant_10: first game where a player scored 10+ goals
INSERT INTO `player_milestones` (`player_id`, `milestone_key`, `achieved_at`, `value`)
SELECT pid, 'dd_merchant_10', MIN(`Date`) AS achieved_at, 10
FROM (
  SELECT idA AS pid, `Date`, GoalsA AS goals FROM ratedresults WHERE GoalsA >= 10
  UNION ALL
  SELECT idB AS pid, `Date`, GoalsB AS goals FROM ratedresults WHERE GoalsB >= 10
) AS big_scores
GROUP BY pid;
