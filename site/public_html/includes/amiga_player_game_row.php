<?php
/**
 * Amiga player-perspective rated-game table row (`amiga/player/games.php`).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_player_game_row.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_rated_game_row.php';

/** 0-based column index for each server-side `sort` query key on `amiga/player/games.php`. */
function amiga_player_game_sort_col_index(string $sortKey): int
{
    $map = [
        'id' => 0,
        'date' => 1,
        'team_a' => 2,
        'team_b' => 5,
        'tournament' => 6,
        'phase' => 7,
        'result' => 8,
        'opponent' => 9,
        'goals_for' => 10,
        'against' => 11,
        'diff' => 12,
        'sum' => 13,
        'player_rating' => 14,
        'opponent_rating' => 15,
        'es' => 16,
        'adjustment' => 17,
    ];

    return $map[$sortKey] ?? 0;
}

/** Event day only — Amiga `game_date` is synthetic; time within the day is not shown. */
function amiga_player_game_date_html(string $date): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date, new DateTimeZone('UTC'));
    if ($dt === false) {
        $ts = strtotime($date);
        if ($ts === false) {
            return k2_h($date);
        }
        $dt = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('UTC'));
    }

    return k2_h($dt->format('M j Y'));
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
    ?mysqli $con = null
): string
{
    $processed = k2_rated_game_is_processed($row);
    $game = k2_player_game_normalize_row($row);
    $tournamentId = (int) ($row['tournament_id'] ?? 0);
    $tournamentName = trim((string) ($row['tournament_name'] ?? ''));
    $phase = trim((string) ($row['phase'] ?? ''));
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
    if ($tournamentName !== '' && $tournamentId > 0) {
        $tournamentCell = amiga_tournament_link($tournamentId, $tournamentName);
    } else {
        $tournamentCell = $tournamentName !== '' ? k2_h($tournamentName) : $dash;
    }
    if ($phase !== '' && $tournamentId > 0 && $con !== null) {
        $phaseCell = amiga_tournament_phase_link(
            $con,
            $tournamentId,
            $phase,
            (int) $game['idA'],
            (int) $game['idB']
        );
    } else {
        $phaseCell = $phase !== '' ? k2_h($phase) : $dash;
    }

    $anchorCol = AMIGA_PLAYER_GAMES_ANCHOR_COL;

    return '<tr>'
        . k2_player_game_td(amiga_rated_game_id_html((int) $game['id']), 0, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td(amiga_player_game_date_html($game['Date']), 1, $sortedColIndex, 'k2-table-cell--left k2-table-cell--pad-left-xs k2-amiga-player-games-date', $anchorCol)
        . k2_player_game_td(k2_amiga_player_link($game['idA'], $game['NameA']), 2, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td((string) $game['GoalsA'], 3, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td((string) $game['GoalsB'], 4, $sortedColIndex, 'k2-table-cell--left', $anchorCol)
        . k2_player_game_td(k2_amiga_player_link($game['idB'], $game['NameB']), 5, $sortedColIndex, 'k2-table-cell--left', $anchorCol)
        . k2_player_game_td($tournamentCell, 6, $sortedColIndex, 'k2-table-cell--left', $anchorCol)
        . k2_player_game_td($phaseCell, 7, $sortedColIndex, 'k2-table-cell--left', $anchorCol)
        . k2_player_game_td($resultCell, 8, $sortedColIndex, 'k2-table-cell--left k2-table-cell--pad-left-xl', $anchorCol)
        . k2_player_game_td(k2_amiga_player_link($opponentId, $opponentName), 9, $sortedColIndex, 'k2-table-cell--left', $anchorCol)
        . k2_player_game_td((string) $goalsFor, 10, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td((string) $goalsAgainst, 11, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($diffCell, 12, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($sumCell, 13, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($playerRatingCell, 14, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($opponentRatingCell, 15, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($esCell, 16, $sortedColIndex, '', $anchorCol)
        . k2_player_game_td($adjustmentCell, 17, $sortedColIndex, '', $anchorCol)
        . '</tr>';
}
