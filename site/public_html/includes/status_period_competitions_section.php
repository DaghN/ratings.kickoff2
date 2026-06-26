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

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_table_render.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_period_page.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';

if (!function_exists('k2_status_period_competition_league_col_title')) {
    function k2_status_period_competition_league_col_title(string $cup, string $period, string $periodStart): void
    {
        $label = k2_league_period_cup_label($cup);
        $href = $periodStart !== ''
            ? k2_league_period_href($cup, $period, $periodStart)
            : k2_league_period_landing_href(['cup' => $cup]);
        ?>
							<h3 class="k2-panel-heading k2-status-period-competitions__col-title">
								<a
									class="k2-link-star k2-status-period-competitions__col-title-link"
									href="<?php echo k2_status_h($href); ?>"
									data-competition-league-link="<?php echo k2_status_h($cup); ?>"
								><?php echo k2_status_h($label); ?> &rarr;</a>
							</h3>
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

        if (!function_exists('k2_status_league_end_epoch')) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
        }
        $serverNow = new DateTimeImmutable('@' . $serverNowEpoch);
        $metaText = k2_status_league_meta_line_for_clock($league, $serverNow);
        $endTs = k2_status_league_end_epoch($league);

        return [
            'data-league-meta-text' => $metaText,
            'data-league-period-label' => (string) ($league['label'] ?? ''),
            'data-league-total-games' => (string) (int) ($league['total_games'] ?? 0),
            'data-league-end-label' => k2_status_league_end_label($league),
            'data-league-end-epoch' => $endTs > 0 ? (string) $endTs : '0',
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
        if (!function_exists('k2_status_league_end_epoch')) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
        }
        $endTs = k2_status_league_end_epoch($points);

        return $endTs > 0 && $endTs <= $serverNowEpoch;
    }
}

if (!function_exists('k2_status_render_day_games_list')) {
    /**
     * @param list<array{id: int, name_a: string, name_b: string, goals_a: int, goals_b: int, at: string, id_a: int, id_b: int}> $games
     */
    function k2_status_render_day_games_list(array $games): void
    {
        if ($games === []) {
            echo '<p class="k2-status-panel__empty">—</p>';

            return;
        }
        ?>
							<ul class="k2-status-recency-list k2-status-day-games-list">
<?php foreach ($games as $g) { ?>
								<li>
									<span class="k2-status-recency-list__when"><?php echo k2_status_h(k2_status_short_time($g['at'])); ?></span>
									<span class="k2-status-match">
										<span class="k2-status-match__side"><?php echo k2_status_player_link($g['id_a'], $g['name_a']); ?></span>
										<span class="k2-status-score"><?php echo k2_status_score_html((int) $g['goals_a'], (int) $g['goals_b']); ?></span>
										<span class="k2-status-match__side"><?php echo k2_status_player_link($g['id_b'], $g['name_b']); ?></span>
									</span>
									<a class="k2-link-star k2-status-day-games-list__game" href="<?php echo htmlspecialchars(k2_game_page_url((int) $g['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $g['id']; ?></a>
								</li>
<?php } ?>
							</ul>
<?php
    }
}

