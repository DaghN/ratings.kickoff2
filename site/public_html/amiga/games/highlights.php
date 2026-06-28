<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_highlights_helpers.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$k2AmigaGamesHubView = 'highlights';
$k2AmigaGamesPageTitle = 'Games — Highlights';
$highlightBoard = amiga_games_highlights_valid_board((string) ($_GET['board'] ?? 'most_goals'));

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

$highlightRows = amiga_games_highlights_fetch($con, $highlightBoard, $ctx);
$hubCounts = amiga_games_hub_status_counts($con, $ctx);
$k2AmigaGamesHubTotal = $hubCounts['total'];
$k2AmigaGamesRecentCount = $hubCounts['recent'];

mysqli_close($con);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_shell_start.inc.php';
?>
	<div id="<?php echo AMIGA_GAMES_HIGHLIGHTS_ANCHOR; ?>" class="k2-games-highlights-cluster">
		<?php amiga_games_render_highlights_board_filter($highlightBoard); ?>
		<?php amiga_games_render_highlights_table($highlightRows, $highlightBoard); ?>
	</div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_shell_end.inc.php'; ?>
