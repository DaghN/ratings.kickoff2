-- SC-11: structured L3 match extensions (ET / penalties). Witness text stays in `extra`.
-- Policy: docs/amiga-format-scoring-contract-policy.md SC11
SET time_zone = '+00:00';

ALTER TABLE `amiga_games`
  ADD COLUMN `goals_et_a` smallint(5) unsigned DEFAULT NULL AFTER `extra`,
  ADD COLUMN `goals_et_b` smallint(5) unsigned DEFAULT NULL AFTER `goals_et_a`,
  ADD COLUMN `pens_a` smallint(5) unsigned DEFAULT NULL AFTER `goals_et_b`,
  ADD COLUMN `pens_b` smallint(5) unsigned DEFAULT NULL AFTER `pens_a`;

ALTER TABLE `tournament_fixtures`
  ADD COLUMN `goals_et_a` smallint(5) unsigned DEFAULT NULL AFTER `extra`,
  ADD COLUMN `goals_et_b` smallint(5) unsigned DEFAULT NULL AFTER `goals_et_a`,
  ADD COLUMN `pens_a` smallint(5) unsigned DEFAULT NULL AFTER `goals_et_b`,
  ADD COLUMN `pens_b` smallint(5) unsigned DEFAULT NULL AFTER `pens_a`;