<?php
declare(strict_types=1);
require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_countries_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

function ms(float $s): float { return round((microtime(true)-$s)*1000,1); }
function normRoster(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'player_id' => (int)$r['player_id'],
            'rating' => (int)$r['rating'],
            'elo_rank' => $r['elo_rank'],
            'number_games' => (int)$r['number_games'],
            'wc_played' => (int)$r['wc_played'],
        ];
    }
    return $out;
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$country = 'Greece';

foreach (['', 'month:2025-09', 'event:589'] as $as) {
    if ($as === '') { unset($_GET['as']); } else { $_GET['as'] = $as; }
    $GLOBALS['_amiga_snapshot_context'] = null;
    $ctx = amiga_snapshot_context_from_request($con);
    $old = amiga_countries_roster_rows(amiga_countries_player_rows($con, $ctx), $country);
    $t0 = microtime(true);
    $new = amiga_countries_query_roster_rows($con, $ctx, $country);
    $newMs = ms($t0);
    $ok = normRoster($old) === normRoster($new);
    echo ($as === '' ? 'present' : $as) . ': parity=' . ($ok ? 'OK' : 'FAIL') . " new={$newMs}ms n=" . count($new) . PHP_EOL;
}