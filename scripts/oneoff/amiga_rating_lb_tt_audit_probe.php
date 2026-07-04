<?php
declare(strict_types=1);
/**
 * F6 Phase 0 audit - Amiga rating LB TT hot path per-phase timings.
 *
 * Measures every blocking DB phase between hub nav and wing nav (hub chapter)
 * on /amiga/leaderboards/rating.php for present + TT cutoffs.
 *
 * Usage: php scripts/oneoff/amiga_rating_lb_tt_audit_probe.php
 */

require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_participation_step_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $start): float
{
    return round((microtime(true) - $start) * 1000, 1);
}

/** @return array{0: mixed, 1: float} result + elapsed ms */
function timed(callable $fn): array
{
    $t0 = microtime(true);
    $result = $fn();

    return [$result, ms($t0)];
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$scenarios = [
    'present'          => null,
    'month:2014-07'    => 'month:2014-07',
    'event:22'         => 'event:22',
    'event:589'        => 'event:589',
    'month:2025-09'    => 'month:2025-09',
    'year:2014'        => 'year:2014',
];

$rows = [];

foreach ($scenarios as $label => $as) {
    if ($as === null) {
        unset($_GET['as']);
    } else {
        $_GET['as'] = $as;
    }
    amiga_snapshot_context_reset();

    $r = ['scenario' => $label];

    // Phase 1: header snapshot chrome context (site_header -> amiga_snapshot_chrome).
    // Cold per request: tournaments list + wing catalog.
    [$ctx, $r['ctx_build']] = timed(fn () => amiga_snapshot_context_from_request($con));

    // Phase 1b: event-wing chrome extras (eligible players for layout + as-with listbox).
    // Static-cached within one process; measure cold via reflection-free trick: only first
    // scenario pays it, note separately below.

    // Phase 2: main career query (rating.php line 29) - full select, fetch all.
    [$careerRows, $r['career_query']] = timed(function () use ($con, $ctx) {
        $res = amiga_lb_query_career(
            $con,
            $ctx,
            'SELECT p.id AS ID, p.name AS Name, s.Rating, s.NumberGames, s.NumberWins, s.NumberDraws, s.NumberLosses, '
            . 's.WinRatio, s.DrawRatio, s.LossRatio, s.AverageOpponentRating, p.country AS Country ',
            'ORDER BY s.Rating DESC'
        );
        $n = 0;
        while ($res->fetch_assoc()) {
            $n++;
        }
        mysqli_free_result($res);

        return $n;
    });
    $r['career_rows'] = $careerRows;

    // Phase 3: games count (rating.php footer).
    [, $r['games_count']] = timed(fn () => amiga_lb_games_count($con, $ctx));

    // Phase 4: delta map - full call as rating.php does it.
    if ($ctx->isActive()) {
        [, $r['delta_map']] = timed(fn () => amiga_lb_rating_delta_map($con, $ctx));
    } else {
        [, $r['delta_map']] = timed(fn () => amiga_lb_wc_start_rating_delta_map($con));
    }

    // Phase 4 breakdown (TT only): current ladder scan / prev ladder scan / participants.
    if ($ctx->isActive()) {
        $view = amiga_snapshot_resolve_catalog_view($con, $ctx->wing(), $ctx->key());
        $entry = $view['entry'];
        [, $r['d_ladder_now']] = timed(fn () => amiga_rating_history_ladder_at_cutoff(
            $con,
            (string) $entry['cutoff_event_date'],
            (float) $entry['cutoff_chrono'],
            (int) $entry['cutoff_tournament_id']
        ));
        $prevEntry = amiga_rating_history_catalog_entry_by_key($view['catalog'], $view['prev_key']);
        if ($prevEntry !== null && $prevEntry['cutoff_tournament_id'] !== null) {
            [, $r['d_ladder_prev']] = timed(fn () => amiga_rating_history_ladder_at_cutoff(
                $con,
                (string) $prevEntry['cutoff_event_date'],
                (float) $prevEntry['cutoff_chrono'],
                (int) $prevEntry['cutoff_tournament_id']
            ));
        } else {
            $r['d_ladder_prev'] = 0.0;
        }
        if ($ctx->wing() === 'event') {
            [, $r['d_participants']] = timed(fn () => amiga_rating_history_event_participant_ids(
                $con,
                (int) $entry['cutoff_tournament_id']
            ));
        } else {
            $r['d_participants'] = 0.0;
        }
    } else {
        $r['d_ladder_now'] = 0.0;
        $r['d_ladder_prev'] = 0.0;
        $r['d_participants'] = 0.0;
    }

    // Phase 5: hub chapter lede counts (amiga_lb_nav -> amiga_lb_chapter_lede_html_for_request)
    // - runs games count AGAIN plus tournament count, on its own extra connection.
    [, $r['lede_games_count']] = timed(fn () => amiga_lb_games_count($con, $ctx));
    [, $r['lede_tournament_count']] = timed(fn () => amiga_tournament_index_count($con, $ctx));

    $r['block_total'] = round(
        $r['career_query'] + $r['games_count'] + $r['delta_map']
        + $r['lede_games_count'] + $r['lede_tournament_count'],
        1
    );

    $rows[] = $r;
}

// Event-wing chrome extras (once, static-cached): eligible players list.
amiga_snapshot_context_reset();
[, $eligibleMs] = timed(fn () => amiga_participation_eligible_players($con));

// Extra-connection cost the lede pays (connect + charset + tz).
[$c2, $connectMs] = timed(function () use ($dbhost, $username, $password, $database, $dbportnum) {
    $c = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    $c->set_charset('utf8mb4');
    $c->query("SET time_zone = '+00:00'");

    return $c;
});
$c2->close();

$cols = [
    'scenario', 'ctx_build', 'career_query', 'career_rows', 'games_count', 'delta_map',
    'd_ladder_now', 'd_ladder_prev', 'd_participants',
    'lede_games_count', 'lede_tournament_count', 'block_total',
];
echo implode("\t", $cols) . "\n";
foreach ($rows as $r) {
    $line = [];
    foreach ($cols as $c) {
        $line[] = (string) ($r[$c] ?? '');
    }
    echo implode("\t", $line) . "\n";
}
echo "\n";
echo "eligible_players (event-wing chrome, once per request): {$eligibleMs} ms\n";
echo "extra DB connect (lede 3rd connection): {$connectMs} ms\n";

$con->close();
echo "OK\n";