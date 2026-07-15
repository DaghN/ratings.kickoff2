<?php
/**
 * Shared ratedresults list filters (player games + realm All games).
 *
 * @see docs/k2-table-and-games-plan.md — All games phase 2
 */

declare(strict_types=1);

require_once __DIR__ . '/player_result_streaks.php';

const K2_PLAYER_GAMES_PAGE_SIZE = 500;

/** Hash target: just above player Games filter row (calendar day links, H2H chart deep links — not hero Games stat). */
const K2_PLAYER_GAMES_FILTERS_ANCHOR = 'k2-player-games-filters';

function k2_player_games_filters_anchor_fragment(): string
{
	return '#' . K2_PLAYER_GAMES_FILTERS_ANCHOR;
}

function k2_ratedresults_games_valid_result(string $value): string
{
	return in_array($value, ['all', 'win', 'draw', 'loss'], true) ? $value : 'all';
}

function k2_ratedresults_games_valid_goals_filter(int $value, array $validValues): int
{
	if ($value < 0) {
		return -1;
	}

	return isset($validValues[$value]) ? $value : -1;
}

/** Hero-signed goal difference label for player games filters (+N / −N / 0). */
function k2_player_games_hero_gd_label(int $gd): string
{
	if ($gd > 0) {
		return '+' . $gd;
	}
	if ($gd < 0) {
		return (string) $gd;
	}

	return '0';
}

function k2_ratedresults_games_valid_hero_gd_filter(?int $value, array $validValues): ?int
{
	if ($value === null) {
		return null;
	}

	return isset($validValues[$value]) ? $value : null;
}

function k2_ratedresults_games_valid_day(string $value): string
{
	$value = trim($value);
	if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
		return $value;
	}

	return '';
}

function k2_ratedresults_games_valid_period_type(string $value): string
{
	return in_array($value, ['day', 'week', 'month', 'year'], true) ? $value : '';
}

/**
 * UTC [start inclusive, end exclusive) bounds for activity period anchors.
 *
 * @return array{0: string, 1: string}
 */
function k2_ratedresults_games_period_bounds(string $periodType, string $anchorYmd): array
{
	$dt = new DateTimeImmutable($anchorYmd, new DateTimeZone('UTC'));
	if ($periodType === 'day') {
		return [$anchorYmd, $dt->modify('+1 day')->format('Y-m-d')];
	}
	if ($periodType === 'week') {
		return [$anchorYmd, $dt->modify('+7 days')->format('Y-m-d')];
	}
	if ($periodType === 'month') {
		return [$dt->format('Y-m-d'), $dt->modify('first day of next month')->format('Y-m-d')];
	}
	if ($periodType === 'year') {
		return [$dt->format('Y-01-01'), $dt->modify('+1 year')->format('Y-01-01')];
	}

	return ['', ''];
}

function k2_ratedresults_games_valid_year_mode(string $value): string
{
	return in_array($value, ['in', 'since', 'until'], true) ? $value : 'in';
}

function k2_ratedresults_games_valid_game_id(int $value): int
{
	return $value > 0 ? $value : 0;
}

function k2_ratedresults_games_valid_streak_type(string $value): string
{
	return in_array($value, K2_RESULT_STREAK_TYPES, true) ? $value : '';
}

/**
 * @return bool both games exist for this player and from <= to
 */
function k2_ratedresults_games_valid_streak_run(mysqli $con, int $playerId, int $fromGameId, int $toGameId): bool
{
	if ($playerId < 1 || $fromGameId < 1 || $toGameId < 1 || $fromGameId > $toGameId) {
		return false;
	}

	$stmt = $con->prepare(
		'SELECT COUNT(DISTINCT `id`) AS c FROM `ratedresults` '
		. 'WHERE `id` IN (?, ?) AND (`idA` = ? OR `idB` = ?)'
	);
	if ($stmt === false) {
		return false;
	}
	$stmt->bind_param('iiii', $fromGameId, $toGameId, $playerId, $playerId);
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res ? $res->fetch_assoc() : null;
	if ($res) {
		$res->free();
	}
	$stmt->close();

	return $row !== null && (int) ($row['c'] ?? 0) === 2;
}

/**
 * @param-out string $types
 * @param-out list<int|string> $params
 */
