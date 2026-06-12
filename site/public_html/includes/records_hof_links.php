<?php
/**
 * Hall of Fame (hall-of-fame.php) — deep links on record values.
 *
 * Single-game spectacle rows link to Games highlights boards (with #k2-games-highlights); career/ratio
 * rows link to leaderboard
 * wings. Ratio/average HoF rows append provisional=0 (matches HoF >=20 eligibility). Other LB rows
 * use default leaderboard pool (both include toggles on). k2_sort / k2_dir see js/k2-table.js.
 */

declare(strict_types=1);

require_once __DIR__ . '/lb_player_filters.php';
require_once __DIR__ . '/games_highlights_helpers.php';

/**
 * @return array{page: string, sort: int, dir: 'asc'|'desc'}|null
 */
function records_hof_lb_target(string $metric): ?array
{
	static $map = [
		'most_games' => ['page' => 'lb-rating', 'sort' => 3, 'dir' => 'desc'],
		// activity-peaks has several small tables; no k2_sort deep link (calendar panels are not sortable).
		'peak_year' => ['page' => 'lb-activity-peaks', 'sort' => -1, 'dir' => 'desc'],
		'peak_month' => ['page' => 'lb-activity-peaks', 'sort' => -1, 'dir' => 'desc'],
		'peak_week' => ['page' => 'lb-activity-peaks', 'sort' => -1, 'dir' => 'desc'],
		'peak_day' => ['page' => 'lb-activity-peaks', 'sort' => -1, 'dir' => 'desc'],
		'play_streak_day' => ['page' => 'lb-streaks', 'sort' => 10, 'dir' => 'desc'],
		'play_streak_week' => ['page' => 'lb-streaks', 'sort' => 11, 'dir' => 'desc'],
		'most_wins' => ['page' => 'lb-rating', 'sort' => 4, 'dir' => 'desc'],
		'most_goals' => ['page' => 'lb-goals', 'sort' => 4, 'dir' => 'desc'],
		'most_dd' => ['page' => 'lb-double-digits', 'sort' => 4, 'dir' => 'desc'],
		'most_cs' => ['page' => 'lb-double-digits', 'sort' => 5, 'dir' => 'desc'],
		'most_opponents' => ['page' => 'lb-victims', 'sort' => 4, 'dir' => 'desc'],
		'most_victims' => ['page' => 'lb-victims', 'sort' => 5, 'dir' => 'desc'],
		'most_dd_victims' => ['page' => 'lb-victims', 'sort' => 6, 'dir' => 'desc'],
		'most_cs_victims' => ['page' => 'lb-victims', 'sort' => 7, 'dir' => 'desc'],
		'peak_rating' => ['page' => 'lb-peak-rating', 'sort' => 4, 'dir' => 'desc'],
		'win_streak' => ['page' => 'lb-streaks', 'sort' => 4, 'dir' => 'desc'],
		'non_loss_streak' => ['page' => 'lb-streaks', 'sort' => 5, 'dir' => 'desc'],
		'draw_streak' => ['page' => 'lb-streaks', 'sort' => 6, 'dir' => 'desc'],
		'attack_avg' => ['page' => 'lb-goals', 'sort' => 6, 'dir' => 'desc'],
		'defense_avg' => ['page' => 'lb-goals', 'sort' => 7, 'dir' => 'asc'],
		'goal_ratio' => ['page' => 'lb-goals', 'sort' => 8, 'dir' => 'desc'],
		'win_ratio' => ['page' => 'lb-rating', 'sort' => 7, 'dir' => 'desc'],
		'dd_ratio' => ['page' => 'lb-double-digits', 'sort' => 6, 'dir' => 'desc'],
		'cs_ratio' => ['page' => 'lb-double-digits', 'sort' => 7, 'dir' => 'desc'],
	];

	$row = $map[$metric] ?? null;
	if ($row === null) {
		return null;
	}

	return [
		'page' => k2_route($row['page']),
		'sort' => $row['sort'],
		'dir' => $row['dir'],
	];
}

/** HoF ratio/average leaders require NumberGames >= 20 — link with provisional=0 so the wing matches. */
function records_hof_metric_needs_established_pool(string $metric): bool
{
	static $metrics = [
		'attack_avg',
		'defense_avg',
		'goal_ratio',
		'win_ratio',
		'dd_ratio',
		'cs_ratio',
	];

	return in_array($metric, $metrics, true);
}

/** Single-game spectacle metrics → Games highlights board id (see K2_GAMES_HIGHLIGHT_BOARDS). */
function records_hof_highlights_board(string $metric): ?string
{
	static $map = [
		'most_goals_one_game' => 'most_goals_one_side',
		'biggest_win_margin' => 'biggest_wins',
		'biggest_draw' => 'biggest_draws',
		'biggest_sum_goals' => 'most_goals',
	];

	return $map[$metric] ?? null;
}

/**
 * Query params for HoF → ranked wing links; optional sort; provisional=0 only for ratio rows.
 *
 * @return array<string, string>
 */
function records_hof_lb_query_params(string $metric): array
{
	$target = records_hof_lb_target($metric);
	$params = k2_lb_filter_query_params([
		'include_inactive' => true,
		'include_provisional' => !records_hof_metric_needs_established_pool($metric),
	]);

	if ($target !== null && $target['sort'] >= 0) {
		$params['k2_sort'] = (string) $target['sort'];
		$params['k2_dir'] = $target['dir'];
	}

	return $params;
}

function records_hof_lb_href(string $metric): ?string
{
	$highlightsBoard = records_hof_highlights_board($metric);
	if ($highlightsBoard !== null) {
		return k2_games_highlights_href($highlightsBoard);
	}

	$target = records_hof_lb_target($metric);
	if ($target === null) {
		return null;
	}

	if ($target['sort'] < 0) {
		if (str_starts_with($metric, 'peak_')) {
			$period = substr($metric, 5);

			return $target['page'] . '#k2-peak-period-' . rawurlencode($period);
		}

		return $target['page'];
	}

	$params = records_hof_lb_query_params($metric);

	return $target['page'] . ($params === [] ? '' : '?' . http_build_query($params));
}

/** Leaderboard link on the value cell (skipped when value is "-"). */
function records_hof_lb_value_html(string $valueHtml, ?string $lbHref): string
{
	if ($lbHref === null || $lbHref === '' || $valueHtml === '-') {
		return $valueHtml;
	}

	return '<a class="k2-link-star" href="' . htmlspecialchars($lbHref, ENT_QUOTES, 'UTF-8') . '">' . $valueHtml . '</a>';
}
