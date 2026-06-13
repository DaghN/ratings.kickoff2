-- Tournament structure slice 1: collapse tournament_stages.stage_type to round_robin | knockout.
-- Apply after 022: mysql ko2amiga_db < scripts/amiga/sql/023_unify_stage_types.sql
-- Policy: docs/amiga-tournament-structure-policy.md T1–T5 · Plan: docs/amiga-tournament-structure-implementation-plan.md slice 1

SET time_zone = '+00:00';

-- 1) Expand enum so new types are storable alongside legacy values.
ALTER TABLE `tournament_stages`
  MODIFY COLUMN `stage_type`
    enum('league','group','knockout','placement','other','round_robin') NOT NULL;

-- 2) Migrate RR modules (league/group → round_robin).
UPDATE `tournament_stages`
  SET `stage_type` = 'round_robin'
  WHERE `stage_type` IN ('league', 'group');

-- 3) Migrate KO modules (placement/other → knockout).
UPDATE `tournament_stages`
  SET `stage_type` = 'knockout'
  WHERE `stage_type` IN ('placement', 'other');

-- 4) Shrink enum to target module types only.
ALTER TABLE `tournament_stages`
  MODIFY COLUMN `stage_type`
    enum('round_robin','knockout') NOT NULL;
