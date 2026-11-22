<?php

declare(strict_types=1);

/**
 * Self-test for in-order game processing (site/public_html/ops/modules/process_pending_games.php).
 *
 *   php scripts/test_ops_game_queue.php [--engine=MyISAM|InnoDB] [--max-id=260] [--keep]
 *
 * Copies the first games from ko2unity_work (read only) into the throwaway database ko2unity_gaptest,
 * runs each scenario there and compares every table with a full recalculation of the same games.
 * Drops the throwaway database at the end.
 */

const TEST_DB = 'ko2unity_gaptest';
const SOURCE_DB = 'ko2unity_work';

$public = dirname(__DIR__) . '/site/public_html';
$_SERVER['DOCUMENT_ROOT'] = $public;
require_once $public . '/ops/includes/ops_bootstrap.php';
require_once $public . '/ops/includes/ops_reset_universe.php';
require_once $public . '/ops/modules/process_pending_games.php';
k2_ops_require_cli();

$opt = ['engine' => 'MyISAM', 'max-id' => '260', 'keep' => false, 'worker' => null];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m) && array_key_exists($m[1], $opt)) {
        $opt[$m[1]] = $m[2] ?? true;
    }
}

function t_connect(string $db): mysqli
{
    $con = new mysqli('127.0.0.1', 'root', '', $db);
    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");

    return $con;
}

/** Run silently; ops code logs every step to stdout. */
function t_quiet(callable $fn): mixed
{
    require_once dirname(__DIR__) . '/site/public_html/ops/includes/ops_dispatch.php';
    k2_ops_dispatch_http_begin();
    try {
        return $fn();
    } finally {
        K2OpsDispatchHttpContext::$enabled = false;
    }
}

function t_ids(mysqli $con): array
{
    return array_map('intval', array_column(
        $con->query('SELECT id FROM ratedresults ORDER BY `Date`, id')->fetch_all(MYSQLI_ASSOC),
        'id'
    ));
}

// One parallel caller: what the web entry point does for one game.
if ($opt['worker'] !== null) {
    $con = t_connect(TEST_DB);
    $result = t_quiet(function () use ($con, $opt) {
        if (!k2_ops_game_queue_lock($con, 60)) {
            return ['status' => 'busy'];
        }

        return k2_ops_process_game_in_order($con, (int) $opt['worker']);
    });
    echo $result['status'] . ($result['reason'] ?? null ? ' ' . $result['reason'] : '') . PHP_EOL;
    exit(0);
}

$admin = t_connect(SOURCE_DB);
$admin->query("SET SESSION sql_mode = ''");
$admin->query('DROP DATABASE IF EXISTS ' . TEST_DB);
$admin->query('CREATE DATABASE ' . TEST_DB . ' CHARACTER SET utf8mb4');
$tables = array_column($admin->query(
    "SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . SOURCE_DB . "' ORDER BY 1"
)->fetch_all(MYSQLI_ASSOC), 't');
foreach ($tables as $t) {
    $admin->query('CREATE TABLE ' . TEST_DB . ".`$t` LIKE `$t`");
    $admin->query('ALTER TABLE ' . TEST_DB . ".`$t` ENGINE=" . $opt['engine']);
}
$admin->query('INSERT INTO ' . TEST_DB . '.playertable SELECT * FROM playertable');
$admin->query('INSERT INTO ' . TEST_DB . '.milestone_definitions SELECT * FROM milestone_definitions');
$admin->query('INSERT INTO ' . TEST_DB . '.ratedresults SELECT * FROM ratedresults WHERE id <= ' . (int) $opt['max-id']);

$con = t_connect(TEST_DB);
$ids = t_ids($con);
$n = count($ids);
echo "engine={$opt['engine']} games=$n days="
    . $con->query('SELECT COUNT(DISTINCT DATE(`Date`)) AS d FROM ratedresults')->fetch_assoc()['d'] . PHP_EOL;

function t_reset(mysqli $con): void
{
    t_quiet(function () use ($con) {
        k2_ops_reset_universe($con, false);
        k2_ops_truncate_aggregate_tables($con, false);
    });
    $con->query('UPDATE milestone_definitions SET holder_count = 0');
}

/** @return array<string, list<string>> every table as sorted row texts */
function t_dump(mysqli $con, array $tables): array
{
    $out = [];
    foreach ($tables as $t) {
        $rows = [];
        $res = $con->query("SELECT * FROM `$t`");
        while ($row = $res->fetch_assoc()) {
            unset($row['finalized_at']); // clock time of the run, differs by nature
            $rows[] = json_encode(array_values($row), JSON_INVALID_UTF8_SUBSTITUTE);
        }
        sort($rows);
        $out[$t] = $rows;
    }

    return $out;
}

