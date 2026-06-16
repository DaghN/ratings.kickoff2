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
	string $yearMode = ''
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
