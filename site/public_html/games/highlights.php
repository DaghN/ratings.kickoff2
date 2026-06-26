<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/games_highlights_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_helpers.php';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$k2GamesHubView = 'highlights';
$k2GamesPageTitle = 'Games — Highlights';
$highlightBoard = k2_games_highlights_valid_board((string) ($_GET['board'] ?? 'most_goals'));

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

$highlightRows = k2_games_highlights_fetch($con, $highlightBoard);
$hubCounts = k2_games_hub_status_counts($con);
$k2GamesHubArc = $hubCounts['arc'];
$k2GamesRecent14Count = $hubCounts['recent14'];

mysqli_close($con);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_shell_start.inc.php';
?>
	<div id="<?php echo K2_GAMES_HIGHLIGHTS_ANCHOR; ?>" class="k2-games-highlights-cluster">
		<?php k2_games_render_highlights_board_filter($highlightBoard); ?>
		<?php k2_games_render_highlights_table($highlightRows, $highlightBoard); ?>
	</div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_shell_end.inc.php'; ?>
