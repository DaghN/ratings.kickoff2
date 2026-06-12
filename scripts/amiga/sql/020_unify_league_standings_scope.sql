-- Standings scope unification slice 0: merge overall+group → league; enum league|knockout only.
-- Apply after 019: mysql ko2amiga_db < scripts/amiga/sql/020_unify_league_standings_scope.sql
-- Policy: docs/amiga-standings-scope-policy.md · Plan: docs/amiga-standings-scope-implementation-plan.md slice 0

SET time_zone = '+00:00';

-- 1) Expand enum so 'league' is storable alongside legacy values.
ALTER TABLE `amiga_tournament_standings`
  MODIFY COLUMN `scope_type`
    enum('overall','group','placement','knockout','league') NOT NULL DEFAULT 'overall';

-- 2) Migrate points-table rows to league (scope_key unchanged).
UPDATE `amiga_tournament_standings`
  SET `scope_type` = 'league'
  WHERE `scope_type` IN ('overall', 'group');

-- 3) Legacy placement scope rows (if any) → knockout per policy S3.
UPDATE `amiga_tournament_standings`
  SET `scope_type` = 'knockout'
  WHERE `scope_type` = 'placement';

-- 4) Shrink enum to target primitives only.
ALTER TABLE `amiga_tournament_standings`
  MODIFY COLUMN `scope_type`
    enum('league','knockout') NOT NULL DEFAULT 'league';

-- 5) Catalog stats column rename (count of distinct league scope keys).
ALTER TABLE `amiga_tournament_catalog_stats`
  CHANGE COLUMN `group_scopes` `league_scopes` int(11) NOT NULL DEFAULT 0;
