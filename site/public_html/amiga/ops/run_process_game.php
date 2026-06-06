<?php
/**
 * Dev runner: Amiga post-game PHP (no dispatch.php).
 *
 * Parity gate (500 games):
 *   python -m scripts.amiga replay --limit 500
 *   php site/public_html/amiga/ops/run_process_game.php zero-derived
 *   php site/public_html/amiga/ops/run_process_game.php replay-to --limit 500
 *   php site/public_html/amiga/ops/run_process_game.php verify
 *
 * Live (append-only last game):
 *   php site/public_html/amiga/ops/run_process_game.php process-one --game-id=N
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/amiga_ops_bootstrap.php';
require_once __DIR__ . '/modules/process_completed_game.php';

amiga_ops_require_cli();

function amiga_ops_print_help(): void
{
    fwrite(STDOUT, "Usage: php run_process_game.php <verb> [options]\n");
    fwrite(STDOUT, "Verbs:\n");
    fwrite(STDOUT, "  zero-derived          Clear amiga_game_ratings + amiga_player_stats (ground kept)\n");
    fwrite(STDOUT, "  replay-to             Sim loop in contract chronology order\n");
    fwrite(STDOUT, "  process-one           Live append-only: one game at end of history\n");
    fwrite(STDOUT, "  verify                Row counts + derived_gap probe\n");
    fwrite(STDOUT, "  help\n");
    fwrite(STDOUT, "Options:\n");
    fwrite(STDOUT, "  --game-id N           process-one target\n");
    fwrite(STDOUT, "  --limit N             replay-to: max games to walk\n");
    fwrite(STDOUT, "  --until-game-id G     replay-to: stop after game G (inclusive)\n");
    fwrite(STDOUT, "  --dry-run\n");
    fwrite(STDOUT, "Database: ko2amiga_db only (site/config/ko2amiga_config.local.php)\n");
    fwrite(STDOUT, "\nParity gate (v1): python -m scripts.amiga replay --limit 500;\n");
    fwrite(STDOUT, "  zero-derived -> replay-to --limit 500 -> verify (counts + no derived_gap)\n");
}

$verb = $argv[1] ?? '';
if ($verb === '' || str_starts_with($verb, '-')) {
    amiga_ops_print_help();
    exit(1);
}
if ($verb === 'help') {
    amiga_ops_print_help();
    exit(0);
}

$dryRun = false;
$gameId = null;
$limit = null;
$untilGameId = null;

for ($i = 2, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--dry-run') {
        $dryRun = true;
    } elseif ($argv[$i] === '--game-id' && isset($argv[$i + 1])) {
        $gameId = (int) $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--game-id=')) {
        $gameId = (int) substr($argv[$i], 10);
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

if ($verb === 'zero-derived') {
    $con = amiga_ops_connect();
    try {
        amiga_ops_zero_derived($con, $dryRun);
        amiga_ops_log('zero-derived done' . ($dryRun ? ' (dry-run)' : ''));
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'replay-to') {
    $con = amiga_ops_connect();
    try {
        $result = amiga_ops_replay_post_game($con, $limit, $untilGameId, $dryRun);
        $processed = $result['processed'];
        $skipped = $result['skipped'];
        $skipReasons = $result['skip_reasons'];
        amiga_ops_log(
            'replay-to done: processed=' . count($processed)
            . ($processed !== [] ? ' last_id=' . $processed[array_key_last($processed)] : '')
            . ' committed=' . $result['committed']
            . ' skipped=' . count($skipped)
            . ($dryRun ? ' (dry-run)' : '')
        );
        if ($skipped !== []) {
            $reasonParts = [];
            foreach ($skipReasons as $gid => $reason) {
                $reasonParts[] = "{$gid}:{$reason}";
            }
            amiga_ops_log('skip_reasons: ' . implode(', ', $reasonParts));
            $bad = array_filter(
                $skipReasons,
                static fn (string $r): bool => $r !== 'already_processed'
            );
            if ($bad !== []) {
                amiga_ops_log('ERROR: unexpected skip reasons (e.g. derived_gap)');
                exit(1);
            }
        }
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'verify') {
    $con = amiga_ops_connect();
    try {
        $cov = amiga_ops_derived_coverage($con);
        amiga_ops_log(
            'verify: ratings=' . $cov['rating_count']
            . ' stats=' . $cov['stats_count']
            . ' games=' . $cov['game_count']
            . ' last_rated=' . ($cov['last_rated_game_id'] ?? 'none')
            . ' first_unrated=' . ($cov['first_unrated_game_id'] ?? 'none')
        );
        if ($cov['derived_gap']) {
            amiga_ops_log('ERROR: derived_gap — hole in contract order');
            exit(1);
        }
        amiga_ops_log('verify OK (row counts; compare to python -m scripts.amiga replay for parity)');
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'process-one') {
    if ($gameId === null || $gameId <= 0) {
        fwrite(STDERR, "process-one requires --game-id N\n");
        exit(1);
    }
    $con = amiga_ops_connect();
    try {
        $result = amiga_process_completed_game($con, $gameId, $dryRun, false);
        if (!empty($result['skipped'])) {
            amiga_ops_log(
                'process-one game_id=' . $gameId
                . ' skipped reason=' . ($result['skip_reason'] ?? 'unknown')
            );
            exit(0);
        }
        $d = $result['derived'];
        amiga_ops_log(
            'process-one game_id=' . $gameId
            . ' new_rating_a=' . round((float) $d['new_rating_a'], 3)
            . ' new_rating_b=' . round((float) $d['new_rating_b'], 3)
            . ($result['committed'] ? ' committed' : ' (dry-run)')
        );
    } finally {
        $con->close();
    }
    exit(0);
}

fwrite(STDERR, "Unknown verb: {$verb}\n");
exit(1);
