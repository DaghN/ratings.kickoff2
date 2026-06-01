<?php
/**
 * Shared player-perspective rated-game table row (`individual3.php`).
 *
 * @see docs/k2-table-and-games-plan.md Phase 7B
 */

function k2_player_game_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function k2_player_game_player_link(int $id, string $name): string
{
    return '<a href="individual1.php?id=' . $id . '">' . k2_player_game_h($name) . '</a>';
}

/**
 * @param array<string, mixed> $row ratedresults assoc row
 * @return array<string, mixed>
 */
function k2_player_game_normalize_row(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'Date' => (string) ($row['Date'] ?? ''),
        'idA' => (int) ($row['idA'] ?? 0),
        'NameA' => (string) ($row['NameA'] ?? ''),
        'idB' => (int) ($row['idB'] ?? 0),
        'NameB' => (string) ($row['NameB'] ?? ''),
        'RatingA' => (float) ($row['RatingA'] ?? 0),
        'RatingB' => (float) ($row['RatingB'] ?? 0),
        'GoalsA' => (int) ($row['GoalsA'] ?? 0),
        'GoalsB' => (int) ($row['GoalsB'] ?? 0),
        'ExpectedScoreA' => (float) ($row['ExpectedScoreA'] ?? 0),
        'ExpectedScoreB' => (float) ($row['ExpectedScoreB'] ?? 0),
        'ActualScore' => (float) ($row['ActualScore'] ?? -1),
        'AdjustmentA' => (float) ($row['AdjustmentA'] ?? 0),
        'AdjustmentB' => (float) ($row['AdjustmentB'] ?? 0),
        'SumOfGoals' => (int) ($row['SumOfGoals'] ?? 0),
        'GoalDifference' => (int) ($row['GoalDifference'] ?? 0),
    ];
}

function k2_player_game_date_html(string $date): string
{
    $ts = strtotime($date);
    $text = $ts !== false ? date('M d Y, H:i', $ts) : $date;

    return k2_player_game_h($text);
}

function k2_player_game_signed_number_html(float $value): string
{
    $text = number_format($value, 1);
    if ($value >= 0) {
        return '<span class="blue">+' . $text . '</span>';
    }

    return '<span class="red">' . $text . '</span>';
}

/** 0-based column index for each server-side `sort` query key on `individual3.php`. */
function k2_player_game_sort_col_index(string $sortKey): int
{
    $map = [
        'id' => 0,
        'date' => 1,
        'team_a' => 2,
        'team_b' => 5,
        'result' => 6,
        'opponent' => 7,
        'for' => 8,
        'against' => 9,
        'diff' => 10,
        'sum' => 11,
        'player_rating' => 12,
        'opponent_rating' => 13,
        'es' => 14,
        'adjustment' => 15,
    ];

    return $map[$sortKey] ?? 0;
}

function k2_player_game_td(string $content, int $colIndex, int $sortedColIndex, string $extraClass = ''): string
{
    $classes = [];
    if ($extraClass !== '') {
        $classes[] = $extraClass;
    }
    if ($colIndex === $sortedColIndex) {
        $classes[] = 'k2-table-col-sorted';
    }
    $classAttr = $classes !== [] ? ' class="' . implode(' ', $classes) . '"' : '';

    return '<td' . $classAttr . '>' . $content . '</td>';
}

/**
 * @param array<string, mixed> $row normalized or raw ratedresults row
 */
function k2_player_game_row_html(array $row, int $playerId, int $sortedColIndex = 0): string
{
    $game = k2_player_game_normalize_row($row);
    $isPlayerA = $game['idA'] === $playerId;
    $opponentId = $isPlayerA ? $game['idB'] : $game['idA'];
    $opponentName = $isPlayerA ? $game['NameB'] : $game['NameA'];
    $goalsFor = $isPlayerA ? $game['GoalsA'] : $game['GoalsB'];
    $goalsAgainst = $isPlayerA ? $game['GoalsB'] : $game['GoalsA'];
    $playerRating = $isPlayerA ? $game['RatingA'] : $game['RatingB'];
    $opponentRating = $isPlayerA ? $game['RatingB'] : $game['RatingA'];
    $expectedScore = $isPlayerA ? $game['ExpectedScoreA'] : $game['ExpectedScoreB'];
    $adjustment = $isPlayerA ? $game['AdjustmentA'] : $game['AdjustmentB'];
    $isDraw = abs($game['ActualScore'] - 0.5) < 0.001;
    $isWin = !$isDraw && (
        ($isPlayerA && abs($game['ActualScore'] - 1.0) < 0.001)
        || (!$isPlayerA && abs($game['ActualScore']) < 0.001)
    );

    if ($isWin) {
        $resultCell = '<span class="blue">Win</span>';
    } elseif ($isDraw) {
        $resultCell = '-';
    } else {
        $resultCell = '<span class="red">Loss</span>';
    }

    if ($isDraw) {
        $diffCell = (string) $game['GoalDifference'];
    } elseif (!$isWin) {
        $diffCell = '<span class="red">' . -$game['GoalDifference'] . '</span>';
    } else {
        $diffCell = '<span class="blue">' . $game['GoalDifference'] . '</span>';
    }

    return '<tr>'
        . k2_player_game_td('<a href="game.php?id=' . $game['id'] . '">' . $game['id'] . '</a>', 0, $sortedColIndex)
        . k2_player_game_td(k2_player_game_date_html($game['Date']), 1, $sortedColIndex, 'k2-table-cell--pad-left-xs k2-table-cell--pad-right-xl')
        . k2_player_game_td(k2_player_game_player_link($game['idA'], $game['NameA']), 2, $sortedColIndex)
        . k2_player_game_td((string) $game['GoalsA'], 3, $sortedColIndex)
        . k2_player_game_td((string) $game['GoalsB'], 4, $sortedColIndex, 'k2-table-cell--left')
        . k2_player_game_td(k2_player_game_player_link($game['idB'], $game['NameB']), 5, $sortedColIndex, 'k2-table-cell--left')
        . k2_player_game_td($resultCell, 6, $sortedColIndex, 'k2-table-cell--left k2-table-cell--pad-left-xl')
        . k2_player_game_td(k2_player_game_player_link($opponentId, $opponentName), 7, $sortedColIndex, 'k2-table-cell--left')
        . k2_player_game_td((string) $goalsFor, 8, $sortedColIndex)
        . k2_player_game_td((string) $goalsAgainst, 9, $sortedColIndex)
        . k2_player_game_td($diffCell, 10, $sortedColIndex)
        . k2_player_game_td((string) $game['SumOfGoals'], 11, $sortedColIndex)
        . k2_player_game_td((string) (int) round($playerRating), 12, $sortedColIndex)
        . k2_player_game_td((string) (int) round($opponentRating), 13, $sortedColIndex)
        . k2_player_game_td(number_format(100 * $expectedScore, 1) . '%', 14, $sortedColIndex)
        . k2_player_game_td(k2_player_game_signed_number_html($adjustment), 15, $sortedColIndex)
        . '</tr>';
}