$failures = 0;
function t_check(string $label, array $want, array $got): void
{
    global $failures;
    $bad = [];
    foreach ($want as $t => $rows) {
        $diff = count(array_diff($rows, $got[$t])) + count(array_diff($got[$t], $rows));
        if ($diff > 0 || count($rows) !== count($got[$t])) {
            $bad[] = "$t($diff)";
        }
    }
    $failures += $bad === [] ? 0 : 1;
    echo ($bad === [] ? 'PASS ' : 'FAIL ') . $label . ($bad === [] ? '' : ' -> ' . implode(' ', $bad)) . PHP_EOL;
}

function t_assert(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    $failures += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . ($ok ? '' : " -> $detail") . PHP_EOL;
}

/**
 * Live day-to-day: a call per game, midnight run at each day change.
 *
 * @param callable(int):bool $callArrives does the call for the game at this position get through?
 * @param callable(int):bool $cronRuns does the midnight run before the game at this position happen?
 */
function t_live(mysqli $con, array $ids, callable $callArrives, callable $cronRuns): void
{
    t_quiet(function () use ($con, $ids, $callArrives, $cronRuns) {
        $previous = null;
        foreach ($ids as $i => $id) {
            // The game after the next one is not in the database yet: hide it by date, as live would.
            $row = k2_ops_load_rated_game_row($con, $id);
            $day = gmdate('Y-m-d', (int) $row['date_utc_ts']);
            if ($previous !== null && $day > $previous && $cronRuns($i)) {
                k2_ops_finalize_utc_day_after_pending($con, k2_ops_timeline_as_of_after_utc_day($previous), $previous);
            }
            $previous = $day;
            if ($callArrives($i)) {
                k2_ops_process_game_in_order($con, $id);
            }
        }
        // Closing sweep, as the cron safety net would do; then the last day stays open as in the reference.
        k2_ops_process_pending_games($con);
    });
}

$stopAt = (new DateTimeImmutable('@' . $con->query('SELECT UNIX_TIMESTAMP(MAX(`Date`)) AS t FROM ratedresults')
    ->fetch_assoc()['t']))->setTimezone(new DateTimeZone('UTC'));
$always = static fn (int $i): bool => true;
$a = intdiv($n * 2, 5);
$b = intdiv($n * 3, 5);
$outside = static fn (int $i): bool => $i < $a || $i >= $b;

t_reset($con);
t_quiet(fn () => k2_ops_timeline_sim_run($con, $stopAt));
$want = t_dump($con, $tables);
t_reset($con);
t_quiet(fn () => k2_ops_timeline_sim_run($con, $stopAt));
t_check('control: full recalculation twice', $want, t_dump($con, $tables));

t_reset($con);
t_live($con, $ids, $always, $always);
t_check('normal days', $want, t_dump($con, $tables));

t_reset($con);
t_live($con, $ids, $outside, $always);
t_check("outage: calls for games $a-$b lost, midnight run works", $want, t_dump($con, $tables));

t_reset($con);
t_live($con, $ids, $outside, $outside);
t_check("outage: calls for games $a-$b lost and no midnight run", $want, t_dump($con, $tables));

t_reset($con);
t_live($con, $ids, static fn (int $i): bool => $i % 7 !== 3, $always);
t_check('every seventh call lost', $want, t_dump($con, $tables));

t_reset($con);
t_live($con, $ids, static fn (int $i): bool => false, $always);
t_check('no calls at all, midnight run only', $want, t_dump($con, $tables));

// The comparison must notice the mistake this code prevents: missed games processed late.
t_reset($con);
t_quiet(function () use ($con, $ids, $outside) {
    foreach ([true, false] as $first) {
        foreach ($ids as $i => $id) {
            if ($outside($i) === $first) {
                k2_ops_process_completed_game($con, $id);
            }
        }
    }
});
$late = t_dump($con, $tables);
t_assert(
    'late processing gives wrong player totals, and the comparison sees it',
    $want['playertable'] !== $late['playertable'] && $want['ratedresults'] !== $late['ratedresults']
);