if (!function_exists('k2_status_render_archive_listbox')) {
    /**
     * Themed week / month / year picker (replaces native &lt;select&gt; on Status Leagues).
     *
     * @param list<string> $choices
     */
    function k2_status_render_archive_listbox(string $period, string $pickerId, string $selectedKey, array $choices, string $ariaLabel): void
    {
        $selectedLabel = '';
        foreach ($choices as $choice) {
            $value = (string) $choice;
            if ($value === $selectedKey) {
                $selectedLabel = k2_format_period_activity_label($period, $value);
                break;
            }
        }
        if ($selectedLabel === '' && $selectedKey !== '') {
            $selectedLabel = k2_format_period_activity_label($period, $selectedKey);
        }
        $listboxId = $pickerId . '-listbox';
        ?>
							<div class="k2-archive-listbox" data-k2-archive-listbox data-archive-period="<?php echo k2_status_h($period); ?>">
								<input type="hidden" id="<?php echo k2_status_h($pickerId); ?>" class="k2-status-period-competitions__archive-input k2-archive-listbox__value" data-archive-period="<?php echo k2_status_h($period); ?>" value="<?php echo k2_status_h($selectedKey); ?>" />
								<button type="button" class="k2-archive-listbox__trigger server-period-activity-leaderboard__input server-period-activity-leaderboard__input--<?php echo k2_status_h($period); ?>" aria-label="<?php echo k2_status_h($ariaLabel); ?>" aria-haspopup="listbox" aria-expanded="false" aria-controls="<?php echo k2_status_h($listboxId); ?>">
									<span class="k2-archive-listbox__label"><?php echo k2_status_h($selectedLabel); ?></span>
									<span class="k2-archive-listbox__chevron" aria-hidden="true"></span>
								</button>
								<ul id="<?php echo k2_status_h($listboxId); ?>" class="k2-archive-listbox__panel" role="listbox" tabindex="-1" hidden="hidden">
<?php foreach ($choices as $choice) {
    $value = (string) $choice;
    $sel = $value === $selectedKey;
    $optClass = 'k2-archive-listbox__option' . ($sel ? ' is-selected' : '');
    ?>
									<li class="<?php echo k2_status_h($optClass); ?>" role="option" data-value="<?php echo k2_status_h($value); ?>" aria-selected="<?php echo $sel ? 'true' : 'false'; ?>"><?php echo k2_status_h(k2_format_period_activity_label($period, $value)); ?></li>
<?php } ?>
								</ul>
							</div>
<?php
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
$dayBundle = is_array($compPeriods['day'] ?? null) ? $compPeriods['day'] : [];
$initialDayGames = is_array($dayBundle['day_games'] ?? null) ? $dayBundle['day_games'] : [];
$initialDayGamesError = $dayBundle['day_games_error'] ?? null;
$initialDayKey = (string) ($currentKeys['day'] ?? '');
if ($initialPoints !== null) {
    $initialMeta = k2_status_league_meta_html_for_clock(
        $initialPoints,
        new DateTimeImmutable('@' . $serverNowEpoch)
    );
}
$initialLeaguePeriodStart = (string) ($currentKeys[$defaultPeriod] ?? '');

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
				<div class="k2-chrome-tabs__bar k2-status-period-competitions__period-tabs" role="tablist" aria-label="League period">
<?php foreach (['day', 'week', 'month', 'year'] as $period) {
    $active = $period === $defaultPeriod;
    ?>
					<button
						type="button"
						class="k2-chrome-tabs__tab k2-status-period-competitions__period-btn<?php echo $active ? ' is-active' : ''; ?>"
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
    $pickerClass = 'k2-status-period-competitions__archive-picker' . ($pickerHidden ? '' : ' is-active');
    ?>
						<div class="<?php echo k2_status_h($pickerClass); ?>" data-archive-picker-period="<?php echo k2_status_h($period); ?>"<?php echo $pickerHidden ? ' hidden' : ''; ?>>
<?php if ($period === 'day') {
    $dayLabel = $selectedKey !== '' ? k2_format_calendar_day_picker_label($selectedKey) : '';
    ?>
							<span class="k2-status-day-picker k2-archive-listbox k2-archive-listbox--day">
								<span class="server-period-activity-leaderboard__date-control">
									<input type="hidden" id="<?php echo k2_status_h($pickerId); ?>" class="k2-status-period-competitions__archive-input k2-status-day-picker__value" data-archive-period="day" value="<?php echo k2_status_h($selectedKey); ?>" data-min="<?php echo k2_status_h($dayMin); ?>" data-max="<?php echo k2_status_h($dayMax); ?>" />
									<input type="text" class="k2-status-day-picker__fp-anchor" value="<?php echo k2_status_h($selectedKey); ?>" aria-hidden="true" tabindex="-1" autocomplete="off" readonly="readonly" />
									<button type="button" class="k2-archive-listbox__trigger k2-status-day-picker__trigger server-period-activity-leaderboard__input server-period-activity-leaderboard__input--day" aria-label="Select calendar day" aria-expanded="false" aria-controls="<?php echo k2_status_h($pickerId); ?>">
										<span class="k2-archive-listbox__label" data-day-picker-label><?php echo k2_status_h($dayLabel); ?></span>
										<span class="k2-archive-listbox__chevron" aria-hidden="true"></span>
									</button>
								</span>
							</span>
<?php } elseif ($period === 'week') {
    k2_status_render_archive_listbox('week', $pickerId, $selectedKey, $weekChoices, 'Select calendar week');
} elseif ($period === 'month') {
    k2_status_render_archive_listbox('month', $pickerId, $selectedKey, $monthChoices, 'Select calendar month');
} else {
    k2_status_render_archive_listbox('year', $pickerId, $selectedKey, $yearChoices, 'Select calendar year');
} ?>
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
							<?php k2_status_period_competition_league_col_title('activity', $defaultPeriod, $initialLeaguePeriodStart); ?>
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
							<?php k2_status_period_competition_league_col_title('points', $defaultPeriod, $initialLeaguePeriodStart); ?>
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
					<div
						class="k2-status-period-competitions__day-games"
						data-competition-day-games
						data-day-games-key="<?php echo k2_status_h($initialDayKey); ?>"
						hidden="hidden"
					>
						<h3 class="k2-panel-heading k2-status-period-competitions__day-games-title">Games this day</h3>
						<div data-competition-day-games-body>
<?php if (!empty($initialDayGamesError)) { ?>
							<p class="k2-status-panel__empty">Could not load games for this day.</p>
<?php } else {
    k2_status_render_day_games_list($initialDayGames);
} ?>
						</div>
					</div>
				</div>
			</div>
			<p class="k2-status-period-competitions__status" data-competition-status hidden="hidden" aria-live="polite"></p>
		</section>
