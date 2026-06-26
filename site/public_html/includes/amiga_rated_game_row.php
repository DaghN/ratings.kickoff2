<?php
/**
 * Neutral Amiga rated-game table row (`amiga/game.php`, list ID links).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_player_game_row.php';
require_once __DIR__ . '/k2_rated_game_row.php';
require_once __DIR__ . '/k2_game_rating_adjustment.php';
require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_tournament_lib.php';

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

function amiga_rated_game_id_html(int $gameId, string $idMode = 'link'): string
{
    if ($idMode === 'plain' || $gameId < 1) {
        return (string) $gameId;
    }

    return '<a href="' . k2_h(k2_amiga_route('amiga-game', ['id' => $gameId])) . '">' . $gameId . '</a>';
}

/**
 * @param array<string, mixed> $row ratedresults-shaped Amiga row
 */
function amiga_rated_game_winner_html(array $row): string
{
    $game = k2_player_game_normalize_row($row);

    if (!k2_rated_game_is_processed($row)) {
        if (k2_rated_game_is_a_win_from_goals($game)) {
            return k2_amiga_player_link((int) $game['idA'], (string) $game['NameA']);
        }
        if (k2_rated_game_is_b_win_from_goals($game)) {
            return k2_amiga_player_link((int) $game['idB'], (string) $game['NameB']);
        }

        return k2_fmt_dash();
    }

    if (k2_rated_game_is_a_win($game)) {
        return k2_amiga_player_link((int) $game['idA'], (string) $game['NameA']);
    }
    if (k2_rated_game_is_b_win($game)) {
        return k2_amiga_player_link((int) $game['idB'], (string) $game['NameB']);
    }

    return 'Draw';
}

/**
 * @param array<string, mixed> $game normalized ratedresults row
 */
function amiga_rated_game_tournament_cell(array $row): string
{
    $tournamentId = (int) ($row['tournament_id'] ?? 0);
    $tournamentName = trim((string) ($row['tournament_name'] ?? ''));
    if ($tournamentName !== '' && $tournamentId > 0) {
        return amiga_tournament_link($tournamentId, $tournamentName);
    }

    return $tournamentName !== '' ? k2_h($tournamentName) : k2_fmt_dash();
}

/**
 * @param array<string, mixed> $row ratedresults-shaped Amiga row
 */
function amiga_rated_game_phase_cell(array $row, ?mysqli $con = null): string
{
    $tournamentId = (int) ($row['tournament_id'] ?? 0);
    $phase = trim((string) ($row['phase'] ?? ''));
    $game = k2_player_game_normalize_row($row);
    if ($phase !== '' && $tournamentId > 0 && $con !== null) {
        return amiga_tournament_phase_link(
            $con,
            $tournamentId,
            $phase,
            (int) $game['idA'],
            (int) $game['idB']
        );
    }

    return $phase !== '' ? k2_h($phase) : k2_fmt_dash();
}

/**
 * @param array<string, mixed> $game normalized ratedresults row
 */
function amiga_rated_game_adjustment_html(array $game, string $side): string
{
    $picked = k2_game_rating_adjustment_pick($game, $side === 'winner' ? 'winner' : 'loser');
    $tone = $side === 'winner' ? 'blue' : 'red';

    return k2_amiga_player_link($picked['id'], $picked['name'])
        . ' '
        . k2_game_rating_adjustment_span_html($picked['adj'], $tone);
}

/**
 * @param array<string, mixed> $row ratedresults-shaped Amiga row
 * @param array{id_mode?: string} $options
 */
function amiga_rated_game_row_html(array $row, array $options = [], ?mysqli $con = null): string
{
    $processed = k2_rated_game_is_processed($row);
    $game = k2_player_game_normalize_row($row);
    $idMode = ($options['id_mode'] ?? 'link') === 'plain' ? 'plain' : 'link';
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
        $adjustmentCell = amiga_rated_game_adjustment_html($game, 'winner');
        $adjustmentLoserCell = amiga_rated_game_adjustment_html($game, 'loser');
        $ratingACell = (string) (int) round((float) $game['RatingA']);
        $ratingBCell = (string) (int) round((float) $game['RatingB']);
        $ratingDiffCell = number_format(abs((float) ($row['RatingDifference'] ?? 0)), 0);
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

    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    $goalsACell = k2_rated_game_goal_cell_html($goalsA, $goalsB);
    $goalsBCell = k2_rated_game_goal_cell_html($goalsB, $goalsA);

    return '<tr>'
        . '<td>' . amiga_rated_game_id_html((int) $game['id'], $idMode) . '</td>'
        . '<td class="k2-table-cell--left k2-table-cell--pad-left-xs">' . amiga_player_game_date_html($game['Date']) . '</td>'
        . '<td class="k2-table-cell--left">' . k2_amiga_player_link((int) $game['idA'], (string) $game['NameA']) . '</td>'
        . '<td>' . $goalsACell . '</td>'
        . '<td class="k2-table-cell--left">' . $goalsBCell . '</td>'
        . '<td class="k2-table-cell--left">' . k2_amiga_player_link((int) $game['idB'], (string) $game['NameB']) . '</td>'
        . '<td class="k2-table-cell--left">' . amiga_rated_game_tournament_cell($row) . '</td>'
        . '<td class="k2-table-cell--left">' . amiga_rated_game_phase_cell($row, $con) . '</td>'
        . '<td>' . $goalDiff . '</td>'
        . '<td>' . $sumGoals . '</td>'
        . '<td class="k2-table-cell--left k2-table-cell--pad-left-lg">' . amiga_rated_game_winner_html($row) . '</td>'
        . '<td>' . $ratingACell . '</td>'
        . '<td>' . $ratingBCell . '</td>'
        . '<td>' . $ratingDiffCell . '</td>'
        . '<td class="k2-table-cell--pad-right-xs" data-k2-sort-value="' . $favoriteExpectedScore . '">' . $esCell . '</td>'
        . '<td class="k2-table-cell--left" data-k2-sort-value="' . (float) $winnerAdjustment['adj'] . '">' . $adjustmentCell . '</td>'
        . '<td class="k2-table-cell--left" data-k2-sort-value="' . (float) $loserAdjustment['adj'] . '">' . $adjustmentLoserCell . '</td>'
        . '</tr>';
}
