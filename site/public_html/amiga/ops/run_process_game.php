<?php
/**
 * Dev runner: Amiga post-game PHP (no dispatch.php).
 *
 * Tournament finalize replay oracle:
 *   python -m scripts.amiga replay
 *   php site/public_html/amiga/ops/run_process_game.php zero-derived
 *   php site/public_html/amiga/ops/run_process_game.php finalize-tournament --tournament-id=N
 *
 * Live (open tournament result entry updates standings only until finalize):
 *   php site/public_html/amiga/ops/run_process_game.php finalize-tournament --tournament-id=N
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/amiga_ops_bootstrap.php';
require_once __DIR__ . '/modules/process_completed_game.php';
require_once __DIR__ . '/modules/finalize_tournament.php';
require_once __DIR__ . '/modules/refinalize_tournament.php';

amiga_ops_require_cli();

function amiga_ops_print_help(): void
{
    fwrite(STDOUT, "Usage: php run_process_game.php <verb> [options]\n");
    fwrite(STDOUT, "Verbs:\n");
    fwrite(STDOUT, "  zero-derived          Clear derived tables incl. realm snapshots (ground kept)\n");
    fwrite(STDOUT, "  finalize-tournament   Batch finalize one tournament (frozen Elo + rating events)\n");
    fwrite(STDOUT, "  reopen-tournament     Clear one tournament's finalize markers + derived rows\n");
    fwrite(STDOUT, "  refinalize-from       Rebuild-forward from tournament T through later events\n");
    fwrite(STDOUT, "  replay-to             Removed — use python -m scripts.amiga replay\n");
    fwrite(STDOUT, "  process-one           Deprecated for tournament games — use finalize-tournament\n");
    fwrite(STDOUT, "  verify                Row counts + derived_gap + standings spot-checks\n");
    fwrite(STDOUT, "  help\n");
    fwrite(STDOUT, "Options:\n");
    fwrite(STDOUT, "  --game-id N           process-one target\n");
    fwrite(STDOUT, "  --tournament-id N     finalize-tournament target\n");
    fwrite(STDOUT, "  --limit N             replay-to (deprecated)\n");
    fwrite(STDOUT, "  --until-game-id G     replay-to (deprecated)\n");
    fwrite(STDOUT, "  --dry-run\n");
    fwrite(STDOUT, "Database: ko2amiga_db only (site/config/ko2amiga_config.local.php)\n");
    fwrite(STDOUT, "\nReplay oracle: python -m scripts.amiga replay\n");
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
$tournamentId = null;
$limit = null;
$untilGameId = null;

for ($i = 2, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--dry-run') {
        $dryRun = true;
    } elseif ($argv[$i] === '--game-id' && isset($argv[$i + 1])) {
        $gameId = (int) $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--game-id=')) {
        $gameId = (int) substr($argv[$i], 10);
    } elseif ($argv[$i] === '--tournament-id' && isset($argv[$i + 1])) {
        $tournamentId = (int) $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--tournament-id=')) {
        $tournamentId = (int) substr($argv[$i], 16);
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

if ($verb === 'finalize-tournament') {
    if ($tournamentId === null || $tournamentId <= 0) {
        fwrite(STDERR, "finalize-tournament requires --tournament-id N\n");
        exit(1);
    }
    $con = amiga_ops_connect();
    try {
        $result = amiga_finalize_tournament($con, $tournamentId, $dryRun);
        amiga_ops_log(
            'finalize-tournament done: id=' . $result['tournament_id']
            . ' games=' . $result['games']
            . (isset($result['rating_events']) ? ' events=' . $result['rating_events'] : '')
            . (!empty($result['skipped']) ? ' skipped' : '')
            . ($dryRun ? ' (dry-run)' : '')
        );
    } catch (AmigaTournamentAlreadyFinalizedException $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    } catch (AmigaTournamentNotFoundException $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    } catch (AmigaFinalizeLockException $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'reopen-tournament') {
    if ($tournamentId === null || $tournamentId <= 0) {
        fwrite(STDERR, "reopen-tournament requires --tournament-id N\n");
        exit(1);
    }
    $con = amiga_ops_connect();
    try {
        $result = amiga_ops_reopen_tournament($con, $tournamentId, $dryRun);
        amiga_ops_log(
            'reopen-tournament done: id=' . $result['tournament_id']
            . (!empty($result['reopened']) ? ' reopened' : ' no-op')
            . ($dryRun ? ' (dry-run)' : '')
        );
    } catch (AmigaTournamentNotFoundException $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'refinalize-from') {
    if ($tournamentId === null || $tournamentId <= 0) {
        fwrite(STDERR, "refinalize-from requires --tournament-id N\n");
        exit(1);
    }
    $con = amiga_ops_connect();
    try {
        $result = amiga_ops_refinalize_from($con, $tournamentId, $dryRun);
        amiga_ops_log(
            'refinalize-from done: id=' . $result['tournament_id']
            . ' from_tournaments=' . ($result['from_tournaments'] ?? 0)
            . ' games=' . ($result['games_finalized'] ?? 0)
            . ($dryRun ? ' (dry-run)' : '')
        );
    } catch (AmigaTournamentNotFoundException $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    } catch (AmigaFinalizeLockException $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'replay-to') {
    fwrite(STDERR, "replay-to removed — use: python -m scripts.amiga replay\n");
    exit(1);
}

if ($verb === 'verify') {
    $con = amiga_ops_connect();
    try {
        $cov = amiga_ops_derived_coverage($con);
        amiga_ops_log(
            'verify: ratings=' . $cov['rating_count']
            . ' stats=' . $cov['stats_count']
            . ' standings=' . $cov['standings_count']
            . ' games=' . $cov['game_count']
            . ' last_rated=' . ($cov['last_rated_game_id'] ?? 'none')
            . ' first_unrated=' . ($cov['first_unrated_game_id'] ?? 'none')
        );
        if ($cov['derived_gap']) {
            amiga_ops_log('ERROR: derived_gap — hole in contract order');
            exit(1);
        }
        if ($cov['rating_count'] > 0 && $cov['standings_count'] < 1) {
            amiga_ops_log('ERROR: standings_count is 0 but ratings exist');
            exit(1);
        }
        $standingsErrors = amiga_ops_verify_standings_spot_checks($con);
        if ($standingsErrors !== []) {
            foreach ($standingsErrors as $err) {
                amiga_ops_log('ERROR: standings ' . $err);
            }
            exit(1);
        }
        amiga_ops_log('verify OK (counts + standings spot-checks)');
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
            $reason = (string) ($result['skip_reason'] ?? 'unknown');
            amiga_ops_log('process-one game_id=' . $gameId . ' skipped reason=' . $reason);
            if ($reason === 'tournament_use_finalize') {
                fwrite(STDERR, "Tournament games require finalize-tournament — not per-game global commit.\n");
                exit(1);
            }
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
