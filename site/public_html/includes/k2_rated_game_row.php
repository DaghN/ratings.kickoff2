<?php
/**
 * Shared rated-game table row (game.php, Games hub /games/*).
 *
 * @see docs/k2-table-and-games-plan.md Phase 2
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_game_rating_adjustment.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_display_names.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';

function k2_rated_game_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** One goals column — winning side blue + bold; draws stay plain. */
function k2_rated_game_goal_cell_html(int $goals, int $goalsOpponent): string
{
    return $goals > $goalsOpponent
        ? '<strong class="blue">' . $goals . '</strong>'
        : (string) $goals;
}

/** Inline scoreline for recency lists and fixture cells (A–B). */
function k2_rated_game_scoreline_html(int $goalsA, int $goalsB): string
{
    return k2_rated_game_goal_cell_html($goalsA, $goalsB)
        . '<span class="k2-scoreline-sep" aria-hidden="true">–</span>'
        . k2_rated_game_goal_cell_html($goalsB, $goalsA);
}

function k2_rated_game_player_link(int $id, string $name): string
{
    return k2_player_link($id, $name);
}

/** Games Highlights table — semantic column class (see .k2-games-highlights-col--* in theme.css). */
function k2_games_highlights_col_classes(string $col, string $extra = ''): string
{
    $classes = 'k2-games-highlights-col k2-games-highlights-col--' . $col;
    if ($extra !== '') {
        $classes .= ' ' . trim($extra);
    }

    return $classes;
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
            return k2_rated_game_player_link($idA, $nameA);
        }
        if (k2_rated_game_is_b_win_from_goals($game)) {
            return k2_rated_game_player_link($idB, $nameB);
        }

        return k2_fmt_dash();
    }

    if (k2_rated_game_is_a_win($game)) {
        return k2_rated_game_player_link($idA, $nameA);
    }
    if (k2_rated_game_is_b_win($game)) {
        return k2_rated_game_player_link($idB, $nameB);
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
 * Top score (TS) — most goals either player scored in the game.
 *
 * @param array<string, mixed> $game normalized or raw ratedresults row
 */
function k2_rated_game_top_score(array $game): int
{
    $normalized = isset($game['GoalsA']) ? $game : k2_rated_game_normalize_row($game);

    return max((int) $normalized['GoalsA'], (int) $normalized['GoalsB']);
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

    return '<a href="' . k2_rated_game_h(k2_game_page_url($id)) . '">' . $id . '</a>';
}

/**
 * 0-based column index for each server-side `sort` key on hub full game rows (Recent default / All games).
 *
 * Pass $withWinner = false for the scoreboard layout (All games / Recent / league period) where the
 * Winner column is dropped and every later column shifts left by one.
 *
 * @return array<string, int>
 */
function k2_rated_game_hub_sort_col_map(bool $withWinner = true): array
{
    $cols = ['id', 'date', 'team_a', 'goals_a', 'goals_b', 'team_b', 'gd', 'sum', 'top_score'];
    if ($withWinner) {
        $cols[] = 'winner';
    }
    array_push($cols, 'rating_a', 'rating_b', 'elo_diff', 'fav_es', 'adjustment');

    return array_flip($cols);
}

function k2_rated_game_sort_col_index(string $sortKey, bool $withWinner = true): int
{
    $map = k2_rated_game_hub_sort_col_map($withWinner);

    return $map[$sortKey] ?? 0;
}

function k2_rated_game_td(
    string $content,
    int $colIndex,
    int $sortedColIndex,
    string $extraClass = '',
    int|float|string|null $sortValue = null
): string {
    $classes = [];
    if ($extraClass !== '') {
        foreach (preg_split('/\s+/', trim($extraClass)) ?: [] as $className) {
            if ($className !== '') {
                $classes[] = $className;
            }
        }
    }
    if ($sortedColIndex >= 0 && $colIndex === $sortedColIndex) {
        $classes[] = 'k2-table-col-sorted';
    }

    $attrs = '';
    if ($classes !== []) {
        $attrs .= ' class="' . implode(' ', $classes) . '"';
    }
    if ($sortValue !== null) {
        $attrs .= ' data-k2-sort-value="' . k2_rated_game_h((string) $sortValue) . '"';
    }

    return '<td' . $attrs . '>' . $content . '</td>';
}

/**
 * Elo / adjustment cell strings shared by the full row and the Highlights compact row.
 *
 * @param array<string, mixed> $game normalized ratedresults row
 * @return array{rating_a: string, rating_b: string, rating_diff: string, es: string, fav_es: float, adjustment: string, adjustment_loser: string, adj_winner: float, adj_loser: float}
 */
function k2_rated_game_elo_cells(array $game, bool $processed): array
{
    if (!$processed) {
        $dash = k2_fmt_dash();

        return [
            'rating_a' => $dash,
            'rating_b' => $dash,
            'rating_diff' => $dash,
            'es' => $dash,
            'fav_es' => -1.0,
            'adjustment' => $dash,
            'adjustment_loser' => $dash,
            'adj_winner' => 0.0,
            'adj_loser' => 0.0,
        ];
    }

    $winnerAdjustment = k2_game_rating_adjustment_pick($game, 'winner');
    $loserAdjustment = k2_game_rating_adjustment_pick($game, 'loser');

    return [
        'rating_a' => (string) (int) round((float) $game['RatingA']),
        'rating_b' => (string) (int) round((float) $game['RatingB']),
        'rating_diff' => number_format(abs((float) $game['RatingDifference']), 0),
        'es' => k2_rated_game_es_winner_html($game),
        'fav_es' => k2_rated_game_favorite_expected_score($game),
        'adjustment' => k2_game_rating_adjustment_html($game),
        'adjustment_loser' => k2_game_rating_adjustment_loser_html($game),
        'adj_winner' => (float) $winnerAdjustment['adj'],
        'adj_loser' => (float) $loserAdjustment['adj'],
    ];
}

/**
 * Compact row for Games Highlights (now full column parity with the hub tables, plus a rank column).
 *
 * @param array<string, mixed> $game normalized or raw ratedresults row
 * @param array{id_mode?: string, date_format?: string, show_ts_column?: bool, show_gd_column?: bool, show_sum_column?: bool, show_winner?: bool, highlight_winner_goal?: bool, team_a_align?: string} $options
 */
function k2_rated_game_row_compact_html(array $row, array $options = []): string
{
	$processed = k2_rated_game_is_processed($row);
	$game = k2_rated_game_normalize_row($row);
	$idMode = ($options['id_mode'] ?? 'link') === 'plain' ? 'plain' : 'link';
	$dateFormat = ($options['date_format'] ?? 'display') === 'raw' ? 'raw' : 'display';
	$showTs = !empty($options['show_ts_column']);
	$showGd = ($options['show_gd_column'] ?? true) !== false;
	$showSum = ($options['show_sum_column'] ?? true) !== false;
	// Scoreboard layout (shared with the full row): no Winner column, Team A right-aligned,
	// goals hugging the centre with the winning goal blue + bold.
	$showWinner = ($options['show_winner'] ?? true) !== false;
	$highlightWinnerGoal = !empty($options['highlight_winner_goal']);
	$teamAClass = ($options['team_a_align'] ?? 'left') === 'right' ? 'k2-table-cell--right' : 'k2-table-cell--left';

	$idA = $game['idA'];
	$idB = $game['idB'];
	$nameA = (string) $game['NameA'];
	$nameB = (string) $game['NameB'];
	$topScore = k2_rated_game_top_score($game);

    $gameId = (int) $game['id'];
    $idCell = k2_rated_game_id_html($game, $idMode);
    $dateCell = k2_rated_game_date_html($game, $dateFormat);
    $dateSortValue = strtotime($game['Date']) ?: 0;
    $teamA = k2_rated_game_player_link($idA, $nameA);
    $teamB = k2_rated_game_player_link($idB, $nameB);
    $winnerCell = k2_rated_game_winner_html($row);
    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    $goalsACell = $highlightWinnerGoal && $goalsA > $goalsB
        ? '<strong class="blue">' . $goalsA . '</strong>'
        : (string) $goalsA;
    $goalsBCell = $highlightWinnerGoal && $goalsB > $goalsA
        ? '<strong class="blue">' . $goalsB . '</strong>'
        : (string) $goalsB;
    $goalDiff = $processed
        ? (int) $game['GoalDifference']
        : abs((int) $game['GoalsA'] - (int) $game['GoalsB']);
    $goalDiffCell = $goalDiff > 0 ? '+' . $goalDiff : (string) $goalDiff;
    $sumGoals = $processed
        ? (int) $game['SumOfGoals']
        : (int) $game['GoalsA'] + (int) $game['GoalsB'];

    $row = '<tr data-k2-sort-tie-value="' . $gameId . '">'
        . '<td class="' . k2_games_highlights_col_classes('rank') . '"></td>'
        . '<td class="' . k2_games_highlights_col_classes('id', 'k2-games-highlights-table__id') . '" data-k2-sort-value="' . $gameId . '">' . $idCell . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('date', 'k2-table-cell--left k2-table-cell--pad-left-xs') . '" data-k2-sort-value="' . $dateSortValue . '">' . $dateCell . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('team-a', $teamAClass) . '">' . $teamA . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('goals-a') . '" data-k2-sort-value="' . $goalsA . '">' . $goalsACell . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('goals-b', 'k2-table-cell--left') . '" data-k2-sort-value="' . $goalsB . '">' . $goalsBCell . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('team-b', 'k2-table-cell--left') . '">' . $teamB . '</td>';

    if ($showGd) {
        $row .= '<td class="' . k2_games_highlights_col_classes('gd') . '" data-k2-sort-value="' . $goalDiff . '">' . $goalDiffCell . '</td>';
    }

    if ($showSum) {
        $row .= '<td class="' . k2_games_highlights_col_classes('sum') . '">' . $sumGoals . '</td>';
    }

	if ($showTs) {
		$row .= '<td class="' . k2_games_highlights_col_classes('ts') . '" data-k2-sort-value="' . $topScore . '">' . $topScore . '</td>';
	}

    $elo = k2_rated_game_elo_cells($game, $processed);
    $row .= '<td class="' . k2_games_highlights_col_classes('rating-a') . '">' . $elo['rating_a'] . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('rating-b') . '">' . $elo['rating_b'] . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('elo-diff') . '">' . $elo['rating_diff'] . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('fav-es', 'k2-table-cell--pad-right-xs') . '" data-k2-sort-value="' . $elo['fav_es'] . '">' . $elo['es'] . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('adjustment', 'k2-table-cell--left') . '" data-k2-sort-value="' . $elo['adj_winner'] . '">' . $elo['adjustment'] . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('adjustment-lost', 'k2-table-cell--left') . '" data-k2-sort-value="' . $elo['adj_loser'] . '">' . $elo['adjustment_loser'] . '</td>';

    if ($showWinner) {
        $row .= '<td class="' . k2_games_highlights_col_classes('winner', 'k2-table-cell--left k2-table-cell--pad-left-lg') . '">' . $winnerCell . '</td>';
    }

    return $row . '</tr>';
}

/**
 * @param array<string, mixed> $game normalized or raw ratedresults row
 * @param array{id_mode?: string, date_format?: string, variant?: string, show_ts_column?: bool, sorted_col_index?: int, show_winner?: bool, highlight_winner_goal?: bool, team_a_align?: string} $options
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
    $sortedColIndex = (int) ($options['sorted_col_index'] ?? -1);
    // Scoreboard layout (All games / Recent / league period): no Winner column, Team A right-aligned,
    // winning goal shown blue + bold. game.php keeps the classic layout (defaults below).
    $showWinner = ($options['show_winner'] ?? true) !== false;
    $highlightWinnerGoal = !empty($options['highlight_winner_goal']);
    $teamAClass = ($options['team_a_align'] ?? 'left') === 'right' ? 'k2-table-cell--right' : 'k2-table-cell--left';

    $idA = $game['idA'];
    $idB = $game['idB'];
    $nameA = (string) $game['NameA'];
    $nameB = (string) $game['NameB'];

    $idCell = k2_rated_game_id_html($game, $idMode);
    $dateCell = k2_rated_game_date_html($game, $dateFormat);
    $dateSortValue = strtotime($game['Date']) ?: 0;
    $teamA = k2_rated_game_player_link($idA, $nameA);
    $teamB = k2_rated_game_player_link($idB, $nameB);
    $winnerCell = k2_rated_game_winner_html($row);
    $goalDiff = $processed
        ? (int) $game['GoalDifference']
        : abs((int) $game['GoalsA'] - (int) $game['GoalsB']);
    $goalDiffCell = $goalDiff > 0 ? '+' . $goalDiff : (string) $goalDiff;
    $sumGoals = $processed
        ? (int) $game['SumOfGoals']
        : (int) $game['GoalsA'] + (int) $game['GoalsB'];
    $topScore = k2_rated_game_top_score($game);

    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    $goalsACell = $highlightWinnerGoal && $goalsA > $goalsB
        ? '<strong class="blue">' . $goalsA . '</strong>'
        : (string) $goalsA;
    $goalsBCell = $highlightWinnerGoal && $goalsB > $goalsA
        ? '<strong class="blue">' . $goalsB . '</strong>'
        : (string) $goalsB;

    $gameId = (int) $game['id'];

    $elo = k2_rated_game_elo_cells($game, $processed);

    $col = 0;
    $cells = '';
    $emit = static function (
        string $content,
        string $extraClass = '',
        int|float|string|null $sortValue = null
    ) use (&$col, &$cells, $sortedColIndex): void {
        $cells .= k2_rated_game_td($content, $col, $sortedColIndex, $extraClass, $sortValue);
        $col++;
    };

    $emit($idCell);
    $emit($dateCell, 'k2-table-cell--pad-left-xs k2-table-cell--pad-right-xl', $dateSortValue);
    $emit($teamA, $teamAClass);
    $emit($goalsACell);
    $emit($goalsBCell, 'k2-table-cell--left');
    $emit($teamB, 'k2-table-cell--left');
    $emit($goalDiffCell, '', $goalDiff);
    $emit((string) $sumGoals);
    $emit((string) $topScore, '', $topScore);
    if ($showWinner) {
        $emit($winnerCell, 'k2-table-cell--left k2-table-cell--pad-left-lg');
    }
    $emit($elo['rating_a']);
    $emit($elo['rating_b']);
    $emit($elo['rating_diff']);
    $emit($elo['es'], 'k2-table-cell--pad-right-xs', $elo['fav_es']);
    $emit($elo['adjustment'], 'k2-table-cell--left', $elo['adj_winner']);
    $emit($elo['adjustment_loser'], 'k2-table-cell--left', $elo['adj_loser']);

    return '<tr data-k2-sort-tie-value="' . $gameId . '">' . $cells . '</tr>';
}
