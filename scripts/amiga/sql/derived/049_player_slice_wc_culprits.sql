-- World Cup player slice — culprits network columns (WC-scoped).
-- Policy: docs/amiga-world-cups-player-slice-v2-policy.md (Opponents wing extension)

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_slice_totals`
  ADD COLUMN `different_culprits` smallint(6) NOT NULL DEFAULT 0 AFTER `clean_sheets_victims`,
  ADD COLUMN `double_digits_culprits` smallint(6) NOT NULL DEFAULT 0 AFTER `different_culprits`,
  ADD COLUMN `clean_sheets_culprits` smallint(6) NOT NULL DEFAULT 0 AFTER `double_digits_culprits`;

ALTER TABLE `amiga_player_slice_at_event`
  ADD COLUMN `different_culprits` smallint(6) NOT NULL DEFAULT 0 AFTER `clean_sheets_victims`,
  ADD COLUMN `double_digits_culprits` smallint(6) NOT NULL DEFAULT 0 AFTER `different_culprits`,
  ADD COLUMN `clean_sheets_culprits` smallint(6) NOT NULL DEFAULT 0 AFTER `double_digits_culprits`;