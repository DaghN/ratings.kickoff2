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

/**
 * @param array<string, mixed> $row normalized or raw ratedresults row
 */
function k2_player_game_row_html(array $row, int $playerId): string
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
        . '<td><a href="game.php?id=' . $game['id'] . '">' . $game['id'] . '</a></td>'
        . '<td class="k2-table-cell--pad-left-xs k2-table-cell--pad-right-xl">' . k2_player_game_date_html($game['Date']) . '</td>'
        . '<td>' . k2_player_game_player_link($game['idA'], $game['NameA']) . '</td>'
        . '<td>' . $game['GoalsA'] . '</td>'
        . '<td class="k2-table-cell--left">' . $game['GoalsB'] . '</td>'
        . '<td class="k2-table-cell--left">' . k2_player_game_player_link($game['idB'], $game['NameB']) . '</td>'
        . '<td class="k2-table-cell--left k2-table-cell--pad-left-xl">' . $resultCell . '</td>'
        . '<td class="k2-table-cell--left">' . k2_player_game_player_link($opponentId, $opponentName) . '</td>'
        . '<td>' . $goalsFor . '</td>'
        . '<td>' . $goalsAgainst . '</td>'
        . '<td>' . $diffCell . '</td>'
        . '<td>' . $game['SumOfGoals'] . '</td>'
        . '<td>' . (int) round($playerRating) . '</td>'
        . '<td>' . (int) round($opponentRating) . '</td>'
        . '<td>' . number_format(100 * $expectedScore, 1) . '%</td>'
        . '<td>' . k2_player_game_signed_number_html($adjustment) . '</td>'
        . '</tr>';
}
