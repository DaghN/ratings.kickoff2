-- SC-9: L5 module key (stage_id) on standings rows — dual-write with scope_type/scope_key.
-- Policy: docs/amiga-format-scoring-contract-policy.md SC7 / D7
SET time_zone = '+00:00';

ALTER TABLE `amiga_tournament_standings`
  ADD COLUMN `stage_id` int(11) DEFAULT NULL AFTER `scope_key`;

ALTER TABLE `amiga_tournament_standings`
  ADD KEY `idx_amiga_tournament_standings_stage` (`tournament_id`, `stage_id`);

ALTER TABLE `amiga_tournament_standings`
  ADD CONSTRAINT `fk_amiga_tournament_standings_stage`
    FOREIGN KEY (`stage_id`) REFERENCES `tournament_stages` (`id`) ON DELETE SET NULL;