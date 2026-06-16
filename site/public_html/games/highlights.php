<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/games_highlights_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_helpers.php';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$k2GamesHubView = 'highlights';
$k2GamesPageTitle = 'Games — Highlights';
$highlightBoard = k2_games_highlights_valid_board((string) ($_GET['board'] ?? 'most_goals'));

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if (mysqli_connect_errno()) {
	die('Failed to connect to MySQL: ' . mysqli_connect_error());
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$highlightRows = k2_games_highlights_fetch($con, $highlightBoard);
$hubCounts = k2_games_hub_status_counts($con);
$k2GamesHubArc = $hubCounts['arc'];
$k2GamesRecent14Count = $hubCounts['recent14'];

mysqli_close($con);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_shell_start.inc.php';
?>
	<div id="<?php echo K2_GAMES_HIGHLIGHTS_ANCHOR; ?>" class="k2-games-highlights-cluster">
		<?php k2_games_render_highlights_board_filter($highlightBoard); ?>
		<?php k2_games_render_highlights_table(
			$highlightRows,
			$highlightBoard,
			$highlightBoard === 'top_score'
		); ?>
	</div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_shell_end.inc.php'; ?>
