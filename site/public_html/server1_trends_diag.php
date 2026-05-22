<?php
/**
 * server1.php (Trends) load diagnostics — localhost only.
 *
 * Usage: server1_trends_diag.php
 * Measures blocking PHP on server1 + chart API SQL (same queries the page triggers via JS).
 */
declare(strict_types=1);

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Trends diagnostics are only available on localhost.';
    exit;
}

header('Content-Type: text/html; charset=utf-8');

$pageStart = microtime(true);
$queries = [];

/**
 * @return mysqli_result|bool
 */
function server1_diag_query(mysqli $con, string $label, string $sql, string $kind, string $dateIndexVerdict): mixed
{
    global $queries;
    $t0 = microtime(true);
    $result = mysqli_query($con, $sql);
    $ms = round((microtime(true) - $t0) * 1000, 2);
    $queries[] = [
        'label' => $label,
        'ms' => $ms,
        'ok' => $result !== false,
        'error' => $result === false ? mysqli_error($con) : null,
        'kind' => $kind,
        'date_index' => $dateIndexVerdict,
    ];
    return $result;
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    echo '<p>Connect failed: ' . htmlspecialchars($con->connect_error, ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}
$con->set_charset('utf8mb4');

$connectMs = 0.0;
$t0 = microtime(true);
// already connected
$connectMs = round((microtime(true) - $t0) * 1000, 2);

// ── Blocking PHP on server1.php ──
server1_diag_query($con, 'generalstatstable', 'SELECT * FROM generalstatstable', 'php_blocking', 'n/a — single row');

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/peak_month_leaderboard_query.php';

$limit = 50;
$err = null;
$t0 = microtime(true);
k2_peak_period_leaderboard_entries($con, 'day', $limit, $err);
$queries[] = [
    'label' => 'hall_of_fame_day (peak_period)',
    'ms' => round((microtime(true) - $t0) * 1000, 2),
    'ok' => $err === null,
    'error' => $err,
    'kind' => 'php_blocking',
    'date_index' => 'low — full-table player×period rollup (UNION all games)',
];
$t0 = microtime(true);
k2_peak_period_leaderboard_entries($con, 'month', $limit, $err);
$queries[] = [
    'label' => 'hall_of_fame_month',
    'ms' => round((microtime(true) - $t0) * 1000, 2),
    'ok' => $err === null,
    'error' => $err,
    'kind' => 'php_blocking',
    'date_index' => 'low — same pattern',
];
$t0 = microtime(true);
k2_peak_period_leaderboard_entries($con, 'year', $limit, $err);
$queries[] = [
    'label' => 'hall_of_fame_year',
    'ms' => round((microtime(true) - $t0) * 1000, 2),
    'ok' => $err === null,
    'error' => $err,
    'kind' => 'php_blocking',
    'date_index' => 'low — same pattern',
];

// ── Chart APIs (deferred JS on server1) ──
server1_diag_query(
    $con,
    'api_server_games_by_month',
    "SELECT DATE_FORMAT(`Date`, '%Y-%m') AS ym, COUNT(*) AS games FROM ratedresults GROUP BY ym ORDER BY ym ASC",
    'chart_api',
    'low — GROUP BY month expression over full table'
);
server1_diag_query(
    $con,
    'api_server_games_by_year',
    'SELECT YEAR(`Date`) AS yr, COUNT(*) AS games FROM ratedresults GROUP BY yr ORDER BY yr ASC',
    'chart_api',
    'low — GROUP BY YEAR over full table'
);
server1_diag_query(
    $con,
    'api_server_goals_by_month',
    "SELECT DATE_FORMAT(`Date`, '%Y-%m') AS ym, SUM(COALESCE(GoalsA, 0) + COALESCE(GoalsB, 0)) AS goals "
        . 'FROM ratedresults GROUP BY ym HAVING ym IS NOT NULL ORDER BY ym ASC',
    'chart_api',
    'low — GROUP BY month over full table'
);
server1_diag_query(
    $con,
    'api_server_active_players_by_month',
    'SELECT ym, COUNT(DISTINCT player_id) AS active_players FROM ('
        . "SELECT DATE_FORMAT(`Date`, '%Y-%m') AS ym, idA AS player_id FROM ratedresults "
        . 'UNION ALL '
        . "SELECT DATE_FORMAT(`Date`, '%Y-%m') AS ym, idB AS player_id FROM ratedresults"
        . ') AS appearances WHERE ym IS NOT NULL GROUP BY ym ORDER BY ym ASC',
    'chart_api',
    'low — reads all rows; DATE_FORMAT blocks plain Date index'
);

$rn = 20;
server1_diag_query(
    $con,
    'api_server_cumulative_established',
    'SELECT game_date, game_id FROM ('
        . 'SELECT player_id, game_date, game_id, '
        . 'ROW_NUMBER() OVER (PARTITION BY player_id ORDER BY game_date ASC, game_id ASC) AS rn '
        . 'FROM ('
        . 'SELECT idA AS player_id, `Date` AS game_date, id AS game_id FROM ratedresults '
        . 'UNION ALL '
        . 'SELECT idB AS player_id, `Date` AS game_date, id AS game_id FROM ratedresults'
        . ') AS appearances WHERE game_date IS NOT NULL'
        . ') AS numbered WHERE rn = ' . $rn . ' ORDER BY game_date ASC, game_id ASC',
    'chart_api',
    'low — window over all appearances; heaviest chart query'
);
server1_diag_query(
    $con,
    'api_server_established_by_year',
    'SELECT YEAR(established_date) AS yr, COUNT(*) AS established_players FROM ('
        . 'SELECT player_id, game_date AS established_date FROM ('
        . 'SELECT player_id, game_date, game_id, '
        . 'ROW_NUMBER() OVER (PARTITION BY player_id ORDER BY game_date ASC, game_id ASC) AS rn '
        . 'FROM ('
        . 'SELECT idA AS player_id, `Date` AS game_date, id AS game_id FROM ratedresults '
        . 'UNION ALL '
        . 'SELECT idB AS player_id, `Date` AS game_date, id AS game_id FROM ratedresults'
        . ') AS appearances WHERE game_date IS NOT NULL'
        . ') AS numbered WHERE rn = ' . $rn
        . ') AS established GROUP BY yr ORDER BY yr ASC',
    'chart_api',
    'low — same window family'
);
server1_diag_query(
    $con,
    'api_server_rating_distribution',
    'SELECT bucket_start, COUNT(*) AS players FROM ('
        . 'SELECT FLOOR(Rating / 100) * 100 AS bucket_start '
        . 'FROM playertable WHERE NumberGames >= 20 AND Rating IS NOT NULL'
        . ') AS buckets GROUP BY bucket_start ORDER BY bucket_start ASC',
    'chart_api',
    'n/a — playertable only'
);

// Index presence
$indexes = [];
$idxRes = mysqli_query(
    $con,
    "SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS "
    . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ratedresults' ORDER BY INDEX_NAME, SEQ_IN_INDEX"
);
while ($idxRes && ($ir = mysqli_fetch_assoc($idxRes))) {
    $indexes[] = $ir['INDEX_NAME'] . '(' . $ir['COLUMN_NAME'] . ')';
}

mysqli_close($con);

$phpBlockingMs = 0.0;
$chartApiMs = 0.0;
foreach ($queries as $q) {
    if ($q['kind'] === 'php_blocking') {
        $phpBlockingMs += $q['ms'];
    } else {
        $chartApiMs += $q['ms'];
    }
}
$totalSqlMs = $phpBlockingMs + $chartApiMs;

usort($queries, static fn (array $a, array $b): int => $b['ms'] <=> $a['ms']);

// HTTP probe server1 document
$docMs = null;
$docUrl = 'http://ratingskickoff.test/server1.php';
if (function_exists('curl_init')) {
    $ch = curl_init($docUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_TIMEOUT => 120,
    ]);
    $t0 = microtime(true);
    curl_exec($ch);
    $docMs = round((microtime(true) - $t0) * 1000, 2);
    curl_close($ch);
}

