<?php
declare(strict_types=1);
require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
require __DIR__ . '/../../site/public_html/includes/amiga_countries_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
foreach (['event:589', 'month:2016-03', ''] as $as) {
    if ($as === '') { unset($_GET['as']); } else { $_GET['as'] = $as; }
    $GLOBALS['_amiga_snapshot_context'] = null;
    $ctx = amiga_snapshot_context_from_request($con);
    $t0 = microtime(true);
    $new = amiga_countries_query_index_rows($con, $ctx);
    $newMs = round((microtime(true) - $t0) * 1000, 1);
    $old = amiga_countries_index_rows(amiga_countries_player_rows($con, $ctx));
    $norm = static function (array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'country_token' => (string) $r['country_token'],
                'players' => (int) $r['players'],
                'games' => (int) $r['games'],
                'wc_players' => (int) $r['wc_players'],
                'wc_entries' => (int) $r['wc_entries'],
                'wc_gold' => (int) $r['wc_gold'],
                'wc_silver' => (int) $r['wc_silver'],
                'wc_bronze' => (int) $r['wc_bronze'],
                'games_per_player' => (float) $r['games_per_player'],
            ];
        }
        return $out;
    };
    $ok = $norm($new) === $norm($old);
    echo ($as === '' ? 'present' : $as) . ': new=' . $newMs . 'ms n=' . count($new) . ' parity=' . ($ok ? 'OK' : 'FAIL') . PHP_EOL;
}