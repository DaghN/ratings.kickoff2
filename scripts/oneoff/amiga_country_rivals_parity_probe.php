<?php
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_country_rivals_load.php';
require __DIR__ . '/../../site/public_html/includes/amiga_country_rivals_perf_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) { fwrite(STDERR, "connect fail\n"); exit(1); }
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$heroes = ['England', 'Germany', 'Italy'];
$cutoffs = ['present' => '', 'event:22' => 'event:22', 'event:589' => 'event:589', 'month:2014-07' => 'month:2014-07', 'month:2025-09' => 'month:2025-09', 'year:2024' => 'year:2024'];
$statKeys = ['games', 'wins', 'draws', 'losses', 'goals_for', 'goals_against', 'double_digits', 'double_digits_conceded', 'clean_sheets', 'clean_sheets_conceded'];
$fail = 0;

foreach ($cutoffs as $label => $as) {
    if ($as !== '') { $_GET['as'] = $as; } else { unset($_GET['as']); }
    $GLOBALS['_amiga_snapshot_context'] = null;
    $ctx = amiga_lb_context($con);

    foreach ($heroes as $hero) {
        $pairRows = amiga_country_rivals_pair_rows($con, $hero, $ctx);
        $rollup = amiga_country_rivals_filter_cross_border_rows(
            amiga_country_rivals_rollup_from_pair_rows($pairRows),
            $hero
        );
        $withPerf = amiga_country_rivals_rows($con, $hero, $ctx, true);

        if (count($rollup) !== count($withPerf)) {
            echo "FAIL count $hero @ $label: rollup=" . count($rollup) . ' withPerf=' . count($withPerf) . "\n";
            $fail++;
            continue;
        }

        $byToken = [];
        foreach ($withPerf as $row) {
            $byToken[(string) $row['rival_token']] = $row;
        }

        foreach ($rollup as $bucket) {
            $token = (string) $bucket['rival_token'];
            $full = $byToken[$token] ?? null;
            if ($full === null) {
                echo "FAIL missing rival $token $hero @ $label\n";
                $fail++;
                continue;
            }
            foreach ($statKeys as $key) {
                if ((int) ($bucket[$key] ?? -1) !== (int) ($full[$key] ?? -2)) {
                    echo "FAIL stat $key $hero vs $token @ $label: {$bucket[$key]} != {$full[$key]}\n";
                    $fail++;
                }
            }
        }

        if ($rollup !== []) {
            $sample = (string) $rollup[0]['rival_token'];
            $batch = amiga_country_rivals_perf_ratings_batch($con, $hero, $ctx);
            $pair = amiga_country_rivals_perf_ratings_for_pair($con, $hero, $sample, $ctx);
            $batchRow = $batch[$sample] ?? null;
            if ($batchRow === null) {
                echo "FAIL perf batch missing $hero vs $sample @ $label\n";
                $fail++;
            } else {
                foreach (['games', 'performance_rating', 'performance_rating_vs_hero'] as $pkey) {
                    if (($batchRow[$pkey] ?? null) !== ($pair[$pkey] ?? null)) {
                        echo "FAIL perf $pkey $hero vs $sample @ $label: batch={$batchRow[$pkey]} pair={$pair[$pkey]}\n";
                        $fail++;
                    }
                }
            }
        }
    }
}

$con->close();
if ($fail > 0) {
    echo "PARITY FAIL ($fail checks)\n";
    exit(1);
}
echo "PARITY OK\n";