<?php
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2) . '/site/public_html';
require $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';
require $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_rank_history_lib.php';
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$payload = amiga_player_rank_history_payload($con, 109);
echo 'points=' . count($payload['points']) . PHP_EOL;
echo json_encode($payload['meta'], JSON_PRETTY_PRINT) . PHP_EOL;
$p = $payload['points'][0];
$calc = round(100.0 * ($p['ladderSize'] - $p['eloRank'] + 1) / $p['ladderSize'], 1);
echo 'first rank=' . $p['eloRank'] . ' N=' . $p['ladderSize'] . ' pct=' . $p['percentile'] . ' calc=' . $calc . PHP_EOL;
$payload84 = amiga_player_rank_history_payload($con, 84);
echo 'darren points=' . count($payload84['points']) . ' best=' . $payload84['meta']['careerBestRank'] . ' worst=' . $payload84['meta']['careerWorstRank'] . PHP_EOL;

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
$_GET['as'] = 'year:2003';
$ctx = amiga_snapshot_context_from_request($con);
$tt237 = amiga_player_rank_history_payload($con, 237, $ctx);
echo 'tt237 points=' . count($tt237['points']) . ' currentRank=' . ($tt237['currentRank'] ?? 'null') . PHP_EOL;

$missing = amiga_player_rank_history_payload($con, 999999);
echo 'missing=' . ($missing === null ? 'null' : 'found') . PHP_EOL;

mysqli_close($con);