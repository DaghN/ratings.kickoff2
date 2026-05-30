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
<div class="k2-status-period-competitions k2-lb-league-honours">
	<div class="k2-status-period-competitions__controls">
		<div class="k2-status-period-competitions__period-tabs" role="tablist" aria-label="League honours view">
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
				class="k2-status-period-competitions__period-btn<?php echo $active ? ' is-active' : ''; ?>"
				role="tab"
				aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
			><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
		</div>
<?php if ($cup !== 'overall') { ?>
		<div class="k2-status-period-competitions__period-tabs" role="tablist" aria-label="League time span">
<?php foreach ($grainTabs as $grainId => $label) {
    $active = $grain === $grainId;
    $href = k2_lb_league_honours_href($cup, $grainId, $filterOpts);
    ?>
			<a
				href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"
				class="k2-status-period-competitions__period-btn<?php echo $active ? ' is-active' : ''; ?>"
				role="tab"
				aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
			><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
		</div>
<?php } ?>
	</div>

<?php if ($queryError !== null) { ?>
	<p class="red">Could not load league honours.</p>
<?php } elseif (!$dataReady) { ?>
	<p class="muted">League honours data is not available on this database yet.</p>
<?php } ?>

	<div class="k2-table-wrap">
		<table class="k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table ranked-table-pending" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="4" data-k2-default-sort="4" data-k2-default-direction="desc">
			<thead>
				<tr>
					<th data-k2-sort="number">#</th>
					<th class="k2-table-cell--left" data-k2-sort="text">Player</th>
					<th data-k2-sort="number">ELO rating</th>
					<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
					<th class="k2-lb-honours-medal-th" data-k2-sort="number" data-k2-tooltip-label="Gold" data-k2-help="<?php echo htmlspecialchars($goldHelp, ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(1); ?><span class="visually-hidden">Gold</span></th>
					<th class="k2-lb-honours-medal-th" data-k2-sort="number" data-k2-tooltip-label="Silver" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_league_silver(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(2); ?><span class="visually-hidden">Silver</span></th>
					<th class="k2-lb-honours-medal-th" data-k2-sort="number" data-k2-tooltip-label="Bronze" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_league_bronze(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(3); ?><span class="visually-hidden">Bronze</span></th>
					<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_league_podium(), ENT_QUOTES, 'UTF-8'); ?>">Podium</th>
				</tr>
			</thead>
			<tbody class="black">
<?php foreach ($honoursRows as $row) { ?>
				<tr>
					<td></td>
					<td class="k2-table-cell--left"><?php echo k2_player_link($row['id'], $row['name']); ?></td>
					<td><?php echo (int) round($row['rating']); ?></td>
					<td><?php echo (int) $row['games']; ?></td>
					<td><?php echo (int) $row['gold']; ?></td>
					<td><?php echo (int) $row['silver']; ?></td>
					<td><?php echo (int) $row['bronze']; ?></td>
					<td><?php echo (int) $row['podiums']; ?></td>
				</tr>
<?php } ?>
			</tbody>
		</table>
	</div>
</div>
