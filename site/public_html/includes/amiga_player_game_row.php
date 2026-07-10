<?php
/**
 * Amiga player-perspective rated-game table row (`amiga/player/games.php`).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_player_game_row.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_rated_game_row.php';

/** 0-based column index for each server-side `sort` query key on `amiga/player/games.php`. */
function amiga_player_game_sort_col_index(string $sortKey): int
{
    $map = [
        'id' => 0,
        'date' => 1,
        'tournament' => 2,
        'phase' => 3,
        'team_a' => 4,
        'team_b' => 7,
        'goals_for' => 8,
        'against' => 9,
        'diff' => 10,
        'sum' => 11,
        'rating_a' => 12,
        'rating_b' => 13,
        'es' => 14,
        'result' => 15,
        'adjustment' => 16,
    ];

    return $map[$sortKey] ?? 0;
}

/** ID column — game link anchor for calm-stats emphasis on first paint. */
const AMIGA_PLAYER_GAMES_ANCHOR_COL = 0;

/**
 * @param array<string, mixed> $row ratedresults row (+ optional tournament_name)
 */
function amiga_player_game_row_html(
    array $row,
    int $playerId,
    int $sortedColIndex = 0,
    ?mysqli $con = null,
): string
{
    $processed = k2_rated_game_is_processed($row);
    $game = k2_player_game_normalize_row($row);
    $tournamentId = (int) ($row['tournament_id'] ?? 0);
    $tournamentName = trim((string) ($row['tournament_name'] ?? ''));
    $isPlayerA = $game['idA'] === $playerId;
    $goalsFor = $isPlayerA ? $game['GoalsA'] : $game['GoalsB'];
    $goalsAgainst = $isPlayerA ? $game['GoalsB'] : $game['GoalsA'];
    $sumGoals = (int) $game['GoalsA'] + (int) $game['GoalsB'];
    $goalDiff = abs((int) $game['GoalsA'] - (int) $game['GoalsB']);

    $countryA = trim((string) ($row['country_a'] ?? ''));
    $countryB = trim((string) ($row['country_b'] ?? ''));

    if ($processed) {
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
        $expectedScore = 0.0;
        $adjustment = 0.0;
        $sumCell = (string) $sumGoals;
        $diffCell = k2_player_game_diff_html($goalDiff, $isWin, $isDraw);
    }

    $resultCell = k2_player_game_result_html($isWin, $isDraw);
    $dash = k2_fmt_dash();
    $ratingACell = $processed ? (string) (int) round((float) $game['RatingA']) : $dash;
    $ratingBCell = $processed ? (string) (int) round((float) $game['RatingB']) : $dash;
    $esCell = $processed ? number_format(100 * $expectedScore, 1) . '%' : $dash;
    $adjustmentCell = $processed ? k2_player_game_signed_number_html($adjustment) : $dash;
    if ($tournamentName !== '' && $tournamentId > 0) {
        $tournamentCell = amiga_rated_game_tournament_cell($row);
    } else {
        $tournamentCell = $tournamentName !== '' ? k2_h($tournamentName) : $dash;
    }
    $phaseCell = amiga_rated_game_phase_cell($row, $con);

    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    $goalsACell = $goalsA > $goalsB
        ? '<strong class="blue">' . $goalsA . '</strong>'
        : (string) $goalsA;
    $goalsBCell = $goalsB > $goalsA
        ? '<strong class="blue">' . $goalsB . '</strong>'
        : (string) $goalsB;

    $anchorCol = AMIGA_PLAYER_GAMES_ANCHOR_COL;

    return '<tr>'
        . k2_player_game_td(amiga_rated_game_id_html((int) $game['id']), 0, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td(amiga_player_game_date_html($game['Date']), 1, $sortedColIndex, 'k2-table-cell--left k2-table-cell--pad-left-xs k2-amiga-player-games-date', $anchorCol)
        . k2_player_game_td($tournamentCell, 2, $sortedColIndex, 'k2-table-cell--left k2-amiga-tgame-team', $anchorCol)
        . k2_player_game_td($phaseCell, 3, $sortedColIndex, 'k2-table-cell--left', $anchorCol)
        . k2_player_game_td(amiga_rated_game_player_side_cell((int) $game['idA'], (string) $game['NameA'], $countryA, 'a'), 4, $sortedColIndex, 'k2-amiga-tgame-team k2-amiga-tgame-team--a', $anchorCol)
        . k2_player_game_td($goalsACell, 5, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($goalsBCell, 6, $sortedColIndex, 'k2-table-cell--left', $anchorCol)
        . k2_player_game_td(amiga_rated_game_player_side_cell((int) $game['idB'], (string) $game['NameB'], $countryB, 'b'), 7, $sortedColIndex, 'k2-table-cell--left k2-amiga-tgame-team k2-amiga-tgame-team--b', $anchorCol)
        . k2_player_game_td((string) $goalsFor, 8, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td((string) $goalsAgainst, 9, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($diffCell, 10, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($sumCell, 11, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($ratingACell, 12, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($ratingBCell, 13, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($esCell, 14, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($resultCell, 15, $sortedColIndex, 'k2-table-cell--left', $anchorCol)
        . k2_player_game_td($adjustmentCell, 16, $sortedColIndex, '', $anchorCol)
        . '</tr>';
}
