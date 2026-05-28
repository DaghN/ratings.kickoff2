<?php
/**
 * Status — paired Activity + Points period competitions.
 *
 * Requires status_room_section helpers (k2_status_render_league_table, k2_status_h, etc.)
 * and $k2StatusRoom['period_competitions'] from k2_status_load_room().
 */
declare(strict_types=1);

if (!function_exists('k2_format_period_activity_label')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
}

if (!function_exists('k2_status_render_activity_competition_table')) {
    /**
     * @param array<int, array{rank: int, player_id: int, player_name: string, games: int}> $entries
     */
    function k2_status_render_activity_competition_table(array $entries, bool $showPodiumMedals = false): void
    {
        if ($entries === []) {
            return;
        }
        ?>
			<div class="k2-table-wrap k2-table-wrap--compact">
				<table class="k2-table k2-status-table k2-status-table--dense k2-status-period-competitions__activity-table<?php echo $showPodiumMedals ? ' k2-status-table--podium' : ''; ?>">
					<thead>
						<tr>
							<th class="k2-status-table__num">#</th>
							<th class="k2-status-table__player">Player</th>
							<th class="k2-status-table__num">Games</th>
<?php if ($showPodiumMedals) { ?>
							<th class="k2-status-table__medal" scope="col"><span class="visually-hidden">Award</span></th>
<?php } ?>
						</tr>
					</thead>
					<tbody class="black">
<?php
        foreach ($entries as $entry) {
            $rank = (int) $entry['rank'];
            echo '<tr>';
            echo '<td class="k2-status-table__num">' . $rank . '</td>';
            echo '<td class="k2-status-table__player">' . k2_status_player_link((int) $entry['player_id'], (string) $entry['player_name']) . '</td>';
            echo '<td class="k2-status-table__num">' . (int) $entry['games'] . '</td>';
            if ($showPodiumMedals) {
                echo '<td class="k2-status-table__medal">' . ($rank <= 3 ? k2_status_league_podium_medal($rank) : '') . '</td>';
            }
            echo '</tr>';
        }
        ?>
					</tbody>
				</table>
			</div>
<?php
    }
}

if (!function_exists('k2_status_period_competition_points_panel_attrs')) {
    /**
     * @param array<string, mixed>|null $league
     * @return array<string, string>
     */
    function k2_status_period_competition_points_panel_attrs(?array $league, int $serverNowEpoch): array
    {
        if ($league === null) {
            return [
                'data-league-meta-text' => '',
                'data-league-period-label' => '',
                'data-league-total-games' => '0',
                'data-league-end-label' => '',
                'data-league-end-epoch' => '0',
                'data-league-period' => '',
            ];
        }

        $serverNow = new DateTimeImmutable('@' . $serverNowEpoch);
        $metaText = k2_status_league_meta_line_for_clock($league, $serverNow);
        $endTs = strtotime((string) ($league['end'] ?? ''));

        return [
            'data-league-meta-text' => $metaText,
            'data-league-period-label' => (string) ($league['label'] ?? ''),
            'data-league-total-games' => (string) (int) ($league['total_games'] ?? 0),
            'data-league-end-label' => k2_status_league_end_label($league),
            'data-league-end-epoch' => $endTs === false ? '0' : (string) (int) $endTs,
            'data-league-period' => (string) ($league['period'] ?? ''),
        ];
    }
}

if (!function_exists('k2_status_period_competition_show_medals')) {
    function k2_status_period_competition_show_medals(?array $points, int $serverNowEpoch): bool
    {
        if ($points === null) {
            return false;
        }
        $endTs = strtotime((string) ($points['end'] ?? ''));

        return $endTs !== false && $endTs <= $serverNowEpoch;
    }
}

