<?php
declare(strict_types=1);
require_once __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_tournament_step_catalog.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_participation_step_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function facet_counts_legacy(mysqli $con, array $catalog, array $filterBag): array
{
    $facetBag = [
        'player_id' => null,
        'country' => $filterBag['country'] ?? null,
        'wc_only' => (bool) ($filterBag['wc_only'] ?? false),
    ];
    $eligible = amiga_tournament_step_eligible_key_set($con, $catalog, $facetBag);
    $counts = [];
    foreach (amiga_participation_eligible_players($con) as $player) {
        $playerId = (int) $player['id'];
        $participated = amiga_player_participated_event_key_set($con, $playerId);
        $count = 0;
        foreach ($eligible as $key => $_) {
            if (isset($participated[$key])) {
                $count++;
            }
        }
        if ($count > 0) {
            $counts[$playerId] = $count;
        }
    }
    ksort($counts);

    return $counts;
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$fail = 0;
foreach (['present', 'year:2024', 'month:2014-07', 'month:2002-06'] as $as) {
    if ($as === 'present') {
        amiga_snapshot_context_reset();
        $ctx = AmigaSnapshotContext::present();
    } else {
        $_GET['as'] = $as;
        amiga_snapshot_context_reset();
        $ctx = amiga_snapshot_context_from_request($con);
    }
    $catalog = amiga_tournament_step_catalog($con, $ctx);
    foreach ([[], ['wc_only' => true], ['country' => 'Germany']] as $i => $bag) {
        $filterBag = ['player_id' => null, 'country' => $bag['country'] ?? null, 'wc_only' => (bool) ($bag['wc_only'] ?? false)];
        $old = facet_counts_legacy($con, $catalog, $filterBag);
        $new = amiga_tournament_step_player_facet_counts($con, $catalog, $filterBag);
        ksort($new);
        if ($old !== $new) {
            echo "FAIL {$as} bag#{$i}\n";
            echo '  old: ' . json_encode($old) . "\n";
            echo '  new: ' . json_encode($new) . "\n";
            $fail++;
        }
    }
}
echo $fail === 0 ? "player facet parity: PASS\n" : "player facet parity: {$fail} FAIL\n";
$con->close();