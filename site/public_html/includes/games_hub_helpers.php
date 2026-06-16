<?php
/**
 * Games hub shared helpers (Recent day buckets).
 */

function k2_games_day_label(int $offset, int $timestamp): string
{
	if ($offset === 0) {
		return 'Today &middot; ' . date('M j, Y', $timestamp);
	}
	if ($offset === 1) {
		return 'Yesterday &middot; ' . date('M j, Y', $timestamp);
	}

	return date('l', $timestamp) . ' &middot; ' . date('M j, Y', $timestamp);
}

/**
 * @return array{arc: ?array, recent14: int}
 */
function k2_games_hub_status_counts(mysqli $con): array
{
	require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';

	$arcError = null;
	$arc = k2_status_arc_ticker($con, $arcError);
	$recent14 = 0;
	$recentCountRes = mysqli_query(
		$con,
		'SELECT COUNT(*) AS c FROM `ratedresults` WHERE `Date` >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) '
		. 'AND `Date` < DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
	);
	if ($recentCountRes !== false) {
		$recentCountRow = mysqli_fetch_assoc($recentCountRes);
		mysqli_free_result($recentCountRes);
		$recent14 = (int) ($recentCountRow['c'] ?? 0);
	}

	return ['arc' => $arc, 'recent14' => $recent14];
}

function k2_games_hub_chapter_list_html(int $recent14Count): string
{
	return '<ul class="k2-hub-chapter__list">'
		. '<li><strong>Recent</strong> lists <span class="blue">' . number_format($recent14Count) . '</span> games from the last 14 days, day by day.</li>'
		. '<li><strong>Highlights</strong> surfaces all-time spectacles — goal feasts, huge draws, big wins.</li>'
		. '<li><strong>All games</strong> searches the full history with filters and sorting.</li>'
		. '</ul>';
}
