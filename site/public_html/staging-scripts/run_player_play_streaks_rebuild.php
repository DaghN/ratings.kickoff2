<?php
/**
 * Staging one-shot: REP-015 — rebuild player_play_streaks + HoF play-streak columns.
 *
 * Prerequisites:
 *   - staging-sql/014_player_play_streaks.sql applied (SCH-014)
 *   - player_period_games populated (REP-003, incl. week)
 *   - includes/player_play_streaks.php uploaded
 *
 * Run from public_html:
 *   php staging-scripts/run_player_play_streaks_rebuild.php
 */
declare(strict_types=1);

require_once __DIR__ . '/_staging_play_streaks_bootstrap.php';

$con = k2_staging_play_streaks_bootstrap();

if (!k2_staging_table_exists($con, 'player_play_streaks')) {
    fwrite(STDERR, "Table player_play_streaks missing — apply staging-sql/014_player_play_streaks.sql first.\n");
    exit(1);
}

$ppg = $con->query("SELECT COUNT(*) AS n FROM player_period_games WHERE period_type = 'day'");
$row = $ppg ? $ppg->fetch_assoc() : null;
if ($ppg) {
    $ppg->free();
}
if ((int) ($row['n'] ?? 0) < 1) {
    fwrite(STDERR, "player_period_games is empty — run REP-003 first.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/player_play_streaks.php';

echo "REP-015: rebuilding player_play_streaks...\n";
$written = k2_play_streak_rebuild_all($con);
echo "Rows written (player × type): {$written}\n";

$check = $con->query(
    "SELECT streak_type, MAX(best_streak) AS mx, COUNT(*) AS n "
    . "FROM player_play_streaks GROUP BY streak_type"
);
if ($check) {
    while ($r = $check->fetch_assoc()) {
        echo "  {$r['streak_type']}: rows={$r['n']} max_best={$r['mx']}\n";
    }
    $check->free();
}

$hof = $con->query(
    'SELECT LongestDailyPlayStreak, LongestDailyPlayStreakID, LongestDailyPlayStreakGameID, '
    . 'LongestWeeklyPlayStreak, LongestWeeklyPlayStreakID, LongestWeeklyPlayStreakGameID '
    . 'FROM generalstatstable WHERE id = 1'
);
if ($hof) {
    $h = $hof->fetch_assoc();
    $hof->free();
    if ($h) {
        echo "HoF daily: streak={$h['LongestDailyPlayStreak']} player={$h['LongestDailyPlayStreakID']} game={$h['LongestDailyPlayStreakGameID']}\n";
        echo "HoF weekly: streak={$h['LongestWeeklyPlayStreak']} player={$h['LongestWeeklyPlayStreakID']} game={$h['LongestWeeklyPlayStreakGameID']}\n";
    }
}

mysqli_close($con);
echo "OK\n";
