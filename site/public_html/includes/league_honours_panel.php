<?php
/**
 * League honours wing — segment pills + table (requires $honoursView, $honoursRows, $queryError, $dataReady).
 */
declare(strict_types=1);

if (!function_exists('k2_lb_league_honours_parse_view')) {
    require_once __DIR__ . '/league_honours_leaderboard.php';
}
require_once __DIR__ . '/k2_league_table_render.php';
require_once __DIR__ . '/lb_column_help.php';
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/lb_player_filters.php';

$honoursView = $honoursView ?? k2_lb_league_honours_parse_view();
$honoursRows = $honoursRows ?? [];
$queryError = $queryError ?? null;
$dataReady = $dataReady ?? false;
$filterOpts = k2_lb_filter_opts();

$cup = (string) ($honoursView['cup'] ?? 'overall');
$grain = $honoursView['grain'] ?? null;
$goldHelp = k2_lb_league_honours_gold_help($honoursView);

$cupTabs = [
    'overall' => 'Overall',
    'activity' => 'Activity leagues',
    'points' => 'Points leagues',
];
$grainTabs = [
    'day' => k2_status_period_segment_label('day'),
    'week' => k2_status_period_segment_label('week'),
    'month' => k2_status_period_segment_label('month'),
    'year' => k2_status_period_segment_label('year'),
];
?>
<div class="k2-lb-league-honours">
	<div class="k2-lb-league-honours__subnav">
		<nav class="k2-lb-league-honours__bar-wrap" data-k2-carry-scroll aria-label="League honours view">
			<div class="k2-chrome-tabs__bar k2-lb-league-honours__bar" role="tablist">
<?php
$sliceGrain = ($cup !== 'overall' && $grain !== null) ? (string) $grain : 'day';
foreach ($cupTabs as $cupId => $label) {
    $active = $cup === $cupId;
    $href = k2_lb_league_honours_href(
        $cupId,
        $cupId === 'overall' ? null : $sliceGrain,
        $filterOpts
    );
    ?>
				<a
					href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
					class="k2-chrome-tabs__tab<?php echo $active ? ' is-active' : ''; ?>"
					role="tab"
					aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
				><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
			</div>
		</nav>
<?php if ($cup !== 'overall') { ?>
		<nav class="k2-lb-league-honours__bar-wrap" data-k2-carry-scroll aria-label="League time span">
			<div class="k2-chrome-tabs__bar k2-lb-league-honours__bar" role="tablist">
<?php foreach ($grainTabs as $grainId => $label) {
    $active = $grain === $grainId;
    $href = k2_lb_league_honours_href($cup, $grainId, $filterOpts);
    ?>
				<a
					href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
					class="k2-chrome-tabs__tab<?php echo $active ? ' is-active' : ''; ?>"
					role="tab"
					aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
				><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
			</div>
		</nav>
<?php } ?>
	</div>

<?php if ($queryError !== null) { ?>
	<p class="red">Could not load league honours.</p>
<?php } elseif (!$dataReady) { ?>
	<p class="muted">League honours data is not available on this database yet.</p>
<?php } ?>

<?php echo k2_lb_table_anchor_markup(); ?>
<?php k2_table_wrap_open(true); ?>
<?php $lbSort = k2_lb_table_sort_state(4); ?>
		<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>
			<thead>
				<tr>
					<th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">#</th>
					<th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
					<th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
					<th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
					<th<?php echo k2_lb_th(4, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="Gold" data-k2-help="<?php echo htmlspecialchars($goldHelp, ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(1); ?><span class="visually-hidden">Gold</span></th>
					<th<?php echo k2_lb_th(5, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="Silver" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_league_silver(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(2); ?><span class="visually-hidden">Silver</span></th>
					<th<?php echo k2_lb_th(6, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="Bronze" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_league_bronze(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(3); ?><span class="visually-hidden">Bronze</span></th>
					<th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_league_podium(), ENT_QUOTES, 'UTF-8'); ?>">Podium</th>
				</tr>
			</thead>
			<tbody class="black">
<?php foreach ($honoursRows as $row) { ?>
				<tr>
					<td<?php echo k2_lb_td(0, $lbSort); ?>></td>
					<td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?>><?php echo k2_player_link($row['id'], $row['name']); ?></td>
					<td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row['rating']); ?></td>
					<td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo (int) $row['games']; ?></td>
					<td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo (int) $row['gold']; ?></td>
					<td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo (int) $row['silver']; ?></td>
					<td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo (int) $row['bronze']; ?></td>
					<td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo (int) $row['podiums']; ?></td>
				</tr>
<?php } ?>
			</tbody>
		</table>
	<?php k2_table_wrap_close(); ?>
</div>
