<?php
/**
 * Shared player-perspective rated-game table row (`player/games.php`).
 *
 * @see docs/k2-table-and-games-plan.md Phase 7B
 */

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_routes.php';

function k2_player_game_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function k2_player_game_player_link(int $id, string $name): string
{
    return '<a href="' . k2_h(k2_player_profile_href($id)) . '">' . k2_player_game_h($name) . '</a>';
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

function k2_player_game_date_html(string $date, bool $utcDayView = false): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date, new DateTimeZone('UTC'));
    if ($dt === false) {
        $ts = strtotime($date);
        if ($ts === false) {
            return k2_player_game_h($date);
        }
        $dt = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('UTC'));
    }

    if ($utcDayView) {
        return k2_player_game_h($dt->format('H:i'));
    }

    return k2_player_game_h($dt->format('M j Y, H:i'));
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
 * Win/draw/loss from scoreline (ground truth goals).
 *
 * @return array{is_win: bool, is_draw: bool, is_loss: bool}
 */
function k2_player_game_outcome_from_goals(int $goalsFor, int $goalsAgainst): array
{
    if ($goalsFor > $goalsAgainst) {
        return ['is_win' => true, 'is_draw' => false, 'is_loss' => false];
    }
    if ($goalsFor < $goalsAgainst) {
        return ['is_win' => false, 'is_draw' => false, 'is_loss' => true];
    }

    return ['is_win' => false, 'is_draw' => true, 'is_loss' => false];
}

function k2_player_game_result_html(bool $isWin, bool $isDraw): string
{
    if ($isWin) {
        return '<span class="blue">Win</span>';
    }
    if ($isDraw) {
        return 'Draw';
    }

    return '<span class="red">Loss</span>';
}

function k2_player_game_diff_html(int $goalDifference, bool $isWin, bool $isDraw): string
{
    if ($isDraw) {
        return (string) $goalDifference;
    }
    if (!$isWin) {
        return '<span class="red">' . -$goalDifference . '</span>';
    }

    return '<span class="blue">+' . $goalDifference . '</span>';
}

