-- Metric-first indexes for Games hub highlights ORDER BY scans (Track L).
ALTER TABLE `amiga_game_ratings`
  ADD INDEX `idx_amiga_game_ratings_sum_goals` (`sum_of_goals`, `game_id`),
  ADD INDEX `idx_amiga_game_ratings_goal_diff` (`goal_difference`, `game_id`);