<?php
/**
 * Profile load diagnostics — localhost only.
 *
 * Usage: individual1_profile_diag.php?id=237
 * Optional: &explain=1 for EXPLAIN on the slowest query shapes
 */
declare(strict_types=1);

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Profile diagnostics are only available on localhost.';
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 237;
if ($id < 1) {
    http_response_code(400);
    echo 'Invalid id';
    exit;
}

$withExplain = !empty($_GET['explain']);
$pageStart = microtime(true);
$docRoot = $_SERVER['DOCUMENT_ROOT'];

header('Content-Type: text/html; charset=utf-8');

$marks = [];

/** @param callable(): void $fn */
function profile_run(string $label, callable $fn): void
{
    global $marks;
    $t0 = microtime(true);
    $fn();
    $marks[] = ['label' => $label, 'ms' => round((microtime(true) - $t0) * 1000, 2)];
}

// Simulate individual1 <head> filemtime calls (runs before DB on prod page).
$filemtimePaths = [
    '/js/chart-theme.js',
    '/js/chart-date-range.js',
    '/js/player-rating-chart.js',
    '/js/player-games-month-chart.js',
    '/js/player-rating-game-chart.js',
    '/js/player-top-opponents-chart.js',
    '/js/player-head-to-head-chart.js',
    '/js/player-compare-rating-chart.js',
    '/js/player-h2h-opponent-search.js',
    '/js/player-feast/player-calendar.js',
    '/stylesheets/player-hero-rank.css',
];
profile_run('head_filemtime_×' . count($filemtimePaths), static function () use ($docRoot, $filemtimePaths): void {
    foreach ($filemtimePaths as $rel) {
        @filemtime($docRoot . $rel);
    }
});

include $docRoot . '/../config/ko2unitydb_config.php';

$dbHostLabel = isset($dbhost) ? (string) $dbhost : '(unknown)';
$dbNameLabel = isset($database) ? (string) $database : '(unknown)';
$dbPortLabel = isset($dbportnum) ? (string) $dbportnum : '3306';

$connectMs = 0.0;
profile_run('mysqli_connect', static function () use (&$con, $dbhost, $username, $password, $database, $dbportnum, &$connectMs): void {
    $t0 = microtime(true);
    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    $connectMs = (microtime(true) - $t0) * 1000;
    if ($con->connect_errno) {
        throw new RuntimeException('connect: ' . $con->connect_error);
    }
    $con->set_charset('utf8mb4');
});

require_once $docRoot . '/includes/player_feast_load.php';
require_once $docRoot . '/includes/player_feast_blocks.php';

$GLOBALS['k2_player_feast_profile'] = [
    'queries' => [],
    'total_query_ms' => 0.0,
    'marks' => [],
];

$pm = null;
$loadError = null;
profile_run('player_feast_load_pm', static function () use ($con, $id, &$pm, &$loadError): void {
    try {
        $pm = player_feast_load_pm($con, $id);
    } catch (RuntimeException $e) {
        $loadError = $e->getMessage();
    }
});

$queryLog = $GLOBALS['k2_player_feast_profile']['queries'] ?? [];
$queryTotalMs = (float) ($GLOBALS['k2_player_feast_profile']['total_query_ms'] ?? 0);

usort($queryLog, static function (array $a, array $b): int {
    return $b['ms'] <=> $a['ms'];
});

$renderBytes = 0;
profile_run('render_profile_html', static function () use ($pm, $id, &$renderBytes): void {
    if ($pm === null) {
        return;
    }
    ob_start();
    player_feast_expose_hero_vars($pm);
    $calYear = (int) date('Y');
    $playerId = (int) $pm['id'];
    player_feast_render_presence_career_duo($pm);
    player_feast_render_played_days($playerId, $calYear);
    player_feast_render_peak_activity($pm);
    player_feast_render_moments($pm);
    player_feast_render_charts($playerId);
    $renderBytes = strlen((string) ob_get_clean());
});

