<?php
/**
 * Visitor UTC checklist — PHP ini, MySQL session, Status league timing sanity.
 *
 *   php scripts/verify_visitor_utc_clock.php
 *   php scripts/verify_visitor_utc_clock.php --config site/config/ladder-work.ini
 */
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $repoRoot . '/site/public_html';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';

$configPath = $repoRoot . '/site/config/ko2unitydb_config.php';
$iniOverride = null;
for ($i = 1, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--config' && isset($argv[$i + 1])) {
        $iniOverride = $argv[++$i];
        if (!str_contains($iniOverride, '/') && !str_contains($iniOverride, '\\')) {
            $iniOverride = $repoRoot . '/site/config/' . $iniOverride;
        }
        break;
    }
}

if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config: {$configPath}\n");
    exit(1);
}

include $configPath;

if ($iniOverride !== null && is_file($iniOverride)) {
    $ini = parse_ini_file($iniOverride, true, INI_SCANNER_TYPED);
    if (is_array($ini) && isset($ini['database']) && is_array($ini['database'])) {
        $db = $ini['database'];
        if (!empty($db['host'])) {
            $dbhost = (string) $db['host'];
        }
        if (!empty($db['user'])) {
            $username = (string) $db['user'];
        }
        if (array_key_exists('password', $db)) {
            $password = (string) $db['password'];
        }
        if (!empty($db['database'])) {
            $database = (string) $db['database'];
        }
        if (!empty($db['port'])) {
            $dbportnum = (int) $db['port'];
        }
    }
}

$fail = 0;
$pass = static function (string $label) use (&$fail): void {
    echo "[PASS] {$label}\n";
};
$failLine = static function (string $label, string $detail) use (&$fail): void {
    echo "[FAIL] {$label} — {$detail}\n";
    $fail++;
};

echo "=== Visitor UTC checklist ===\n\n";

k2_site_ensure_utc();
$phpTz = date_default_timezone_get();
if ($phpTz === 'UTC') {
    $pass('php_date_timezone=' . $phpTz);
} else {
    $failLine('php_date_timezone', "expected UTC, got {$phpTz} (k2_site_ensure_utc should force UTC)");
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum ?? 3306);
if ($con->connect_errno) {
    $failLine('db_connect', $con->connect_error);
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$r = $con->query("SELECT NOW() AS n, @@session.time_zone AS tz, UNIX_TIMESTAMP(NOW()) AS unix_now");
$row = $r->fetch_assoc();
$r->free();
$sessionTz = (string) ($row['tz'] ?? '');
if ($sessionTz === '+00:00') {
    $pass('mysql_session_time_zone=' . $sessionTz);
} else {
    $failLine('mysql_session_time_zone', $sessionTz);
}

$mysqlNow = (string) ($row['n'] ?? '');
$clock = k2_status_server_clock($con);
$skew = abs($clock['now']->getTimestamp() - (int) ($row['unix_now'] ?? 0));
if ($skew <= 2) {
    $pass("status_server_clock_matches_mysql_now (skew={$skew}s)");
} else {
    $failLine('status_server_clock_skew', "MySQL unix vs PHP clock skew {$skew}s");
}

$utc = new DateTimeZone('UTC');
$week = k2_status_league_period_bounds('day', 0, new DateTimeImmutable('now', $utc));
if ($week !== null && preg_match('/^\d{4}-\d{2}-\d{2} 00:00:00$/', $week['end']) === 1) {
    $pass('league_bounds_end_is_utc_midnight_format');
} else {
    $failLine('league_bounds', 'unexpected end format');
}

$endEpoch = k2_status_league_end_epoch($week);
$endFromFormat = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $week['end'], $utc)->getTimestamp();
if ($endEpoch === $endFromFormat) {
    $pass('k2_status_league_end_epoch_consistent');
} else {
    $failLine('k2_status_league_end_epoch', "got {$endEpoch} expected {$endFromFormat}");
}

$timing = k2_status_league_timing_for_api($con, $week);
if ($timing['end_epoch'] === $endEpoch && $timing['server_now_epoch'] === $clock['now']->getTimestamp()) {
    $pass('league_timing_for_api');
} else {
    $failLine('league_timing_for_api', 'epoch mismatch');
}

$expectedShow = $endEpoch > 0 && $endEpoch <= $clock['now']->getTimestamp();
if ($timing['show_medals'] === $expectedShow) {
    $pass('show_medals=' . ($timing['show_medals'] ? 'true' : 'false') . ' (live day)');
} else {
    $failLine('show_medals', 'mismatch vs end<=now');
}

echo "\nDatabase: {$database} @ {$dbhost}\n";
echo "MySQL NOW(): {$mysqlNow}\n";
echo "PHP now (UTC): " . $clock['now']->format('Y-m-d H:i:s') . "\n";
echo "Session tz label: " . $clock['timezone'] . "\n";

$con->close();

echo "\nPER-003 prod cron: not verifiable from this machine — Steve must run finalize ~00:00:01 UTC on prod.\n";

if ($fail > 0) {
    echo "\n{$fail} check(s) failed.\n";
    exit(2);
}

echo "\nAll automated checks passed.\n";
exit(0);