$pageMs = round((microtime(true) - $pageStart) * 1000, 2);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>server1 Trends diagnostics</title>
<style>
body { font-family: system-ui, sans-serif; background: #0b0f14; color: #e6edf3; margin: 24px; max-width: 1100px; }
h1 { font-size: 1.25rem; }
h2 { font-size: 1rem; color: #8b949e; margin-top: 28px; }
table { border-collapse: collapse; width: 100%; font-size: 13px; margin: 8px 0 20px; }
th, td { border: 1px solid #30363d; padding: 6px 10px; text-align: left; }
th { background: #1a2230; }
.num { text-align: right; font-variant-numeric: tabular-nums; }
.warn { color: #ffb74d; }
.ok { color: #9ccc65; }
.meta { color: #8b949e; font-size: 0.9em; line-height: 1.5; }
.tag { font-size: 11px; color: #8b949e; }
</style>
</head>
<body>
<h1>server1.php (Trends) diagnostics — local</h1>
<p class="meta">
	<a href="server1.php">Open server1</a> ·
	DB <code><?php echo htmlspecialchars((string) $database, ENT_QUOTES, 'UTF-8'); ?></code> @
	<code><?php echo htmlspecialchars((string) $dbhost, ENT_QUOTES, 'UTF-8'); ?></code>
</p>

<h2>Summary</h2>
<table>
	<tr><th>Measured</th><th class="num">ms</th></tr>
	<tr><td>SQL in this diag (all queries below)</td><td class="num"><?php echo $totalSqlMs; ?></td></tr>
	<tr><td>↳ Blocking PHP on server1 (generalstats + 3× hall of fame)</td><td class="num"><?php echo round($phpBlockingMs, 2); ?></td></tr>
	<tr><td>↳ Chart APIs (7 fetches after page paint)</td><td class="num"><?php echo round($chartApiMs, 2); ?></td></tr>
<?php if ($docMs !== null) { ?>
	<tr><td>HTTP <code>server1.php</code> (full document, curl)</td><td class="num"><?php echo $docMs; ?></td></tr>
<?php } ?>
	<tr><td>This diag page generation</td><td class="num"><?php echo $pageMs; ?></td></tr>
</table>

<p class="meta">
<?php if ($phpBlockingMs >= 2000) { ?>
	<span class="warn"><strong>Blank / frozen tab:</strong> likely blocking PHP (hall of fame queries scan all games ×3).</span><br>
<?php } ?>
<?php if ($chartApiMs >= 3000) { ?>
	<span class="warn"><strong>Charts slow to fill:</strong> deferred API SQL (especially cumulative / established window queries).</span><br>
<?php } ?>
	<strong>Date index verdict:</strong> unlikely to help much here — most cost is <code>GROUP BY DATE_FORMAT(...)</code> or window functions over the full table, not <code>WHERE Date = …</code> range filters.
</p>

<h2>ratedresults indexes</h2>
<p class="meta"><code><?php echo $indexes ? implode(', ', array_map('htmlspecialchars', $indexes)) : 'none'; ?></code></p>

<h2>Queries (slowest first)</h2>
<table>
	<thead>
		<tr>
			<th>Label</th>
			<th>When</th>
			<th class="num">ms</th>
			<th>Date index?</th>
		</tr>
	</thead>
	<tbody>
<?php foreach ($queries as $q) { ?>
		<tr>
			<td><code><?php echo htmlspecialchars($q['label'], ENT_QUOTES, 'UTF-8'); ?></code></td>
			<td class="tag"><?php echo $q['kind'] === 'php_blocking' ? 'before first byte' : 'after load (JS)'; ?></td>
			<td class="num"><?php echo $q['ms']; ?></td>
			<td class="tag"><?php echo htmlspecialchars($q['date_index'], ENT_QUOTES, 'UTF-8'); ?></td>
		</tr>
<?php } ?>
	</tbody>
</table>

<p class="meta">Compare chart API totals to DevTools Network on server1 — seven <code>api/server_*.php</code> requests. Delete this file from production if ever deployed.</p>
</body>
</html>
