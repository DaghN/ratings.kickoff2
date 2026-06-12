<?php
/**
 * Shared rated-game table row (game.php, Games tab / games.php).
 *
 * @see docs/k2-table-and-games-plan.md Phase 2
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_game_rating_adjustment.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';

function k2_rated_game_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * @param array<string, mixed> $row ratedresults assoc row
 * @return array<string, mixed>
 */
function k2_rated_game_normalize_row(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'Date' => (string) ($row['Date'] ?? ''),
        'idA' => (int) ($row['idA'] ?? 0),
        'idB' => (int) ($row['idB'] ?? 0),
        'NameA' => (string) ($row['NameA'] ?? ''),
        'NameB' => (string) ($row['NameB'] ?? ''),
        'RatingA' => (float) ($row['RatingA'] ?? 0),
        'RatingB' => (float) ($row['RatingB'] ?? 0),
        'RatingDifference' => (float) ($row['RatingDifference'] ?? 0),
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

/**
 * @param array<string, mixed> $game normalized or raw ratedresults row
 */
function k2_rated_game_is_a_win(array $game): bool
{
    return abs((float) ($game['ActualScore'] ?? -1) - 1.0) < 0.001;
}

/**
 * @param array<string, mixed> $game normalized or raw ratedresults row
 */
function k2_rated_game_is_b_win(array $game): bool
{
    return abs((float) ($game['ActualScore'] ?? -1)) < 0.001;
}

function k2_rated_game_is_a_win_from_goals(array $game): bool
{
    return (int) ($game['GoalsA'] ?? 0) > (int) ($game['GoalsB'] ?? 0);
}

function k2_rated_game_is_b_win_from_goals(array $game): bool
{
    return (int) ($game['GoalsB'] ?? 0) > (int) ($game['GoalsA'] ?? 0);
}

/**
 * @param array<string, mixed> $row raw or normalized ratedresults row
 */
function k2_rated_game_winner_html(array $row): string
{
    $game = k2_rated_game_normalize_row($row);
    $idA = (int) ($game['idA'] ?? 0);
    $idB = (int) ($game['idB'] ?? 0);
    $nameA = (string) ($game['NameA'] ?? '');
    $nameB = (string) ($game['NameB'] ?? '');

    if (!k2_rated_game_is_processed($row)) {
        if (k2_rated_game_is_a_win_from_goals($game)) {
            return '<a href="/player/profile.php?id=' . $idA . '">' . k2_rated_game_h($nameA) . '</a>';
        }
        if (k2_rated_game_is_b_win_from_goals($game)) {
            return '<a href="/player/profile.php?id=' . $idB . '">' . k2_rated_game_h($nameB) . '</a>';
        }

        return k2_fmt_dash();
    }

    if (k2_rated_game_is_a_win($game)) {
        return '<a href="/player/profile.php?id=' . $idA . '">' . k2_rated_game_h($nameA) . '</a>';
    }
    if (k2_rated_game_is_b_win($game)) {
        return '<a href="/player/profile.php?id=' . $idB . '">' . k2_rated_game_h($nameB) . '</a>';
    }

    return 'Draw';
}

/**
 * @param array<string, mixed> $game normalized or raw ratedresults row
 */
function k2_rated_game_es_winner_html(array $game): string
{
    $expectedA = (float) ($game['ExpectedScoreA'] ?? 0);
    $expectedB = (float) ($game['ExpectedScoreB'] ?? 0);

    return number_format(100 * max($expectedA, $expectedB), 1) . '%';
}

function k2_rated_game_favorite_expected_score(array $game): float
{
    return max((float) ($game['ExpectedScoreA'] ?? 0), (float) ($game['ExpectedScoreB'] ?? 0));
}

/**
 * @param array<string, mixed> $game normalized or raw ratedresults row
 */
function k2_rated_game_date_html(array $game, string $dateFormat = 'display'): string
{
    $date = (string) ($game['Date'] ?? '');
    if ($dateFormat === 'raw') {
        return k2_rated_game_h($date);
    }

    $ts = strtotime($date);
    $text = $ts !== false ? date('M d Y, H:i', $ts) : $date;

    return k2_rated_game_h($text);
}

/**
 * @param array<string, mixed> $game normalized or raw ratedresults row
 */
function k2_rated_game_id_html(array $game, string $idMode = 'link'): string
{
    $id = (int) ($game['id'] ?? 0);
    if ($idMode === 'plain') {
        return (string) $id;
    }

    return '<a href="/game.php?id=' . $id . '">' . $id . '</a>';
}

/**
 * Compact row for Games Highlights (no Elo / adjustment columns).
 *
 * @param array<string, mixed> $game normalized or raw ratedresults row
 * @param array{id_mode?: string, date_format?: string, show_peak_column?: bool, show_gd_column?: bool, show_sum_column?: bool} $options
 */
function k2_rated_game_row_compact_html(array $row, array $options = []): string
{
    $processed = k2_rated_game_is_processed($row);
    $game = k2_rated_game_normalize_row($row);
    $idMode = ($options['id_mode'] ?? 'link') === 'plain' ? 'plain' : 'link';
    $dateFormat = ($options['date_format'] ?? 'display') === 'raw' ? 'raw' : 'display';
    $showPeak = !empty($options['show_peak_column']);
    $showGd = ($options['show_gd_column'] ?? true) !== false;
    $showSum = ($options['show_sum_column'] ?? true) !== false;

    $idA = $game['idA'];
    $idB = $game['idB'];
    $nameA = (string) $game['NameA'];
    $nameB = (string) $game['NameB'];
    $peakGoals = max((int) $game['GoalsA'], (int) $game['GoalsB']);

    $gameId = (int) $game['id'];
    $idCell = k2_rated_game_id_html($game, $idMode);
    $dateCell = k2_rated_game_date_html($game, $dateFormat);
    $dateSortValue = strtotime($game['Date']) ?: 0;
    $teamA = '<a href="/player/profile.php?id=' . $idA . '">' . k2_rated_game_h($nameA) . '</a>';
    $teamB = '<a href="/player/profile.php?id=' . $idB . '">' . k2_rated_game_h($nameB) . '</a>';
    $winnerCell = k2_rated_game_winner_html($row);
    $goalDiff = $processed
        ? (int) $game['GoalDifference']
        : abs((int) $game['GoalsA'] - (int) $game['GoalsB']);
    $goalDiffCell = $goalDiff > 0 ? '+' . $goalDiff : (string) $goalDiff;
    $sumGoals = $processed
        ? (int) $game['SumOfGoals']
        : (int) $game['GoalsA'] + (int) $game['GoalsB'];

    $row = '<tr data-k2-sort-tie-value="' . $gameId . '">'
        . '<td></td>'
        . '<td class="k2-games-highlights-table__id" data-k2-sort-value="' . $gameId . '">' . $idCell . '</td>'
        . '<td class="k2-table-cell--left k2-table-cell--pad-left-xs" data-k2-sort-value="' . $dateSortValue . '">' . $dateCell . '</td>'
        . '<td class="k2-table-cell--left">' . $teamA . '</td>'
        . '<td>' . (int) $game['GoalsA'] . '</td>'
        . '<td class="k2-table-cell--left">' . (int) $game['GoalsB'] . '</td>'
        . '<td class="k2-table-cell--left">' . $teamB . '</td>';

    if ($showGd) {
        $row .= '<td data-k2-sort-value="' . $goalDiff . '">' . $goalDiffCell . '</td>';
    }

    if ($showSum) {
        $row .= '<td>' . $sumGoals . '</td>';
    }

    if ($showPeak) {
        $row .= '<td data-k2-sort-value="' . $peakGoals . '">' . $peakGoals . '</td>';
    }

    $row .= '<td class="k2-table-cell--left k2-table-cell--pad-left-lg">' . $winnerCell . '</td>';

    return $row . '</tr>';
}

/**
 * @param array<string, mixed> $game normalized or raw ratedresults row
 * @param array{id_mode?: string, date_format?: string, variant?: string, show_peak_column?: bool} $options
 */
function k2_rated_game_row_html(array $row, array $options = []): string
{
    if (($options['variant'] ?? '') === 'compact') {
        return k2_rated_game_row_compact_html($row, $options);
    }

    $processed = k2_rated_game_is_processed($row);
    $game = k2_rated_game_normalize_row($row);
    $idMode = ($options['id_mode'] ?? 'link') === 'plain' ? 'plain' : 'link';
    $dateFormat = ($options['date_format'] ?? 'display') === 'raw' ? 'raw' : 'display';

    $idA = $game['idA'];
    $idB = $game['idB'];
    $nameA = (string) $game['NameA'];
    $nameB = (string) $game['NameB'];

    $idCell = k2_rated_game_id_html($game, $idMode);
    $dateCell = k2_rated_game_date_html($game, $dateFormat);
    $dateSortValue = strtotime($game['Date']) ?: 0;
    $teamA = '<a href="/player/profile.php?id=' . $idA . '">' . k2_rated_game_h($nameA) . '</a>';
    $teamB = '<a href="/player/profile.php?id=' . $idB . '">' . k2_rated_game_h($nameB) . '</a>';
    $winnerCell = k2_rated_game_winner_html($row);
    $goalDiff = $processed
        ? (int) $game['GoalDifference']
        : abs((int) $game['GoalsA'] - (int) $game['GoalsB']);
    $sumGoals = $processed
        ? (int) $game['SumOfGoals']
        : (int) $game['GoalsA'] + (int) $game['GoalsB'];
    $dash = k2_fmt_dash();

    if ($processed) {
        $esCell = k2_rated_game_es_winner_html($game);
        $favoriteExpectedScore = k2_rated_game_favorite_expected_score($game);
        $winnerAdjustment = k2_game_rating_adjustment_pick($game, 'winner');
        $loserAdjustment = k2_game_rating_adjustment_pick($game, 'loser');
        $adjustmentCell = k2_game_rating_adjustment_html($game);
        $adjustmentLoserCell = k2_game_rating_adjustment_loser_html($game);
        $ratingACell = (string) (int) round((float) $game['RatingA']);
        $ratingBCell = (string) (int) round((float) $game['RatingB']);
        $ratingDiffCell = number_format(abs((float) $game['RatingDifference']), 0);
    } else {
        $esCell = $dash;
        $favoriteExpectedScore = -1.0;
        $winnerAdjustment = ['adj' => 0.0];
        $loserAdjustment = ['adj' => 0.0];
        $adjustmentCell = $dash;
        $adjustmentLoserCell = $dash;
        $ratingACell = $dash;
        $ratingBCell = $dash;
        $ratingDiffCell = $dash;
    }

    return '<tr>'
        . '<td>' . $idCell . '</td>'
        . '<td class="k2-table-cell--pad-left-xs k2-table-cell--pad-right-xl" data-k2-sort-value="' . $dateSortValue . '">' . $dateCell . '</td>'
        . '<td class="k2-table-cell--left">' . $teamA . '</td>'
        . '<td>' . (int) $game['GoalsA'] . '</td>'
        . '<td class="k2-table-cell--left">' . (int) $game['GoalsB'] . '</td>'
        . '<td class="k2-table-cell--left">' . $teamB . '</td>'
        . '<td>' . $goalDiff . '</td>'
        . '<td>' . $sumGoals . '</td>'
        . '<td class="k2-table-cell--left k2-table-cell--pad-left-lg">' . $winnerCell . '</td>'
        . '<td>' . $ratingACell . '</td>'
        . '<td>' . $ratingBCell . '</td>'
        . '<td>' . $ratingDiffCell . '</td>'
        . '<td class="k2-table-cell--pad-right-xs" data-k2-sort-value="' . $favoriteExpectedScore . '">' . $esCell . '</td>'
        . '<td class="k2-table-cell--left" data-k2-sort-value="' . (float) $winnerAdjustment['adj'] . '">' . $adjustmentCell . '</td>'
        . '<td class="k2-table-cell--left" data-k2-sort-value="' . (float) $loserAdjustment['adj'] . '">' . $adjustmentLoserCell . '</td>'
        . '</tr>';
}
