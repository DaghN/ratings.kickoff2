-- Cumulative directed H2H at each tournament finalize (policy: amiga-matchup-at-event-policy.md).
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `amiga_player_matchup_at_event` (
  `player_id` int(11) NOT NULL,
  `opponent_id` int(11) NOT NULL,
  `as_of_tournament_id` int(11) NOT NULL,
  `event_date` date NOT NULL,
  `event_chrono` int(11) NOT NULL,
  `games` smallint(5) unsigned NOT NULL DEFAULT 0,
  `wins` smallint(5) unsigned NOT NULL DEFAULT 0,
  `draws` smallint(5) unsigned NOT NULL DEFAULT 0,
  `losses` smallint(5) unsigned NOT NULL DEFAULT 0,
  `goals_for` smallint(5) unsigned NOT NULL DEFAULT 0,
  `goals_against` smallint(5) unsigned NOT NULL DEFAULT 0,
  `max_goals_for` smallint(5) unsigned NOT NULL DEFAULT 0,
  `max_goals_against` smallint(5) unsigned NOT NULL DEFAULT 0,
  `min_goals_for` smallint(5) unsigned NOT NULL DEFAULT 0,
  `min_goals_against` smallint(5) unsigned NOT NULL DEFAULT 0,
  `max_win_margin` smallint(5) unsigned NULL DEFAULT NULL,
  `max_loss_margin` smallint(5) unsigned NULL DEFAULT NULL,
  `max_draw_goals` smallint(5) unsigned NULL DEFAULT NULL,
  `max_goal_sum` smallint(5) unsigned NOT NULL DEFAULT 0,
  `min_goal_sum` smallint(5) unsigned NOT NULL DEFAULT 0,
  `dd_wins` smallint(5) unsigned NOT NULL DEFAULT 0,
  `dd_losses` smallint(5) unsigned NOT NULL DEFAULT 0,
  `cs_wins` smallint(5) unsigned NOT NULL DEFAULT 0,
  `cs_losses` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`player_id`, `opponent_id`, `as_of_tournament_id`),
  KEY `idx_matchup_at_event_player_chrono` (`player_id`, `event_date`, `event_chrono`, `as_of_tournament_id`),
  KEY `idx_matchup_at_event_tournament` (`as_of_tournament_id`, `player_id`),
  CONSTRAINT `fk_matchup_at_event_player`
    FOREIGN KEY (`player_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_matchup_at_event_opponent`
    FOREIGN KEY (`opponent_id`) REFERENCES `amiga_players` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_matchup_at_event_tournament`
    FOREIGN KEY (`as_of_tournament_id`) REFERENCES `tournaments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