/** 0-based column index for each server-side `sort` query key on `player/games.php`. */
function k2_player_game_sort_col_index(string $sortKey): int
{
    $map = [
        'id' => 0,
        'date' => 1,
        'team_a' => 2,
        'team_b' => 5,
        'result' => 6,
        'opponent' => 7,
        'goals_for' => 8,
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

function k2_player_game_td(string $content, int $colIndex, int $sortedColIndex, string $extraClass = '', int $anchorCol = -1): string
{
    $classes = [];
    if ($extraClass !== '') {
        $classes[] = $extraClass;
    }
    if ($anchorCol >= 0 && $colIndex === $anchorCol) {
        $classes[] = 'k2-table-anchor-cell';
    } elseif ($colIndex === $sortedColIndex) {
        $classes[] = 'k2-table-col-sorted';
    }
    $classAttr = $classes !== [] ? ' class="' . implode(' ', $classes) . '"' : '';

    return '<td' . $classAttr . '>' . $content . '</td>';
}

/**
 * @param array<string, mixed> $row normalized or raw ratedresults row
 */
function k2_player_game_row_html(array $row, int $playerId, int $sortedColIndex = 0, bool $utcDayView = false): string
{
    $processed = k2_rated_game_is_processed($row);
    $game = k2_player_game_normalize_row($row);
    $isPlayerA = $game['idA'] === $playerId;
    $opponentId = $isPlayerA ? $game['idB'] : $game['idA'];
    $opponentName = $isPlayerA ? $game['NameB'] : $game['NameA'];
    $goalsFor = $isPlayerA ? $game['GoalsA'] : $game['GoalsB'];
    $goalsAgainst = $isPlayerA ? $game['GoalsB'] : $game['GoalsA'];
    $sumGoals = (int) $game['GoalsA'] + (int) $game['GoalsB'];
    $goalDiff = abs((int) $game['GoalsA'] - (int) $game['GoalsB']);

    if ($processed) {
        $playerRating = $isPlayerA ? $game['RatingA'] : $game['RatingB'];
        $opponentRating = $isPlayerA ? $game['RatingB'] : $game['RatingA'];
        $expectedScore = $isPlayerA ? $game['ExpectedScoreA'] : $game['ExpectedScoreB'];
        $adjustment = $isPlayerA ? $game['AdjustmentA'] : $game['AdjustmentB'];
        $isDraw = abs($game['ActualScore'] - 0.5) < 0.001;
        $isWin = !$isDraw && (
            ($isPlayerA && abs($game['ActualScore'] - 1.0) < 0.001)
            || (!$isPlayerA && abs($game['ActualScore']) < 0.001)
        );
        $sumCell = (string) $game['SumOfGoals'];
        $diffCell = k2_player_game_diff_html((int) $game['GoalDifference'], $isWin, $isDraw);
    } else {
        $outcome = k2_player_game_outcome_from_goals($goalsFor, $goalsAgainst);
        $isWin = $outcome['is_win'];
        $isDraw = $outcome['is_draw'];
        $playerRating = 0.0;
        $opponentRating = 0.0;
        $expectedScore = 0.0;
        $adjustment = 0.0;
        $sumCell = (string) $sumGoals;
        $diffCell = k2_player_game_diff_html($goalDiff, $isWin, $isDraw);
    }

    $resultCell = k2_player_game_result_html($isWin, $isDraw);
    $dash = k2_fmt_dash();
    $playerRatingCell = $processed ? (string) (int) round($playerRating) : $dash;
    $opponentRatingCell = $processed ? (string) (int) round($opponentRating) : $dash;
    $esCell = $processed ? number_format(100 * $expectedScore, 1) . '%' : $dash;
    $adjustmentCell = $processed ? k2_player_game_signed_number_html($adjustment) : $dash;

    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    $goalsACell = $goalsA > $goalsB
        ? '<strong class="blue">' . $goalsA . '</strong>'
        : (string) $goalsA;
    $goalsBCell = $goalsB > $goalsA
        ? '<strong class="blue">' . $goalsB . '</strong>'
        : (string) $goalsB;

    return '<tr>'
        . k2_player_game_td('<a href="' . k2_player_game_h(k2_game_page_url((int) $game['id'])) . '">' . $game['id'] . '</a>', 0, $sortedColIndex)
        . k2_player_game_td(k2_player_game_date_html($game['Date'], $utcDayView), 1, $sortedColIndex, 'k2-table-cell--pad-left-xs k2-table-cell--pad-right-xl')
        . k2_player_game_td(k2_player_game_player_link($game['idA'], $game['NameA']), 2, $sortedColIndex)
        . k2_player_game_td($goalsACell, 3, $sortedColIndex)
        . k2_player_game_td($goalsBCell, 4, $sortedColIndex, 'k2-table-cell--left')
        . k2_player_game_td(k2_player_game_player_link($game['idB'], $game['NameB']), 5, $sortedColIndex, 'k2-table-cell--left')
        . k2_player_game_td($resultCell, 6, $sortedColIndex, 'k2-table-cell--left k2-table-cell--pad-left-xl')
        . k2_player_game_td(k2_player_game_player_link($opponentId, $opponentName), 7, $sortedColIndex, 'k2-table-cell--left')
        . k2_player_game_td((string) $goalsFor, 8, $sortedColIndex)
        . k2_player_game_td((string) $goalsAgainst, 9, $sortedColIndex)
        . k2_player_game_td($diffCell, 10, $sortedColIndex)
        . k2_player_game_td($sumCell, 11, $sortedColIndex)
        . k2_player_game_td($playerRatingCell, 12, $sortedColIndex)
        . k2_player_game_td($opponentRatingCell, 13, $sortedColIndex)
        . k2_player_game_td($esCell, 14, $sortedColIndex)
        . k2_player_game_td($adjustmentCell, 15, $sortedColIndex)
        . '</tr>';
}