if (!function_exists('k2_status_period_competitions_calendar_icon_svg')) {
    function k2_status_period_competitions_calendar_icon_svg(): string
    {
        return '<svg class="k2-status-period-competitions__calendar-svg" width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">'
            . '<rect x="2.5" y="4" width="13" height="11.5" rx="2" stroke="currentColor" stroke-width="1.3"/>'
            . '<path d="M2.5 7.75h13" stroke="currentColor" stroke-width="1.3"/>'
            . '<path d="M6 2.25V5.25M12 2.25V5.25" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>'
            . '<g class="k2-status-period-competitions__calendar-dots" fill="currentColor">'
            . '<circle cx="6" cy="10.35" r="0.8"/><circle cx="9" cy="10.35" r="0.8"/><circle cx="12" cy="10.35" r="0.8"/>'
            . '<circle cx="6" cy="13.1" r="0.8"/><circle cx="9" cy="13.1" r="0.8"/><circle cx="12" cy="13.1" r="0.8"/>'
            . '</g></svg>';
    }
}

$competitions = is_array($k2StatusRoom['period_competitions'] ?? null) ? $k2StatusRoom['period_competitions'] : [];
$compPeriods = is_array($competitions['periods'] ?? null) ? $competitions['periods'] : [];
$defaultPeriod = (string) ($competitions['default_period'] ?? 'week');
$activityLimit = (int) ($competitions['activity_limit'] ?? 0);
$currentKeys = is_array($competitions['current_keys'] ?? null) ? $competitions['current_keys'] : [];
$dayMin = (string) ($competitions['day_min'] ?? date('Y-m-d'));
$dayMax = (string) ($competitions['day_max'] ?? date('Y-m-d'));
$firstRatedDay = (string) ($competitions['first_rated_day'] ?? $dayMin);
$weekChoices = is_array($competitions['week_choices'] ?? null) ? $competitions['week_choices'] : [];
$monthChoices = is_array($competitions['month_choices'] ?? null) ? $competitions['month_choices'] : [];
$yearChoices = is_array($competitions['year_choices'] ?? null) ? $competitions['year_choices'] : [];

$periodTabLabels = [
    'day' => k2_status_period_segment_label('day'),
    'week' => k2_status_period_segment_label('week'),
    'month' => k2_status_period_segment_label('month'),
    'year' => k2_status_period_segment_label('year'),
];

$navBounds = [
    'day' => ['min' => $dayMin, 'max' => $dayMax],
    'week' => [
        'min' => $weekChoices !== [] ? (string) $weekChoices[array_key_last($weekChoices)] : '',
        'max' => (string) ($currentKeys['week'] ?? ($weekChoices[0] ?? '')),
    ],
    'month' => [
        'min' => $monthChoices !== [] ? (string) $monthChoices[array_key_last($monthChoices)] : '',
        'max' => (string) ($currentKeys['month'] ?? ($monthChoices[0] ?? '')),
    ],
    'year' => [
        'min' => $yearChoices !== [] ? (string) $yearChoices[array_key_last($yearChoices)] : '',
        'max' => (string) ($currentKeys['year'] ?? ($yearChoices[0] ?? '')),
    ],
];

$initialMeta = '';
$initialBundle = is_array($compPeriods[$defaultPeriod] ?? null) ? $compPeriods[$defaultPeriod] : [];
$initialPoints = is_array($initialBundle['points'] ?? null) ? $initialBundle['points'] : null;
$initialPointsError = $initialBundle['points_error'] ?? null;
$initialActivity = is_array($initialBundle['activity'] ?? null) ? $initialBundle['activity'] : [];
$initialActivityEntries = is_array($initialActivity['entries'] ?? null) ? $initialActivity['entries'] : [];
$initialActivityError = $initialActivity['error'] ?? null;
$initialPanelAttrs = k2_status_period_competition_points_panel_attrs($initialPoints, $serverNowEpoch);
$initialShowMedals = k2_status_period_competition_show_medals($initialPoints, $serverNowEpoch);
if ($initialPoints !== null) {
    $initialMeta = k2_status_league_meta_html_for_clock(
        $initialPoints,
        new DateTimeImmutable('@' . $serverNowEpoch)
    );
}

