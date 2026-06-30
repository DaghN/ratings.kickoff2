<?php
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_rated_game_row.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_videos_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_game_videos_render.inc.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$row = $id > 0 ? amiga_rated_game_load($con, $id) : null;
$gameVideos = $row !== null ? amiga_videos_for_game_id($id) : [];
$gameVideoActiveIndex = amiga_game_videos_active_index(
    $gameVideos,
    isset($_GET['v']) ? (string) $_GET['v'] : null,
);
$gameHasVideos = $gameVideos !== [];

$k2ScrollTargetId = '';
if ($row !== null) {
    $hasVideoPick = isset($_GET['v']) && amiga_tournament_videos_sanitize_youtube_id((string) $_GET['v']) !== '';
    $k2ScrollTargetId = $hasVideoPick ? 'k2-amiga-game-videos' : 'k2-game';
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga rated game</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<?php if ($gameHasVideos) { ?>
<link href="/stylesheets/amiga-tournament-videos.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament-videos.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="/js/amiga-game-video.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/amiga-game-video.js'); ?>" defer="defer"></script>
<?php } ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php'; k2_table_js_enqueue(); ?>
</head>
<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = '';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';
?>

<div id="k2-game" class="k2-game-page-anchor" tabindex="-1"></div>

<div class="k2-table-wrap">

<?php if ($row === null) { ?>
<p>Game not found.</p>
<?php } else { ?>
<table class="<?php echo k2_h(k2_table_ranked_sortable_class('k2-table--single-game k2-table--tournament-games', false)); ?>">

<thead>
	<tr>
        <th class="k2-table-cell--left">ID</th>
        <th class="k2-table-cell--left k2-table-cell--pad-left-xs">Date</th>
        <th class="k2-table-cell--left">Tournament</th>
        <th class="k2-table-cell--left" data-k2-help="Bracket phase when recorded (group, final, etc.).">Phase</th>
        <th class="k2-table-cell--left">Team A</th>
        <th></th>
        <th></th>
        <th class="k2-table-cell--left">Team B</th>
        <th class="k2-table-cell--pad-left-md">GD</th>
        <th data-k2-tooltip-label="Goal sum" data-k2-help="Total goals scored by both players. A 7-4 result has Sum 11.">Sum</th>
        <th data-k2-help="Team A's Elo rating before this game.">Rating A</th>
        <th data-k2-help="Team B's Elo rating before this game.">Rating B</th>
        <th data-k2-tooltip-label="Elo difference" data-k2-help="Absolute pre-game Elo rating difference between the two players.">Diff</th>
        <th class="k2-table-cell--pad-right-xs" data-k2-tooltip-label="Favorite expected score" data-k2-help="Elo maps the rating difference to an expected score for the favorite:&#10;&#10;ES = 1 / (1 + 10^(-diff/400))&#10;&#10;Examples:&#10;&#10;0 -> 0.50&#10;100 -> 0.64&#10;200 -> 0.76&#10;300 -> 0.85&#10;400 -> 0.91&#10;&#10;The actual score will be one of win = 1, draw = 0.5, loss = 0.">Fav ES</th>
        <th class="k2-table-cell--left" data-k2-tooltip-label="Adjustment" data-k2-help="The expected score and actual score are used to calculate the rating change:&#10;&#10;Rating change = 32 * (actual score - expected score)&#10;&#10;Example:&#10;&#10;200 Elo difference -> expected score 0.76 ->&#10;&#10;A win would gain 7.7 rating points.&#10;A draw would lose 8.3 rating points.&#10;A loss would lose 24.3 rating points.&#10;&#10;A favorite's expected win gives a small rating gain; an underdog win beats expectation a lot and gains more. The two players win or lose the opposite amount.">Adjustment</th>
        <th data-k2-tooltip-label="Adjustment lost" data-k2-help="Rating points lost by the other player. The two players win or lose the opposite amount."></th>
	</tr>
</thead>

<tbody class="black">
	<?php echo amiga_rated_game_row_html($row, [], $con); ?>
</tbody>

</table>
<?php } ?>

</div><!-- .k2-table-wrap -->

<?php if ($row !== null && $gameHasVideos) {
    amiga_game_videos_render_section($row, $gameVideos, $gameVideoActiveIndex);
} ?>

<?php mysqli_close($con); ?>

</div><!-- .k2-page-nav -->
</body>
</html>
