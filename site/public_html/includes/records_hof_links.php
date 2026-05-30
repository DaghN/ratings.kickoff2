<?php
/**
 * Hall of Fame (server2.php) — leaderboard deep links on record values.
 *
 * Ratio/average HoF rows append provisional=0 (matches HoF >=20 eligibility). Other rows use
 * default leaderboard pool (both include toggles on). k2_sort / k2_dir see js/k2-table.js.
 * Anchor columns on each wing are unchanged; only active sort + row order follow the query params.
 * Stored *GameID columns remain in DB for a future game-record surface — not linked from HoF.
 */

declare(strict_types=1);

require_once __DIR__ . '/lb_player_filters.php';

/**
 * @return array{page: string, sort: int, dir: 'asc'|'desc'}|null
 */
function records_hof_lb_target(string $metric): ?array
{
	static $map = [
		'most_games' => ['page' => 'ranked7.php', 'sort' => 3, 'dir' => 'desc'],
		// ranked8.php has several small tables; no k2_sort deep link (calendar panels are not sortable).
		'peak_year' => ['page' => 'ranked8.php', 'sort' => -1, 'dir' => 'desc'],
		'peak_month' => ['page' => 'ranked8.php', 'sort' => -1, 'dir' => 'desc'],
		'peak_week' => ['page' => 'ranked8.php', 'sort' => -1, 'dir' => 'desc'],
		'peak_day' => ['page' => 'ranked8.php', 'sort' => -1, 'dir' => 'desc'],
		'play_streak_day' => ['page' => 'ranked4.php', 'sort' => 10, 'dir' => 'desc'],
		'play_streak_week' => ['page' => 'ranked4.php', 'sort' => 11, 'dir' => 'desc'],
		'most_wins' => ['page' => 'ranked7.php', 'sort' => 4, 'dir' => 'desc'],
		'most_goals' => ['page' => 'ranked2.php', 'sort' => 4, 'dir' => 'desc'],
		'most_dd' => ['page' => 'ranked3.php', 'sort' => 4, 'dir' => 'desc'],
		'most_cs' => ['page' => 'ranked3.php', 'sort' => 5, 'dir' => 'desc'],
		'most_opponents' => ['page' => 'ranked5.php', 'sort' => 4, 'dir' => 'desc'],
		'most_victims' => ['page' => 'ranked5.php', 'sort' => 5, 'dir' => 'desc'],
		'most_dd_victims' => ['page' => 'ranked5.php', 'sort' => 6, 'dir' => 'desc'],
		'most_cs_victims' => ['page' => 'ranked5.php', 'sort' => 7, 'dir' => 'desc'],
		'most_goals_one_game' => ['page' => 'ranked2.php', 'sort' => 9, 'dir' => 'desc'],
		'biggest_win_margin' => ['page' => 'ranked2.php', 'sort' => 11, 'dir' => 'desc'],
		'biggest_draw' => ['page' => 'ranked2.php', 'sort' => 13, 'dir' => 'desc'],
		'biggest_sum_goals' => ['page' => 'ranked2.php', 'sort' => 14, 'dir' => 'desc'],
		'peak_rating' => ['page' => 'ranked1.php', 'sort' => 4, 'dir' => 'desc'],
		'win_streak' => ['page' => 'ranked4.php', 'sort' => 4, 'dir' => 'desc'],
		'non_loss_streak' => ['page' => 'ranked4.php', 'sort' => 5, 'dir' => 'desc'],
		'draw_streak' => ['page' => 'ranked4.php', 'sort' => 6, 'dir' => 'desc'],
		'attack_avg' => ['page' => 'ranked2.php', 'sort' => 6, 'dir' => 'desc'],
		'defense_avg' => ['page' => 'ranked2.php', 'sort' => 7, 'dir' => 'asc'],
		'goal_ratio' => ['page' => 'ranked2.php', 'sort' => 8, 'dir' => 'desc'],
		'win_ratio' => ['page' => 'ranked7.php', 'sort' => 7, 'dir' => 'desc'],
		'dd_ratio' => ['page' => 'ranked3.php', 'sort' => 6, 'dir' => 'desc'],
		'cs_ratio' => ['page' => 'ranked3.php', 'sort' => 7, 'dir' => 'desc'],
	];

	return $map[$metric] ?? null;
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
