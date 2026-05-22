<?php
/**
 * Helpers for profile lab mocks (profile_mock_*.php).
 */

function pm_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/**
 * ratedresults column (PascalCase in schema; tolerate lowercase mysqli keys).
 *
 * @param array<string, mixed> $row
 */
function pm_row_col(array $row, string $name): mixed
{
    if (array_key_exists($name, $row)) {
        return $row[$name];
    }
    $lower = strtolower($name);
    if (array_key_exists($lower, $row)) {
        return $row[$lower];
    }
    return null;
}

/**
 * Win / Draw / Loss from a ratedresults row (minimal columns OK).
 *
 * @param array<string, mixed> $row
 */
function pm_game_outcome(array $row, int $playerId): string
{
    $isA = (int) pm_row_col($row, 'idA') === $playerId;
    $actual = (float) pm_row_col($row, 'ActualScore');

    if (abs($actual - 0.5) < 0.001) {
        return 'Draw';
    }
    if (($isA && $actual >= 0.99) || (!$isA && $actual <= 0.01)) {
        return 'Win';
    }
    return 'Loss';
}

/**
 * Tally W-D-L vs one opponent without loading full row fields.
 *
 * @param array<string, mixed> $row
 */
function pm_h2h_tally_row(array $row, int $playerId, array &$tally): void
{
    $outcome = pm_game_outcome($row, $playerId);
    if ($outcome === 'Win') {
        $tally['wins']++;
    } elseif ($outcome === 'Draw') {
        $tally['draws']++;
    } else {
        $tally['losses']++;
    }
}

/**
 * @param array<string, mixed> $row ratedresults row
 * @return array{outcome: string, outcome_class: string, score: string, opponent_id: int, opponent_name: string, goals_for: int, goals_against: int, adjustment: float, date: string}
 */
function pm_parse_game_row(array $row, int $playerId): array
{
    $isA = (int) pm_row_col($row, 'idA') === $playerId;
    $goalsFor = $isA ? (int) pm_row_col($row, 'GoalsA') : (int) pm_row_col($row, 'GoalsB');
    $goalsAgainst = $isA ? (int) pm_row_col($row, 'GoalsB') : (int) pm_row_col($row, 'GoalsA');
    $opponentId = $isA ? (int) pm_row_col($row, 'idB') : (int) pm_row_col($row, 'idA');
    $opponentName = $isA ? (string) pm_row_col($row, 'NameB') : (string) pm_row_col($row, 'NameA');
    $actual = (float) pm_row_col($row, 'ActualScore');

    if (abs($actual - 0.5) < 0.001) {
        $outcome = 'Draw';
        $outcomeClass = 'pm-outcome--draw';
    } elseif (($isA && $actual >= 0.99) || (!$isA && $actual <= 0.01)) {
        $outcome = 'Win';
        $outcomeClass = 'pm-outcome--win';
    } else {
        $outcome = 'Loss';
        $outcomeClass = 'pm-outcome--loss';
    }

    $adjustment = $isA ? (float) pm_row_col($row, 'AdjustmentA') : (float) pm_row_col($row, 'AdjustmentB');
    $date = date('M j, Y', strtotime((string) pm_row_col($row, 'Date')));

    return [
        'outcome' => $outcome,
        'outcome_class' => $outcomeClass,
        'score' => $goalsFor . '–' . $goalsAgainst,
        'opponent_id' => $opponentId,
        'opponent_name' => $opponentName,
        'goals_for' => $goalsFor,
        'goals_against' => $goalsAgainst,
        'adjustment' => $adjustment,
        'date' => $date,
        'game_id' => (int) pm_row_col($row, 'id'),
    ];
}

/**
 * @param array<string, mixed> $row
 */
function pm_parse_highlight_row(array $row, int $playerId): array
{
    $parsed = pm_parse_game_row($row, $playerId);
    $parsed['year'] = date('Y', strtotime((string) pm_row_col($row, 'Date')));
    return $parsed;
}

function pm_adj_text(float $adj): string
{
    $sign = $adj >= 0 ? '+' : '';
    return $sign . number_format($adj, 1);
}

function pm2_format_busiest_month(?string $ym): string
{
    if ($ym === null || $ym === '') {
        return '—';
    }
    $ts = strtotime($ym . '-01');
    return $ts ? date('M Y', $ts) : $ym;
}

function pm2_format_busiest_day(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('M j, Y', $ts) : $date;
}

/**
 * Whole calendar years from first rated game date through today (0 in year one).
 */
function pm_years_on_ladder_since(string $firstRatedGameDate): int
{
    $firstRatedGameDate = trim($firstRatedGameDate);
    if ($firstRatedGameDate === '') {
        return 0;
    }

    try {
        $start = (new DateTimeImmutable($firstRatedGameDate))->setTime(0, 0);
        $today = new DateTimeImmutable('today');
    } catch (Exception $e) {
        return 0;
    }

    if ($start > $today) {
        return 0;
    }

    return max(0, (int) $start->diff($today)->y);
}

/** Display tenure as 0+ years, 1+ year, 8+ years, … */
function pm_tenure_plus_label(int $yearsOnLadder): string
{
    $yearsOnLadder = max(0, $yearsOnLadder);
    if ($yearsOnLadder === 1) {
        return '1+ year';
    }

    return $yearsOnLadder . '+ years';
}

/**
 * Ladder rank for a career total among Display = 1 players (same rule as rating rank).
 */
function pm_playertable_career_stat_rank(mysqli $con, int $playerId, string $column): int
{
    static $allowed = ['NumberGames', 'NumberWins', 'GoalsFor', 'DoubleDigits', 'DifferentOpponents'];
    if (!in_array($column, $allowed, true)) {
        return 0;
    }

    $playerId = (int) $playerId;
    $sql = 'SELECT COUNT(*) + 1 AS r FROM playertable WHERE Display = 1 AND `'
        . $column . '` > (SELECT `' . $column . '` FROM playertable WHERE id = ' . $playerId . ')';
    $result = mysqli_query($con, $sql);
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return 0;
    }

    return max(1, (int) $row['r']);
}