// Time limit: a web request may not finish a backlog; repeated calls do.
t_reset($con);
$calls = t_quiet(function () use ($con, $ids) {
    $calls = 0;
    do {
        ++$calls;
        $run = k2_ops_process_pending_games($con, null, null, 0.5);
    } while ($run['remaining'] > 0 && $calls < 1000);

    return $calls;
});
t_check("backlog cleared over several time-limited calls ($calls)", $want, t_dump($con, $tables));
t_assert('time limit really cut the work up', $calls > 1, "calls=$calls");

// A call for a game that was overtaken before this code existed is refused, not processed late.
t_reset($con);
t_quiet(function () use ($con, $ids) {
    k2_ops_process_completed_game($con, $ids[0]);
    k2_ops_process_completed_game($con, $ids[2]);
});
$before = t_dump($con, $tables);
$r = t_quiet(fn () => k2_ops_process_game_in_order($con, $ids[1]));
t_assert('overtaken game is refused', $r['status'] === 'refused', json_encode($r));
t_check('... and nothing was written', $before, t_dump($con, $tables));
t_assert('... and it is listed as a hole', k2_ops_game_queue_holes($con) === [$ids[1]], json_encode(k2_ops_game_queue_holes($con)));
$r = t_quiet(fn () => k2_ops_process_game_in_order($con, $ids[2]));
t_assert('repeated call is a harmless duplicate', $r['status'] === 'skipped' && $r['reason'] === 'already_processed', json_encode($r));

// A ladder that was just zeroed must not be rebuilt by accident through a game call.
t_reset($con);
try {
    t_quiet(fn () => k2_ops_process_pending_games($con, null, null, null, 100));
    t_assert('backlog over the limit is refused', false, 'no exception');
} catch (RuntimeException $e) {
    t_assert('backlog over the limit is refused', str_contains($e->getMessage(), 'over the limit'), $e->getMessage());
}

// A database fault in one game: nothing may overtake it.
t_reset($con);
$cut = 100;
t_quiet(function () use ($con, $ids, $cut) {
    k2_ops_process_pending_games($con, $ids[$cut - 1]);
});
$before = t_dump($con, $tables);
$con->query("CREATE TRIGGER zz_i BEFORE INSERT ON server_period_matchups FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='simulated fault'");
$con->query("CREATE TRIGGER zz_u BEFORE UPDATE ON server_period_matchups FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='simulated fault'");
$message = '';
try {
    t_quiet(fn () => k2_ops_process_game_in_order($con, $ids[$cut + 1]));
} catch (Throwable $e) {
    $message = $e->getMessage();
}
$con->query('DROP TRIGGER zz_i');
$con->query('DROP TRIGGER zz_u');
$done = (int) $con->query('SELECT COUNT(*) AS c FROM ratedresults WHERE NewRatingA IS NOT NULL')->fetch_assoc()['c'];
if ($opt['engine'] === 'InnoDB') {
    t_assert('fault: the call fails', str_contains($message, 'simulated fault'), $message);
    t_check('fault: the failed game is undone completely and nothing overtook it', $before, t_dump($con, $tables));
    t_quiet(fn () => k2_ops_process_pending_games($con));
    t_check('fault: the next call catches up', $want, t_dump($con, $tables));
} else {
    t_assert('fault: reported as half-processed', str_contains($message, 'HALF-PROCESSED'), $message);
    t_assert('fault: nothing overtook the failed game', $done === $cut + 1, "processed=$done");
}

// Many calls at the same moment, in random order.
t_reset($con);
t_quiet(fn () => k2_ops_process_pending_games($con, $ids[$n - 41]));
$last = array_slice($ids, $n - 40);
mt_srand(7);
for ($i = count($last) - 1; $i > 0; --$i) {
    $j = mt_rand(0, $i);
    [$last[$i], $last[$j]] = [$last[$j], $last[$i]];
}
foreach (array_chunk(array_merge($last, $last), 8) as $chunk) {
    $procs = [];
    foreach ($chunk as $id) {
        $pipes = [];
        $procs[] = proc_open([PHP_BINARY, __FILE__, "--worker=$id"], [1 => ['pipe', 'w']], $pipes);
    }
    foreach ($procs as $p) {
        proc_close($p);
    }
}
t_check('40 calls, sent twice, eight at a time in random order', $want, t_dump($con, $tables));

$con->close();
if (!$opt['keep']) {
    $admin->query('DROP DATABASE ' . TEST_DB);
}
echo $failures === 0 ? "ALL PASS\n" : "FAILURES: $failures\n";
exit($failures === 0 ? 0 : 1);