$tableCounts = [];
profile_run('table_row_counts', static function () use ($con, &$tableCounts): void {
    foreach (['ratedresults', 'playertable'] as $table) {
        $res = mysqli_query($con, 'SELECT COUNT(*) AS c FROM `' . $table . '`');
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $tableCounts[$table] = (int) $row['c'];
        }
    }
});

$playerGames = null;
if ($pm !== null) {
    $playerGames = (int) $pm['games'];
}

$explains = [];
if ($withExplain && $pm !== null) {
    $escId = (string) $id;
    $explainSql = [
        'busiest_month' => "SELECT DATE_FORMAT(Date, '%Y-%m') AS k, COUNT(*) AS c FROM ratedresults "
            . "WHERE idA='$escId' OR idB='$escId' GROUP BY k ORDER BY c DESC LIMIT 1",
        'first_rated_game' => "SELECT Date FROM ratedresults WHERE idA='$escId' OR idB='$escId' ORDER BY Date ASC, id ASC LIMIT 1",
        'career_rank_games' => 'SELECT COUNT(*) + 1 AS r FROM playertable WHERE Display = 1 AND COALESCE(`NumberGames`, 0) > COALESCE((SELECT `NumberGames` FROM playertable WHERE id = ' . $id . '), 0)',
    ];
    foreach ($explainSql as $label => $sql) {
        $res = mysqli_query($con, 'EXPLAIN ' . $sql);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        $explains[$label] = $rows;
    }
}

mysqli_close($con);

