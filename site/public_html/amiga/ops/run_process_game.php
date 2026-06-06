<?php
/**
 * Dev runner: Amiga post-game PHP (no dispatch.php).
 *
 *   php site/public_html/amiga/ops/run_process_game.php process-one --game-id=27408
 *   php site/public_html/amiga/ops/run_process_game.php help
 *
 * v1 append-only: --game-id must be the chronologically last game in amiga_games.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/amiga_ops_bootstrap.php';
require_once __DIR__ . '/modules/process_completed_game.php';

amiga_ops_require_cli();

$verb = $argv[1] ?? '';
if ($verb === '' || $verb === 'help' || str_starts_with($verb, '-')) {
    fwrite(STDOUT, "Usage: php run_process_game.php <verb> [options]\n");
    fwrite(STDOUT, "Verbs: process-one, help\n");
    fwrite(STDOUT, "Options: --game-id N, --dry-run\n");
    fwrite(STDOUT, "Database: ko2amiga_db only (site/config/ko2amiga_config.local.php)\n");
    exit($verb === 'help' ? 0 : 1);
}

$dryRun = false;
$gameId = null;

for ($i = 2, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--dry-run') {
        $dryRun = true;
    } elseif ($argv[$i] === '--game-id' && isset($argv[$i + 1])) {
        $gameId = (int) $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--game-id=')) {
        $gameId = (int) substr($argv[$i], 10);
    }
}

if ($verb === 'process-one') {
    if ($gameId === null || $gameId <= 0) {
        fwrite(STDERR, "process-one requires --game-id N\n");
        exit(1);
    }
    $con = amiga_ops_connect();
    try {
        $result = amiga_process_completed_game($con, $gameId, $dryRun);
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