function k2_ratedresults_games_where_clause(
	int $playerId,
	string $resultFilter,
	int $opponentId,
	int $goalsScoredFilter,
	int $goalsConcededFilter,
	int $goalsSumFilter,
	string $utcDay,
	string &$types,
	array &$params,
	int $goalDiffFilter = -1,
	int $topScoreFilter = -1,
	int $year = 0,
	string $yearMode = '',
	string $periodType = '',
	string $periodAnchor = '',
	int $fromGameId = 0,
	int $toGameId = 0,
	?int $heroGoalDiffFilter = null
): string {
	$where = [];
	$types = '';
	$params = [];

	if ($playerId > 0) {
		$where[] = '(r.idA = ? OR r.idB = ?)';
		$types .= 'ii';
		$params = [$playerId, $playerId];
	} else {
		$where[] = '1=1';
	}

	if ($utcDay !== '') {
		$where[] = 'DATE(r.`Date`) = ?';
		$types .= 's';
		$params[] = $utcDay;
	} elseif ($fromGameId > 0 && $toGameId >= $fromGameId) {
		$where[] = 'r.`id` >= ? AND r.`id` <= ?';
		$types .= 'ii';
		$params[] = $fromGameId;
		$params[] = $toGameId;
	} elseif ($periodType !== '' && $periodAnchor !== '') {
		$periodType = k2_ratedresults_games_valid_period_type($periodType);
		$periodAnchor = k2_ratedresults_games_valid_day($periodAnchor);
		if ($periodType !== '' && $periodAnchor !== '' && $periodType !== 'day') {
			[$periodStart, $periodEnd] = k2_ratedresults_games_period_bounds($periodType, $periodAnchor);
			if ($periodStart !== '' && $periodEnd !== '') {
				$where[] = 'r.`Date` >= ? AND r.`Date` < ?';
				$types .= 'ss';
				$params[] = $periodStart;
				$params[] = $periodEnd;
			}
		}
	}

	if ($year > 0) {
		$yearMode = k2_ratedresults_games_valid_year_mode($yearMode);
		$yearStart = sprintf('%04d-01-01', $year);
		$yearEnd = sprintf('%04d-01-01', $year + 1);
		if ($yearMode === 'since') {
			$where[] = 'r.`Date` >= ?';
			$types .= 's';
			$params[] = $yearStart;
		} elseif ($yearMode === 'until') {
			$where[] = 'r.`Date` < ?';
			$types .= 's';
			$params[] = $yearEnd;
		} else {
			$where[] = 'r.`Date` >= ? AND r.`Date` < ?';
			$types .= 'ss';
			$params[] = $yearStart;
			$params[] = $yearEnd;
		}
	}

	if ($playerId > 0) {
		if ($resultFilter === 'win') {
			$where[] = '((r.idA = ? AND ABS(r.ActualScore - 1.0) < 0.001) OR (r.idB = ? AND ABS(r.ActualScore) < 0.001))';
			$types .= 'ii';
			$params[] = $playerId;
			$params[] = $playerId;
		} elseif ($resultFilter === 'draw') {
			$where[] = 'ABS(r.ActualScore - 0.5) < 0.001';
		} elseif ($resultFilter === 'loss') {
			$where[] = '((r.idA = ? AND ABS(r.ActualScore) < 0.001) OR (r.idB = ? AND ABS(r.ActualScore - 1.0) < 0.001))';
			$types .= 'ii';
			$params[] = $playerId;
			$params[] = $playerId;
		}

		if ($opponentId > 0) {
			$where[] = '((r.idA = ? AND r.idB = ?) OR (r.idB = ? AND r.idA = ?))';
			$types .= 'iiii';
			$params[] = $playerId;
			$params[] = $opponentId;
			$params[] = $playerId;
			$params[] = $opponentId;
		}

		if ($goalsScoredFilter >= 0) {
			$where[] = '((r.idA = ? AND r.GoalsA = ?) OR (r.idB = ? AND r.GoalsB = ?))';
			$types .= 'iiii';
			$params[] = $playerId;
			$params[] = $goalsScoredFilter;
			$params[] = $playerId;
			$params[] = $goalsScoredFilter;
		}

		if ($goalsConcededFilter >= 0) {
			$where[] = '((r.idA = ? AND r.GoalsB = ?) OR (r.idB = ? AND r.GoalsA = ?))';
			$types .= 'iiii';
			$params[] = $playerId;
			$params[] = $goalsConcededFilter;
			$params[] = $playerId;
			$params[] = $goalsConcededFilter;
		}

		if ($heroGoalDiffFilter !== null) {
			$where[] = '((r.idA = ? AND (r.GoalsA - r.GoalsB) = ?) OR (r.idB = ? AND (r.GoalsB - r.GoalsA) = ?))';
			$types .= 'iiii';
			$params[] = $playerId;
			$params[] = $heroGoalDiffFilter;
			$params[] = $playerId;
			$params[] = $heroGoalDiffFilter;
		}
	}

	if ($goalsSumFilter >= 0) {
		$where[] = 'r.SumOfGoals = ?';
		$types .= 'i';
		$params[] = $goalsSumFilter;
	}

	if ($goalDiffFilter >= 0) {
		$where[] = 'r.GoalDifference = ?';
		$types .= 'i';
		$params[] = $goalDiffFilter;
	}

	if ($topScoreFilter >= 0) {
		$where[] = 'GREATEST(r.GoalsA, r.GoalsB) = ?';
		$types .= 'i';
		$params[] = $topScoreFilter;
	}

	return implode(' AND ', $where);
}

function k2_ratedresults_games_filter_pick_active(string $selectedValue, string $idleValue): bool
{
	return (string) $selectedValue !== $idleValue;
}

function k2_ratedresults_games_filter_pick_trigger_class(string $selectedValue, string $idleValue): string
{
	return k2_ratedresults_games_filter_pick_active($selectedValue, $idleValue) ? 'k2-link-star' : '';
}
