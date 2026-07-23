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
require_once __DIR__ . '/modules/delete_unfinalized_tournament.php';
require_once __DIR__ . '/modules/delete_last_finalized_tournament.php';
require_once __DIR__ . '/modules/delete_finalized_mid_tournament.php';
require_once __DIR__ . '/modules/insert_finalized_mid_tournament.php';
require_once __DIR__ . '/modules/project_present_at.php';

amiga_ops_require_cli();

function amiga_ops_print_help(): void
{
    fwrite(STDOUT, "Usage: php run_process_game.php <verb> [options]\n");
    fwrite(STDOUT, "Verbs:\n");
    fwrite(STDOUT, "  zero-derived          Clear derived tables incl. realm snapshots (ground kept)\n");
    fwrite(STDOUT, "  finalize-tournament   Batch finalize one tournament (frozen Elo + rating events)\n");
    fwrite(STDOUT, "  delete-unfinalized-tournament  Case A: delete never-official generated kitchen\n");
    fwrite(STDOUT, "  delete-last-finalized-tournament  Case B: delete tip + project-present-at prior (no seal)\n");
    fwrite(STDOUT, "  delete-finalized-mid-tournament  Case C: truncate > N, delete M, reset forward (no project/seal)\n");
    fwrite(STDOUT, "  truncate-derived-after  Case C step: clear §5.3 derived for chrono > --tournament-id (N)\n");
    fwrite(STDOUT, "  refinalize-forward-from  Case C: finalize one pending forward --tournament-id\n");
    fwrite(STDOUT, "  repair-insert-catalog-chrono  Fix mid-history M chrono for tournaments.php index order\n");
    fwrite(STDOUT, "  project-present-at    Rebuild present tables at --tournament-id cutoff\n");
    fwrite(STDOUT, "  replay-to             Removed — use python -m scripts.amiga prove\n");
    fwrite(STDOUT, "  process-one           Deprecated for tournament games — use finalize-tournament\n");
    fwrite(STDOUT, "  verify                Row counts + derived_gap + standings spot-checks\n");
    fwrite(STDOUT, "  help\n");
    fwrite(STDOUT, "Options:\n");
    fwrite(STDOUT, "  --game-id N           process-one target\n");
    fwrite(STDOUT, "  --tournament-id N     finalize / Case A/B/C / project-present-at / truncate cutoff\n");
    fwrite(STDOUT, "  --limit N             replay-to (deprecated)\n");
    fwrite(STDOUT, "  --until-game-id G     replay-to (deprecated)\n");
    fwrite(STDOUT, "  --dry-run\n");
    fwrite(STDOUT, "  --apply              Case C delete: required to mutate (else dry-run)\n");
    fwrite(STDOUT, "Database: ko2amiga_db or ko2amiga_work (site/config/ko2amiga_config.local.php)\n");
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
$apply = false;
$gameId = null;
$tournamentId = null;
$limit = null;
$untilGameId = null;

for ($i = 2, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--dry-run') {
        $dryRun = true;
    } elseif ($argv[$i] === '--apply') {
        $apply = true;
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

if ($verb === 'delete-unfinalized-tournament') {
    if ($tournamentId === null || $tournamentId <= 0) {
        fwrite(STDERR, "delete-unfinalized-tournament requires --tournament-id N\n");
        exit(1);
    }
    $con = amiga_ops_connect();
    try {
        $result = amiga_delete_unfinalized_tournament($con, $tournamentId, $dryRun);
        if (!$result['ok']) {
            fwrite(STDERR, 'Case A refuse: ' . $result['error'] . PHP_EOL);
            exit(1);
        }
        amiga_ops_log(
            'delete-unfinalized-tournament done: id=' . $result['tournament_id']
            . ' name=' . $result['name']
            . ' games=' . $result['games_deleted']
            . ' orphans=' . count($result['orphan_players_deleted'])
            . ($dryRun ? ' (dry-run)' : '')
        );
        // Case A is not tip-changing — no auto-seal.
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'delete-last-finalized-tournament') {
    if ($tournamentId === null || $tournamentId <= 0) {
        fwrite(STDERR, "delete-last-finalized-tournament requires --tournament-id N\n");
        exit(1);
    }
    $con = amiga_ops_connect();
    try {
        $result = amiga_delete_last_finalized_tournament($con, $tournamentId, $dryRun);
        if (!$result['ok']) {
            fwrite(STDERR, 'Case B refuse: ' . $result['error'] . PHP_EOL);
            exit(1);
        }
        amiga_ops_log(
            'delete-last-finalized-tournament done: id=' . $result['tournament_id']
            . ' name=' . $result['name']
            . ' prior=' . $result['prior_tournament_id']
            . ' games=' . $result['games_deleted']
            . ' orphans=' . count($result['orphan_players_deleted'])
            . ($dryRun ? ' (dry-run)' : '')
        );
        if (!$dryRun) {
            fwrite(STDOUT, "NOTE: Case B does not seal here — use admin backup page or "
                . "amiga_backup_seal_write_from_config after success (AD6).\n");
        }
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'delete-finalized-mid-tournament') {
    if ($tournamentId === null || $tournamentId <= 0) {
        fwrite(STDERR, "delete-finalized-mid-tournament requires --tournament-id M\n");
        exit(1);
    }
    // Default dry-run; --apply required to mutate (local work smoke).
    $caseCDry = !$apply || $dryRun;
    $con = amiga_ops_connect();
    try {
        $result = amiga_delete_finalized_mid_tournament($con, $tournamentId, $caseCDry);
        if (!$result['ok']) {
            fwrite(STDERR, 'Case C refuse: ' . $result['error'] . PHP_EOL);
            exit(1);
        }
        amiga_ops_log(
            'delete-finalized-mid-tournament: M=' . $result['tournament_id']
            . ' name=' . $result['name']
            . ' N=' . $result['cutoff_id'] . ' (' . $result['cutoff_name'] . ')'
            . ' truncate_ids=[' . implode(',', $result['truncated_ids']) . ']'
            . ' remaining_forward=[' . implode(',', $result['remaining_forward_ids']) . ']'
            . ' games=' . $result['games_deleted']
            . ($caseCDry ? ' (dry-run)' : '')
        );
        if ($caseCDry) {
            fwrite(STDOUT, "Planned steps: truncate derived for ids > N; delete M ground; "
                . "reset remaining forward; then project-present-at N; "
                . "refinalize-forward-from each remaining; seal (AD6).\n");
            fwrite(STDOUT, "Re-run with --apply to mutate (prefer ko2amiga_work).\n");
        } else {
            fwrite(STDOUT, "NOTE: Case C phase 1 only — next: project-present-at --tournament-id="
                . $result['cutoff_id'] . " then refinalize-forward-from for each remaining id, then seal.\n");
        }
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'truncate-derived-after') {
    if ($tournamentId === null || $tournamentId <= 0) {
        fwrite(STDERR, "truncate-derived-after requires --tournament-id N (cutoff)\n");
        exit(1);
    }
    $con = amiga_ops_connect();
    try {
        $nRow = amiga_case_b_load_tournament($con, $tournamentId);
        if ($dryRun || !$apply) {
            $fwd = amiga_case_c_list_tournaments_after($con, $nRow, false);
            $ids = array_map(static fn (array $r): int => (int) $r['id'], $fwd);
            amiga_ops_log(
                'truncate-derived-after dry-run: N=' . $tournamentId
                . ' would clear ids=[' . implode(',', $ids) . ']'
            );
            fwrite(STDOUT, "Re-run with --apply to mutate.\n");
            exit(0);
        }
        $ids = amiga_ops_truncate_derived_after($con, $nRow);
        amiga_ops_log(
            'truncate-derived-after done: N=' . $tournamentId
            . ' cleared=[' . implode(',', $ids) . ']'
        );
    } catch (Throwable $e) {
        fwrite(STDERR, 'truncate-derived-after failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'refinalize-forward-from') {
    if ($tournamentId === null || $tournamentId <= 0) {
        fwrite(STDERR, "refinalize-forward-from requires --tournament-id T (pending forward)\n");
        exit(1);
    }
    if ($dryRun) {
        fwrite(STDOUT, "refinalize-forward-from dry-run: would finalize tournament_id={$tournamentId}\n");
        exit(0);
    }
    $con = amiga_ops_connect();
    try {
        $result = amiga_ops_refinalize_forward_one($con, $tournamentId);
        if (!$result['ok']) {
            fwrite(STDERR, 'refinalize-forward-from refuse: ' . $result['error'] . PHP_EOL);
            exit(1);
        }
        amiga_ops_log(
            'refinalize-forward-from done: id=' . $result['tournament_id']
            . ' name=' . $result['name']
            . ' games=' . $result['games']
            . ($result['skipped'] ? ' skipped' : '')
            . ' next_id=' . $result['next_id']
        );
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'repair-insert-catalog-chrono') {
    if ($tournamentId === null || $tournamentId <= 0) {
        fwrite(STDERR, "repair-insert-catalog-chrono requires --tournament-id M\n");
        exit(1);
    }
    if ($dryRun || !$apply) {
        fwrite(STDOUT, "repair-insert-catalog-chrono dry-run: would recompute catalog chrono for M={$tournamentId}\n");
        fwrite(STDOUT, "Re-run with --apply to mutate.\n");
        exit(0);
    }
    $con = amiga_ops_connect();
    try {
        $result = amiga_case_c_insert_repair_catalog_chrono($con, $tournamentId);
        if (!$result['ok']) {
            fwrite(STDERR, 'repair-insert-catalog-chrono refuse: ' . $result['error'] . PHP_EOL);
            exit(1);
        }
        amiga_ops_log(
            'repair-insert-catalog-chrono: id=' . $result['tournament_id']
            . ' old=' . ($result['old_chrono'] !== null ? (string) $result['old_chrono'] : 'null')
            . ' new=' . ($result['new_chrono'] !== null ? (string) $result['new_chrono'] : 'null')
            . ($result['changed'] ? ' changed' : ' unchanged')
        );
        if ($result['changed']) {
            fwrite(STDOUT, "Catalog chrono repaired for #{$tournamentId}: "
                . ($result['old_chrono'] !== null ? (string) $result['old_chrono'] : 'null')
                . ' → '
                . ($result['new_chrono'] !== null ? (string) $result['new_chrono'] : 'null')
                . PHP_EOL);
        } else {
            fwrite(STDOUT, "Catalog chrono already correct for #{$tournamentId}.\n");
        }
    } finally {
        $con->close();
    }
    exit(0);
}

if ($verb === 'project-present-at') {
    if ($tournamentId === null || $tournamentId <= 0) {
        fwrite(STDERR, "project-present-at requires --tournament-id N\n");
        exit(1);
    }
    if ($dryRun) {
        fwrite(STDOUT, "project-present-at dry-run: would rebuild present at cutoff={$tournamentId}\n");
        exit(0);
    }
    $con = amiga_ops_connect();
    try {
        $result = amiga_ops_project_present_at($con, $tournamentId);
        amiga_ops_log(
            'project-present-at done: cutoff=' . $result['cutoff_tournament_id']
            . ' current=' . $result['player_current']
            . ' matchups=' . $result['matchup_summary']
            . ' player_slice=' . $result['player_slice_totals']
            . ' country_slice=' . $result['country_slice_totals']
            . ' gst=' . ($result['generalstats'] ? '1' : '0')
            . ' community=' . ($result['community_stats'] ? '1' : '0')
            . ' wc_hof=' . ($result['wc_hof_present'] ? '1' : '0')
        );
    } catch (Throwable $e) {
        fwrite(STDERR, 'project-present-at failed: ' . $e->getMessage() . PHP_EOL);
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
