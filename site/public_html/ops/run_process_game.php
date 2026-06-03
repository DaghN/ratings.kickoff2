<?php
/**
 * Dev runner: post-game PHP (no dispatch.php).
 *
 *   php site/public_html/ops/run_process_game.php process-one --game-id 10 --target local-work
 *   php site/public_html/ops/run_process_game.php replay-to --limit 100 --target local-work
 *   php site/public_html/ops/run_process_game.php status-ratedresults --limit 100 --target local-work
 *   php site/public_html/ops/run_process_game.php register-arena --player-id 42 --target local-work
 *
 * See docs/post-game-php-development.md.
 */
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once __DIR__ . '/includes/ops_bootstrap.php';
require_once __DIR__ . '/modules/process_completed_game.php';
require_once __DIR__ . '/modules/process_player_registered.php';
require_once __DIR__ . '/modules/post_game_parity_ratedresults.php';

k2_ops_require_cli();

$verb = $argv[1] ?? '';
if ($verb === '' || str_starts_with($verb, '-')) {
    fwrite(STDERR, "Usage: php run_process_game.php <verb> [options]\n");
    fwrite(STDERR, "Verbs: process-one, replay-to, register-arena, status-ratedresults\n");
    fwrite(STDERR, "Options: --target local-work, --game-id N, --player-id N, --limit N, --until-game-id N, --dry-run\n");
    exit(1);
}

$targetName = 'local-work';
$dryRun = false;
$gameId = null;
$playerId = null;
$limit = null;
$untilGameId = null;

for ($i = 2, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--dry-run') {
        $dryRun = true;
    } elseif ($argv[$i] === '--target' && isset($argv[$i + 1])) {
        $targetName = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--target=')) {
        $targetName = substr($argv[$i], 9);
    } elseif ($argv[$i] === '--game-id' && isset($argv[$i + 1])) {
        $gameId = (int) $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--game-id=')) {
        $gameId = (int) substr($argv[$i], 10);
    } elseif ($argv[$i] === '--player-id' && isset($argv[$i + 1])) {
        $playerId = (int) $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--player-id=')) {
        $playerId = (int) substr($argv[$i], 12);
    } elseif ($argv[$i] === '--limit' && isset($argv[$i + 1])) {
        $limit = (int) $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--limit=')) {
        $limit = (int) substr($argv[$i], 8);
    } elseif ($argv[$i] === '--until-game-id' && isset($argv[$i + 1])) {
        $untilGameId = (int) $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--until-game-id=')) {
        $untilGameId = (int) substr($argv[$i], 16);
    }
}

$target = k2_ops_load_work_target($targetName);
k2_ops_assert_mutate_work_target($target);

if ($verb === 'process-one') {
    if ($gameId === null || $gameId <= 0) {
        fwrite(STDERR, "process-one requires --game-id N\n");
        exit(1);
    }
    $con = k2_ops_connect_work($target);
    try {
        $result = k2_ops_process_completed_game($con, $gameId, $dryRun);
        $d = $result['derived'];
        k2_ops_log(
            '[OK] process-one game_id=' . $gameId
            . ' NewRatingA=' . round((float) $d['NewRatingA'], 3)
            . ' NewRatingB=' . round((float) $d['NewRatingB'], 3)
            . ($result['committed'] ? '' : ' (dry-run)')
        );
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'register-arena') {
    if ($playerId === null || $playerId <= 0) {
        fwrite(STDERR, "register-arena requires --player-id N\n");
        exit(1);
    }
    $con = k2_ops_connect_work($target);
    try {
        $result = k2_ops_process_player_registered($con, $playerId, $dryRun);
        k2_ops_log(
            '[OK] register-arena player_id=' . $playerId
            . ' inserted=' . ($result['entered_arena_inserted'] ? '1' : '0')
            . ($result['committed'] ? '' : ' (dry-run)')
        );
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'replay-to') {
    if ($limit === null && $untilGameId === null) {
        fwrite(STDERR, "replay-to requires --limit N and/or --until-game-id G\n");
        exit(1);
    }
    $con = k2_ops_connect_work($target);
    try {
        $result = k2_ops_replay_post_game($con, $limit, $untilGameId, $dryRun);
        $processed = $result['processed'];
        k2_ops_log(
            '[OK] replay-to games=' . count($processed)
            . ($processed !== [] ? ' last_id=' . $processed[array_key_last($processed)] : '')
            . ' committed=' . $result['committed']
            . ($dryRun ? ' (dry-run)' : '')
        );
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'status-ratedresults') {
    $con = k2_ops_connect_work($target);
    try {
        $cov = k2_ops_ratedresults_derived_coverage($con, $limit, $untilGameId);
        k2_ops_log(
            '[OK] status-ratedresults total=' . $cov['total']
            . ' with_derived=' . $cov['with_derived']
            . ' missing=' . $cov['missing_derived']
            . ($cov['first_missing_id'] !== null ? ' first_missing_id=' . $cov['first_missing_id'] : '')
        );
        exit($cov['missing_derived'] === 0 && $cov['total'] > 0 ? 0 : 1);
    } finally {
        $con->close();
    }
}

fwrite(STDERR, "Unknown verb {$verb}\n");
exit(1);
