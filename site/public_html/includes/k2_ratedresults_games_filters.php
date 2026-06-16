<?php
/**
 * Shared ratedresults list filters (player games + realm All games).
 *
 * @see docs/k2-table-and-games-plan.md — All games phase 2
 */

declare(strict_types=1);

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
	string $periodAnchor = ''
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
