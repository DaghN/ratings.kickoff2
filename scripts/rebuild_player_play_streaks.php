<?php
/**
 * Local CLI: REP-015 — rebuild player_play_streaks + HoF columns from player_period_games.
 *
 *   php scripts/rebuild_player_play_streaks.php
 *
 * Prerequisites: SCH-014 applied; player_period_games up to date (REP-003).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$repoRoot = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $repoRoot . '/site/public_html';

$configPath = dirname($_SERVER['DOCUMENT_ROOT']) . '/config/ko2unitydb_config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config: {$configPath}\n");
    exit(1);
}

include $configPath;

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum ?? 3306);
if ($con->connect_errno) {
    fwrite(STDERR, 'DB connect failed: ' . $con->connect_error . PHP_EOL);
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$res = $con->query("SHOW TABLES LIKE 'player_play_streaks'");
if ($res === false || $res->num_rows === 0) {
  if ($res) {
      $res->free();
  }
    fwrite(STDERR, "Apply schema/migrations/014_player_play_streaks.sql first.\n");
    exit(1);
}
$res->free();

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_play_streaks.php';

echo "Rebuilding player_play_streaks (UTC day/week/month/year from player_period_games)...\n";
$written = k2_play_streak_rebuild_all($con);
echo "Rows written (player × type): {$written}\n";

$check = $con->query(
    "SELECT streak_type, MAX(best_streak) AS mx, COUNT(*) AS n "
    . "FROM player_play_streaks GROUP BY streak_type"
);
if ($check) {
    while ($row = $check->fetch_assoc()) {
        echo "  {$row['streak_type']}: rows={$row['n']} max_best={$row['mx']}\n";
    }
    $check->free();
}

$hof = $con->query(
    'SELECT LongestDailyPlayStreak, LongestDailyPlayStreakID, '
    . 'LongestWeeklyPlayStreak, LongestWeeklyPlayStreakID, '
    . 'LongestMonthlyPlayStreak, LongestMonthlyPlayStreakID, '
    . 'LongestYearlyPlayStreak, LongestYearlyPlayStreakID '
    . 'FROM generalstatstable WHERE id = 1'
);
if ($hof) {
    $h = $hof->fetch_assoc();
    $hof->free();
    if ($h) {
        echo "HoF daily: {$h['LongestDailyPlayStreak']} (player {$h['LongestDailyPlayStreakID']})\n";
        echo "HoF weekly: {$h['LongestWeeklyPlayStreak']} (player {$h['LongestWeeklyPlayStreakID']})\n";
        echo "HoF monthly: {$h['LongestMonthlyPlayStreak']} (player {$h['LongestMonthlyPlayStreakID']})\n";
        echo "HoF yearly: {$h['LongestYearlyPlayStreak']} (player {$h['LongestYearlyPlayStreakID']})\n";
    }
}

mysqli_close($con);
echo "Done.\n";
