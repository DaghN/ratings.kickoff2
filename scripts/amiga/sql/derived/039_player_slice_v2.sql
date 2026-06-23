-- World Cup player slice V2 — goals texture, DDs/CSs, opponents network + geo.
-- Policy: docs/amiga-world-cups-player-slice-v2-policy.md

SET time_zone = '+00:00';

ALTER TABLE `amiga_player_slice_totals`
  ADD COLUMN `goal_ratio` decimal(10,8) DEFAULT NULL AFTER `points`,
  ADD COLUMN `most_goals_scored` tinyint(4) NOT NULL DEFAULT 0 AFTER `goal_ratio`,
  ADD COLUMN `most_goals_conceded` tinyint(4) NOT NULL DEFAULT 0 AFTER `most_goals_scored`,
  ADD COLUMN `biggest_win_difference` tinyint(4) NOT NULL DEFAULT 0 AFTER `most_goals_conceded`,
  ADD COLUMN `biggest_loss_difference` tinyint(4) NOT NULL DEFAULT 0 AFTER `biggest_win_difference`,
  ADD COLUMN `biggest_sum_of_goals` tinyint(4) NOT NULL DEFAULT 0 AFTER `biggest_loss_difference`,
  ADD COLUMN `biggest_draw_sum` tinyint(4) NOT NULL DEFAULT 0 AFTER `biggest_sum_of_goals`,
  ADD COLUMN `double_digits` mediumint(9) NOT NULL DEFAULT 0 AFTER `biggest_draw_sum`,
  ADD COLUMN `clean_sheets` mediumint(9) NOT NULL DEFAULT 0 AFTER `double_digits`,
  ADD COLUMN `double_digits_ratio` decimal(5,4) DEFAULT NULL AFTER `clean_sheets`,
  ADD COLUMN `clean_sheets_ratio` decimal(5,4) DEFAULT NULL AFTER `double_digits_ratio`,
  ADD COLUMN `double_digits_conceded` mediumint(9) NOT NULL DEFAULT 0 AFTER `clean_sheets_ratio`,
  ADD COLUMN `clean_sheets_conceded` mediumint(9) NOT NULL DEFAULT 0 AFTER `double_digits_conceded`,
  ADD COLUMN `double_digits_conceded_ratio` decimal(5,4) DEFAULT NULL AFTER `clean_sheets_conceded`,
  ADD COLUMN `clean_sheets_conceded_ratio` decimal(5,4) DEFAULT NULL AFTER `double_digits_conceded_ratio`,
  ADD COLUMN `opponent_countries_faced` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `clean_sheets_conceded_ratio`,
  ADD COLUMN `opponent_countries_beaten` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `opponent_countries_faced`,
  ADD COLUMN `different_opponents` smallint(6) NOT NULL DEFAULT 0 AFTER `opponent_countries_beaten`,
  ADD COLUMN `different_victims` smallint(6) NOT NULL DEFAULT 0 AFTER `different_opponents`,
  ADD COLUMN `double_digits_victims` smallint(6) NOT NULL DEFAULT 0 AFTER `different_victims`,
  ADD COLUMN `clean_sheets_victims` smallint(6) NOT NULL DEFAULT 0 AFTER `double_digits_victims`;

ALTER TABLE `amiga_player_slice_at_event`
  ADD COLUMN `goal_ratio` decimal(10,8) DEFAULT NULL AFTER `points`,
  ADD COLUMN `most_goals_scored` tinyint(4) NOT NULL DEFAULT 0 AFTER `goal_ratio`,
  ADD COLUMN `most_goals_conceded` tinyint(4) NOT NULL DEFAULT 0 AFTER `most_goals_scored`,
  ADD COLUMN `biggest_win_difference` tinyint(4) NOT NULL DEFAULT 0 AFTER `most_goals_conceded`,
  ADD COLUMN `biggest_loss_difference` tinyint(4) NOT NULL DEFAULT 0 AFTER `biggest_win_difference`,
  ADD COLUMN `biggest_sum_of_goals` tinyint(4) NOT NULL DEFAULT 0 AFTER `biggest_loss_difference`,
  ADD COLUMN `biggest_draw_sum` tinyint(4) NOT NULL DEFAULT 0 AFTER `biggest_sum_of_goals`,
  ADD COLUMN `double_digits` mediumint(9) NOT NULL DEFAULT 0 AFTER `biggest_draw_sum`,
  ADD COLUMN `clean_sheets` mediumint(9) NOT NULL DEFAULT 0 AFTER `double_digits`,
  ADD COLUMN `double_digits_ratio` decimal(5,4) DEFAULT NULL AFTER `clean_sheets`,
  ADD COLUMN `clean_sheets_ratio` decimal(5,4) DEFAULT NULL AFTER `double_digits_ratio`,
  ADD COLUMN `double_digits_conceded` mediumint(9) NOT NULL DEFAULT 0 AFTER `clean_sheets_ratio`,
  ADD COLUMN `clean_sheets_conceded` mediumint(9) NOT NULL DEFAULT 0 AFTER `double_digits_conceded`,
  ADD COLUMN `double_digits_conceded_ratio` decimal(5,4) DEFAULT NULL AFTER `clean_sheets_conceded`,
  ADD COLUMN `clean_sheets_conceded_ratio` decimal(5,4) DEFAULT NULL AFTER `double_digits_conceded_ratio`,
  ADD COLUMN `opponent_countries_faced` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `clean_sheets_conceded_ratio`,
  ADD COLUMN `opponent_countries_beaten` smallint(5) unsigned NOT NULL DEFAULT 0 AFTER `opponent_countries_faced`,
  ADD COLUMN `different_opponents` smallint(6) NOT NULL DEFAULT 0 AFTER `opponent_countries_beaten`,
  ADD COLUMN `different_victims` smallint(6) NOT NULL DEFAULT 0 AFTER `different_opponents`,
  ADD COLUMN `double_digits_victims` smallint(6) NOT NULL DEFAULT 0 AFTER `different_victims`,
  ADD COLUMN `clean_sheets_victims` smallint(6) NOT NULL DEFAULT 0 AFTER `double_digits_victims`;