$podiumMedalHtml = [
    '1' => k2_status_league_podium_medal(1),
    '2' => k2_status_league_podium_medal(2),
    '3' => k2_status_league_podium_medal(3),
];
?>
		<section
			class="k2-status-panel k2-status-panel--tight k2-status-room__panel-period-competitions k2-status-period-competitions"
			aria-labelledby="k2-status-leagues-title"
			data-k2-status-period-competitions
			data-default-period="<?php echo k2_status_h($defaultPeriod); ?>"
			data-server-now-epoch="<?php echo (int) $serverNowEpoch; ?>"
			data-activity-limit="<?php echo (int) $activityLimit; ?>"
			data-current-keys='<?php echo htmlspecialchars(json_encode($currentKeys, JSON_UNESCAPED_UNICODE), ENT_COMPAT, 'UTF-8'); ?>'
			data-nav-bounds='<?php echo htmlspecialchars(json_encode($navBounds, JSON_UNESCAPED_UNICODE), ENT_COMPAT, 'UTF-8'); ?>'
			data-first-rated-day="<?php echo k2_status_h($firstRatedDay); ?>"
			data-podium-medals="<?php echo k2_status_h(json_encode($podiumMedalHtml, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>"
			data-active-period="<?php echo k2_status_h($defaultPeriod); ?>"
			data-competition-prewarm="1"
		>
			<div class="k2-status-period-competitions__intro">
				<h2 id="k2-status-leagues-title" class="k2-panel-heading">Leagues</h2>
			</div>

			<div class="k2-status-period-competitions__controls">
				<div class="k2-status-period-competitions__period-tabs" role="tablist" aria-label="League period">
<?php foreach (['day', 'week', 'month', 'year'] as $period) {
    $active = $period === $defaultPeriod;
    ?>
					<button
						type="button"
						class="k2-status-period-competitions__period-btn<?php echo $active ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
						data-competition-period="<?php echo k2_status_h($period); ?>"
					><?php echo k2_status_h($periodTabLabels[$period] ?? $period); ?></button>
<?php } ?>
				</div>

				<div class="k2-status-period-competitions__period-nav">
					<button
						type="button"
						class="k2-status-period-competitions__step-btn k2-status-period-competitions__step-btn--prev"
						data-competition-step="prev"
						aria-label="Previous period"
					><span class="k2-status-period-competitions__step-chevron" aria-hidden="true"></span></button>
					<div class="k2-status-period-competitions__picker-row" data-competition-picker-row>