$pageMs = round((microtime(true) - $pageStart) * 1000, 2);
$querySumMs = round($queryTotalMs, 2);
$nonQueryMs = round($pageMs - $querySumMs, 2);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Profile load diagnostics — id <?php echo $id; ?></title>
<style>
body { font-family: system-ui, sans-serif; background: #0b0f14; color: #e6edf3; margin: 24px; max-width: 1100px; }
h1 { font-size: 1.25rem; }
h2 { font-size: 1rem; margin-top: 28px; color: #8b949e; }
table { border-collapse: collapse; width: 100%; font-size: 13px; margin: 8px 0 20px; }
th, td { border: 1px solid #30363d; padding: 6px 10px; text-align: left; }
th { background: #1a2230; }
tr:nth-child(even) td { background: #131922; }
.num { text-align: right; font-variant-numeric: tabular-nums; }
.warn { color: #ffb74d; }
.ok { color: #9ccc65; }
.meta { color: #8b949e; font-size: 0.9em; line-height: 1.5; }
.bar { height: 8px; background: #30363d; border-radius: 4px; overflow: hidden; }
.bar > i { display: block; height: 100%; background: #64b5f6; }
code { font-size: 12px; color: #9ccc65; }
a { color: #64b5f6; }
</style>
</head>
<body>
<h1>individual1 profile load diagnostics</h1>
<p class="meta">
	Player <strong><?php echo htmlspecialchars((string) ($pm['name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></strong>
	(id=<?php echo $id; ?><?php echo $playerGames !== null ? ', ' . $playerGames . ' games' : ''; ?>).
	<a href="player/profile.php?id=<?php echo $id; ?>">Open profile</a> ·
	<a href="individual1_profile_diag.php?id=<?php echo $id; ?>&explain=1">With EXPLAIN</a>
</p>

<h2>Verdict</h2>
<p class="meta">
<?php if ($loadError !== null) { ?>
	<span class="warn">Load failed: <?php echo htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8'); ?></span>
<?php } else { ?>
	Total server time <strong><?php echo $pageMs; ?> ms</strong>
	(SQL <?php echo $querySumMs; ?> ms ≈ <?php echo $pageMs > 0 ? round(100 * $querySumMs / $pageMs) : 0; ?>%,
	non-query <?php echo $nonQueryMs; ?> ms).
<?php if ($pageMs >= 500 && $querySumMs >= $pageMs * 0.6) { ?>
	<br><span class="warn">Blank-page wait is almost certainly <strong>player_feast_load_pm() SQL</strong> on local MySQL — not chart JS.</span>
<?php } elseif (($marks[0]['ms'] ?? 0) > 50) { ?>
	<br><span class="warn">Head <code>filemtime</code> cost is noticeable; prod sends HTML only after this + full load.</span>
<?php } ?>
<?php } ?>
</p>

<h2>Environment</h2>
<table>
	<tr><th>DB host</th><td><code><?php echo htmlspecialchars($dbHostLabel, ENT_QUOTES, 'UTF-8'); ?></code></td></tr>
	<tr><th>DB name</th><td><code><?php echo htmlspecialchars($dbNameLabel, ENT_QUOTES, 'UTF-8'); ?></code></td></tr>
	<tr><th>DB port</th><td><?php echo htmlspecialchars($dbPortLabel, ENT_QUOTES, 'UTF-8'); ?></td></tr>
	<tr><th>Connect</th><td class="num"><?php echo round($connectMs, 2); ?> ms</td></tr>
	<tr><th>ratedresults rows</th><td class="num"><?php echo isset($tableCounts['ratedresults']) ? number_format($tableCounts['ratedresults']) : '—'; ?></td></tr>
	<tr><th>playertable rows</th><td class="num"><?php echo isset($tableCounts['playertable']) ? number_format($tableCounts['playertable']) : '—'; ?></td></tr>
	<tr><th>HTML render (bytes)</th><td class="num"><?php echo number_format($renderBytes); ?></td></tr>
</table>

<h2>Page phases</h2>
<table>
	<thead><tr><th>Phase</th><th class="num">ms</th></tr></thead>
	<tbody>
<?php foreach ($marks as $m) { ?>
		<tr>
			<td><?php echo htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8'); ?></td>
			<td class="num"><?php echo $m['ms']; ?></td>
		</tr>
<?php } ?>
	</tbody>
</table>

<?php if ($queryLog) { ?>
<h2>SQL queries (slowest first)</h2>
<table>
	<thead><tr><th>Label</th><th class="num">ms</th><th>OK</th><th>Share</th></tr></thead>
	<tbody>
<?php
$maxMs = max(1.0, (float) $queryLog[0]['ms']);
foreach ($queryLog as $q) {
    $pct = $querySumMs > 0 ? round(100 * $q['ms'] / $querySumMs, 1) : 0;
    $barW = round(100 * $q['ms'] / $maxMs);
    ?>
		<tr>
			<td><code><?php echo htmlspecialchars($q['label'], ENT_QUOTES, 'UTF-8'); ?></code></td>
			<td class="num"><?php echo $q['ms']; ?></td>
			<td><?php echo $q['ok'] ? '<span class="ok">yes</span>' : '<span class="warn">' . htmlspecialchars((string) $q['error'], ENT_QUOTES, 'UTF-8') . '</span>'; ?></td>
			<td>
				<div class="bar" title="<?php echo $pct; ?>% of query time"><i style="width:<?php echo $barW; ?>%"></i></div>
			</td>
		</tr>
<?php } ?>
		<tr>
			<td><strong>All queries</strong></td>
			<td class="num"><strong><?php echo $querySumMs; ?></strong></td>
			<td colspan="2"></td>
		</tr>
	</tbody>
</table>
<?php } ?>

<?php if ($explains) { ?>
<h2>EXPLAIN (heaviest shapes)</h2>
<?php foreach ($explains as $label => $rows) { ?>
<h3 style="font-size:0.95rem;color:#e6edf3;"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></h3>
<pre style="background:#131922;padding:12px;overflow:auto;font-size:11px;border:1px solid #30363d;"><?php echo htmlspecialchars(json_encode($rows, JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></pre>
<?php } ?>
<?php } ?>

<p class="meta">Production <code>player/profile.php</code> also loads Chart.js and ~10 scripts in <code>&lt;head&gt;</code> before the DB block; this page skips that. Chart API fetches happen after first paint and are not measured here.</p>
</body>
</html>
