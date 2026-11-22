-- Tournament lifecycle ground truth (draft through archived/void).
SET time_zone = '+00:00';

ALTER TABLE `tournaments`
  ADD COLUMN `lifecycle_status` enum(
    'draft',
    'registration',
    'ready',
    'running',
    'completed',
    'archived',
    'void'
  ) NOT NULL DEFAULT 'draft' AFTER `has_cup`;

ALTER TABLE `tournaments`
  ADD COLUMN `started_at` datetime DEFAULT NULL AFTER `lifecycle_status`;

ALTER TABLE `tournaments`
  ADD COLUMN `completed_at` datetime DEFAULT NULL AFTER `started_at`;

ALTER TABLE `tournaments`
  ADD KEY `idx_tournaments_lifecycle_status` (`lifecycle_status`);

-- One-time backfill when the column is first added (every row then holds the 'draft'
-- default). Simul re-applies this bundle on every run, so each UPDATE below must only
-- touch rows still at 'draft'; explicit statuses written later (organizer flow, or
-- forward catalog-tail appends such as World Cup XXIV) must survive re-application.

-- Historical Access imports: finished events with canonical games.
UPDATE `tournaments`
SET
  `lifecycle_status` = 'completed',
  `completed_at` = TIMESTAMP(`event_date`)
WHERE `source_id` IS NOT NULL
  AND `lifecycle_status` = 'draft';

-- Generated fixture-backed tournaments with at least one game: treat as in progress.
UPDATE `tournaments` t
SET
  `lifecycle_status` = 'running',
  `started_at` = (
    SELECT MIN(g.game_date)
    FROM amiga_games g
    WHERE g.tournament_id = t.id
  )
WHERE t.source_id IS NULL
  AND t.lifecycle_status = 'draft'
  AND EXISTS (SELECT 1 FROM amiga_games g WHERE g.tournament_id = t.id);

-- Generated tournaments where every fixture is played: mark completed.
UPDATE `tournaments` t
SET
  `lifecycle_status` = 'completed',
  `completed_at` = COALESCE(
    t.completed_at,
    (
      SELECT MAX(g.game_date)
      FROM amiga_games g
      WHERE g.tournament_id = t.id
    )
  )
WHERE t.source_id IS NULL
  AND EXISTS (
    SELECT 1
    FROM tournament_stages s
    INNER JOIN tournament_fixtures f ON f.stage_id = s.id
    WHERE s.tournament_id = t.id
  )
  AND NOT EXISTS (
    SELECT 1
    FROM tournament_stages s
    INNER JOIN tournament_fixtures f ON f.stage_id = s.id
    WHERE s.tournament_id = t.id
      AND f.status = 'scheduled'
  );