<?php foreach (['day', 'week', 'month', 'year'] as $period) {
    $pickerId = 'k2-status-competition-archive-' . $period;
    $selectedKey = (string) ($currentKeys[$period] ?? '');
    $pickerHidden = $period !== $defaultPeriod;
    ?>
						<div class="k2-status-period-competitions__archive-picker" data-archive-picker-period="<?php echo k2_status_h($period); ?>"<?php echo $pickerHidden ? ' hidden' : ''; ?>>
<?php if ($period === 'day') { ?>
							<span class="k2-status-day-picker">
								<span class="server-period-activity-leaderboard__date-control">
									<input type="hidden" id="<?php echo k2_status_h($pickerId); ?>" class="k2-status-period-competitions__archive-input k2-status-day-picker__value" data-archive-period="day" value="<?php echo k2_status_h($selectedKey); ?>" data-min="<?php echo k2_status_h($dayMin); ?>" data-max="<?php echo k2_status_h($dayMax); ?>" />
									<input type="text" class="k2-status-day-picker__fp-anchor" value="<?php echo k2_status_h($selectedKey); ?>" aria-hidden="true" tabindex="-1" autocomplete="off" readonly="readonly" />
									<button type="button" class="server-period-activity-leaderboard__calendar-button k2-status-period-competitions__calendar-btn" aria-label="Open calendar picker" aria-controls="<?php echo k2_status_h($pickerId); ?>">
										<?php echo k2_status_period_competitions_calendar_icon_svg(); ?>
									</button>
								</span>
							</span>
<?php } elseif ($period === 'week') { ?>
							<select id="<?php echo k2_status_h($pickerId); ?>" class="server-period-activity-leaderboard__input server-period-activity-leaderboard__input--week k2-status-period-competitions__archive-input" data-archive-period="week" aria-label="Select calendar week">
<?php foreach ($weekChoices as $weekStart) {
    $sel = $weekStart === $selectedKey ? ' selected="selected"' : ''; ?>
								<option value="<?php echo k2_status_h($weekStart); ?>"<?php echo $sel; ?>><?php echo k2_status_h(k2_format_period_activity_label('week', $weekStart)); ?></option>
<?php } ?>
							</select>
<?php } elseif ($period === 'month') { ?>
							<select id="<?php echo k2_status_h($pickerId); ?>" class="server-period-activity-leaderboard__input server-period-activity-leaderboard__input--month k2-status-period-competitions__archive-input" data-archive-period="month" aria-label="Select calendar month">
<?php foreach ($monthChoices as $ym) {
    $sel = $ym === $selectedKey ? ' selected="selected"' : ''; ?>
								<option value="<?php echo k2_status_h($ym); ?>"<?php echo $sel; ?>><?php echo k2_status_h(k2_format_period_activity_label('month', $ym)); ?></option>
<?php } ?>
							</select>
<?php } else { ?>
							<select id="<?php echo k2_status_h($pickerId); ?>" class="server-period-activity-leaderboard__input server-period-activity-leaderboard__input--year k2-status-period-competitions__archive-input" data-archive-period="year" aria-label="Select calendar year">
<?php foreach ($yearChoices as $y) {
    $sel = $y === $selectedKey ? ' selected="selected"' : ''; ?>
								<option value="<?php echo k2_status_h($y); ?>"<?php echo $sel; ?>><?php echo k2_status_h($y); ?></option>
<?php } ?>
							</select>
<?php } ?>
						</div>
<?php } ?>
					</div>
					<button
						type="button"
						class="k2-status-period-competitions__step-btn k2-status-period-competitions__step-btn--next"
						data-competition-step="next"
						aria-label="Next period"
					><span class="k2-status-period-competitions__step-chevron" aria-hidden="true"></span></button>
				</div>
			</div>

			<p class="k2-status-room__arc k2-status-period-competitions__meta" data-competition-meta><?php echo $initialMeta; ?></p>

			<div class="k2-status-period-competitions__views" data-competition-views>
				<div class="k2-status-period-competitions__view" data-competition-view>
					<div class="k2-status-period-competitions__pair">
						<div class="k2-status-period-competitions__col k2-status-period-competitions__col--activity">
							<div class="k2-status-period-competitions__col-stack">
							<h3 class="k2-panel-heading k2-status-period-competitions__col-title">Activity league</h3>
							<div class="k2-status-period-competitions__table-slot" data-competition-activity-body>
<?php if (!empty($initialActivityError)) { ?>
								<p class="k2-status-panel__empty">Could not load activity for this period.</p>
<?php } elseif ($initialActivityEntries === []) { ?>
								<p class="k2-status-panel__empty">No rated games in this period yet.</p>
<?php } else {
    k2_status_render_activity_competition_table($initialActivityEntries, $initialShowMedals);
} ?>
							</div>
							</div>
						</div>
						<div class="k2-status-period-competitions__col k2-status-period-competitions__col--points">
							<div class="k2-status-period-competitions__col-stack">
							<h3 class="k2-panel-heading k2-status-period-competitions__col-title">Points league</h3>
							<div
								class="k2-status-period-competitions__table-slot"
								data-competition-points-body
								data-competition-points-panel
								<?php
                                foreach ($initialPanelAttrs as $attr => $val) {
                                    echo ' ' . $attr . '="' . k2_status_h($val) . '"';
                                }
?>
							>
<?php if (!empty($initialPointsError) || $initialPoints === null) { ?>
								<p class="k2-status-panel__empty">Could not load points league for this period.</p>
<?php } elseif (($initialPoints['rows'] ?? []) === []) { ?>
								<p class="k2-status-panel__empty">No rated games in this period yet.</p>
<?php } else {
    k2_status_render_league_table($initialPoints, $initialShowMedals);
} ?>
							</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<p class="k2-status-period-competitions__status" data-competition-status hidden="hidden" aria-live="polite"></p>
			<div class="k2-status-period-competitions__editorial" data-k2-editorial hidden="hidden" aria-hidden="true"></div>
		</section>
