-- REP-015: player_play_streaks is rebuilt via PHP (establishing-game = MIN id per period).
-- Run: php scripts/rebuild_player_play_streaks.php (local)
--      php staging-scripts/run_player_play_streaks_rebuild.php (staging)
--
-- Requires player_period_games (REP-003) and SCH-014 schema.

SET time_zone = '+00:00';

-- Intentionally no-op in mysql client; use the PHP rebuild entrypoints above.
