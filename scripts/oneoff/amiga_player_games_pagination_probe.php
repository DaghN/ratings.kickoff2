<?php
declare(strict_types=1);
/**
 * Track J — Amiga player games 500-row pagination probe.
 * Usage: php scripts/oneoff/amiga_player_games_pagination_probe.php [--base=http://ratingskickoff.test]
 */
$base = 'http://ratingskickoff.test';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base=')) {
        $base = rtrim(substr($arg, 7), '/');
    }
}

require_once __DIR__ . '/../../site/public_html/includes/amiga_player_games_lib.php';
require_once __DIR__ . '/../../site/public_html/includes/amiga_player_games_filter_facets.php';
include __DIR__ . '/../../site/config/ko2amiga_config.php';

const PROBE_PLAYER_ID = 382;
const PROBE_CUTOFF = 'year:2024';
const PAGE_SIZE = 500;
const CURL_MAX_SEC = 0.45;

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function extract_game_ids(string $html): array
{
    if (!preg_match_all('#/amiga/game\.php\?id=(\d+)#', $html, $matches)) {
        return [];
    }

    return array_map('intval', $matches[1]);
}

function curl_page(string $url): array
{
    $t0 = microtime(true);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: text/html'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $elapsed = round(microtime(true) - $t0, 3);

    if (!is_string($body) || $body === '') {
        fail('Empty curl body: ' . $url . ($err !== '' ? ' (' . $err . ')' : ''));
    }
    if ($status !== 200) {
        fail('HTTP ' . $status . ' for ' . $url);
    }
    if (preg_match('/\b(Warning|Fatal error|Deprecated):/i', $body) === 1) {
        fail('PHP error in body for ' . $url);
    }

    return ['body' => $body, 'elapsed' => $elapsed];
}

echo "=== Track J player games pagination ===\n";

$url = $base . '/amiga/player/games.php?id=' . PROBE_PLAYER_ID . '&as=' . rawurlencode(PROBE_CUTOFF);
$curlTimes = [];
$html = '';
for ($i = 0; $i < 3; $i++) {
    $curl = curl_page($url);
    $curlTimes[] = $curl['elapsed'];
    $html = $curl['body'];
}
sort($curlTimes);
$curlWorst = $curlTimes[count($curlTimes) - 1];
$curlMedian = $curlTimes[(int) floor(count($curlTimes) / 2)];
$curlBest = $curlTimes[0];
$pageIds = extract_game_ids($html);
$rowCount = count($pageIds);

echo 'Curl: ' . $url . "\n";
echo 'Elapsed (3 runs): best=' . $curlBest . ' s median=' . $curlMedian . ' worst=' . $curlWorst . " s\n";
echo 'Data rows (game links): ' . $rowCount . "\n";

if ($rowCount > PAGE_SIZE) {
    fail('Page rendered ' . $rowCount . ' data rows; expected <= ' . PAGE_SIZE);
}
if ($rowCount === 0) {
    fail('No game rows found in HTML');
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_error) {
    fail('DB connect: ' . $con->connect_error);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$_GET['as'] = PROBE_CUTOFF;
amiga_snapshot_context_reset();
$ctx = amiga_snapshot_context_from_request($con);

$filters = amiga_player_games_filters_from_request($con, PROBE_PLAYER_ID, [], $ctx);
$whereTypes = '';
$whereParams = [];
$whereSql = amiga_games_where_clause(
    PROBE_PLAYER_ID,
    $filters['result'],
    $filters['opponent'],
    $filters['tournament'],
    $filters['event'],
    $filters['country'],
    $filters['opp_country'],
    $filters['day'],
    $filters['since'],
    $filters['until'],
    $filters['year'],
    $filters['gf'],
    $filters['ga'],
    $filters['gs'],
    $filters['gd'],
    $whereTypes,
    $whereParams,
    $ctx
);
$fromSql = amiga_rated_games_from_sql(PROBE_PLAYER_ID);
$oracleRows = amiga_games_query_all(
    $con,
    'SELECT r.id ' . $fromSql . ' WHERE ' . $whereSql . ' ORDER BY r.id DESC LIMIT ' . PAGE_SIZE,
    $whereTypes,
    $whereParams
);
$oracleIds = array_map(static fn(array $row): int => (int) $row['id'], $oracleRows);
$con->close();

echo 'Oracle first-page IDs: ' . count($oracleIds) . "\n";

if ($pageIds !== $oracleIds) {
    $missing = array_diff($oracleIds, $pageIds);
    $extra = array_diff($pageIds, $oracleIds);
    fail(
        'ID mismatch vs oracle LIMIT ' . PAGE_SIZE
        . ' (missing=' . count($missing) . ', extra=' . count($extra) . ')'
    );
}

if ($curlWorst > 1.09) {
    echo 'WARN: curl worst ' . $curlWorst . " s slower than pre-pagination census worst (~1.09 s)\n";
} elseif ($curlBest < 1.09) {
    echo 'NOTE: curl best ' . $curlBest . " s beats census worst (~1.09 s)\n";
}

if ($curlWorst > CURL_MAX_SEC) {
    echo 'WARN: curl worst ' . $curlWorst . ' s above aspirational ' . CURL_MAX_SEC . " s target\n";
}

echo 'PASS — rows <= ' . PAGE_SIZE . ', IDs match oracle (curl best=' . $curlBest . ' s worst=' . $curlWorst . " s)\n";