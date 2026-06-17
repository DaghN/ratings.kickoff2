-- SCH-026: Per-player match-result streak boundaries (personal-best run game ids + dates).
-- Register: docs/coordination/schema-register.md
-- Contract: docs/website-data-contract.md § player_result_streaks

SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `player_result_streaks` (
  `player_id` int(11) NOT NULL,
  `streak_type` enum('win','draw','loss','non_win','non_draw','non_loss') NOT NULL,
  `best_streak` int(11) NOT NULL DEFAULT 0,
  `best_start_game_id` int(11) DEFAULT NULL COMMENT 'First game in personal-best run',
  `best_end_game_id` int(11) DEFAULT NULL COMMENT 'Last game in personal-best run',
  `best_start_at` datetime DEFAULT NULL COMMENT 'ratedresults.Date of best_start_game_id',
  `best_end_at` datetime DEFAULT NULL COMMENT 'ratedresults.Date of best_end_game_id',
  `current_run_start_game_id` int(11) DEFAULT NULL COMMENT 'Writer: start of active run (post-game)',
  PRIMARY KEY (`player_id`, `streak_type`),
  KEY `idx_player_result_streaks_best` (`streak_type`, `best_streak`, `best_end_at`, `player_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
