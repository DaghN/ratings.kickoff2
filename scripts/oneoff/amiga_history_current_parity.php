<?php
declare(strict_types=1);

require __DIR__ . '/../../site/public_html/includes/amiga_rating_history_lib.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');

$view = amiga_rating_history_resolve_view($con, 'event', null);
$hist = array_slice($view['ladder'], 0, 10);
$res = $con->query(
    'SELECT c.player_id, c.Rating FROM amiga_player_current c '
    . 'WHERE c.NumberGames > 0 ORDER BY c.Rating DESC, c.player_id ASC LIMIT 10'
);
$cur = [];
while ($row = $res->fetch_assoc()) {
    $cur[] = (int) $row['player_id'] . ':' . (int) round((float) $row['Rating']);
}
$h = [];
foreach ($hist as $r) {
    $h[] = $r['player_id'] . ':' . (int) round($r['rating_after']);
}
echo 'history_top10=' . implode(',', $h) . PHP_EOL;
echo 'current_top10=' . implode(',', $cur) . PHP_EOL;
echo ($h === $cur ? 'PARITY OK' : 'MISMATCH') . PHP_EOL;
