<?php
declare(strict_types=1);
/** Parity: slim amiga_lb_rating_delta_map vs legacy resolve_view ladder deltas. */

require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_lb_snapshot_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$fail = 0;
foreach (['month:2014-07', 'event:22', 'event:589', 'month:2025-09', 'year:2014', 'month:2002-06', 'year:2001'] as $as) {
    $_GET['as'] = $as;
    amiga_snapshot_context_reset();
    $ctx = amiga_snapshot_context_from_request($con);

    $new = amiga_lb_rating_delta_map($con, $ctx);

    $view = amiga_rating_history_resolve_from_context($con, $ctx);
    $old = [];
    foreach ($view['ladder'] as $row) {
        $old[(int) $row['player_id']] = (float) $row['rating_delta'];
    }

    ksort($new);
    ksort($old);
    $ok = $new === $old;
    if (!$ok) {
        $fail++;
        $onlyNew = array_diff_key($new, $old);
        $onlyOld = array_diff_key($old, $new);
        $diffVals = 0;
        foreach ($old as $pid => $v) {
            if (isset($new[$pid]) && abs($new[$pid] - $v) > 0.0001) {
                $diffVals++;
            }
        }
        echo "{$as}: MISMATCH new=" . count($new) . " old=" . count($old)
            . " onlyNew=" . count($onlyNew) . " onlyOld=" . count($onlyOld) . " diffVals={$diffVals}\n";
    } else {
        echo "{$as}: OK (" . count($new) . " players)\n";
    }
}

$con->close();
echo $fail === 0 ? "ALL OK\n" : "FAILURES: {$fail}\n";
exit($fail === 0 ? 0 : 1);