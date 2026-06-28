<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_hub_table.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$k2AmigaGamesHubView = 'recent';
$k2AmigaGamesPageTitle = 'Games — Recent';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

$hubCounts = amiga_games_hub_status_counts($con, $ctx);
$k2AmigaGamesHubTotal = $hubCounts['total'];
$k2AmigaGamesRecentCount = $hubCounts['recent'];

$recentSections = [];
foreach (amiga_games_hub_recent_tournaments($con, $ctx) as $tournamentRow) {
    $tournamentId = (int) ($tournamentRow['id'] ?? 0);
    if ($tournamentId < 1) {
        continue;
    }
    $recentSections[] = [
        'heading' => amiga_games_hub_tournament_section_heading($tournamentRow),
        'rows' => amiga_games_hub_recent_games_for_tournament($con, $tournamentId, $ctx),
    ];
}

mysqli_close($con);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_shell_start.inc.php';
?>
	<div class="k2-games-list">
<?php foreach ($recentSections as $section) { ?>
	<div class="k2-games-day">
		<h2 class="k2-panel-heading k2-games-day__heading"><?php echo $section['heading']; ?></h2>
		<?php amiga_realm_games_hub_render_table($section['rows'], [
            'default_sort_col' => AMIGA_REALM_GAMES_HUB_ID_SORT_COL,
            'default_sort_dir' => 'desc',
            'skip_initial_sort' => true,
            'empty_message' => 'No rated games in this tournament.',
        ]); ?>
	</div>
<?php } ?>
<?php if ($recentSections === []) { ?>
	<p class="k2-games-day__empty">No recent tournaments to show.</p>
<?php } ?>
	</div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_shell_end.inc.php'; ?>
