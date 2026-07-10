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

    return '<a href="' . k2_h(k2_amiga_game_page_url($gameId)) . '">' . $gameId . '</a>';
}

/**
 * @param array<string, mixed> $game normalized ratedresults row
 */
function amiga_rated_game_player_side_cell(int $playerId, string $name, string $country, string $side = 'a'): string
{
    if ($playerId < 1 || $name === '') {
        return k2_fmt_dash();
    }

    require_once __DIR__ . '/k2_amiga_country_flag.php';
    $flag = trim($country) !== '' ? k2_amiga_country_flag_link($country) : '';
    $link = k2_amiga_player_link($playerId, $name);
    if ($side === 'b') {
        return '<span class="k2-amiga-tgame-side k2-amiga-tgame-side--b">' . $link . $flag . '</span>';
    }

    return '<span class="k2-amiga-tgame-side k2-amiga-tgame-side--a">' . $flag . $link . '</span>';
}

/**
 * @param array<string, mixed> $row ratedresults-shaped Amiga row
 */
function amiga_rated_game_tournament_cell(array $row): string
{
    require_once __DIR__ . '/k2_amiga_country_flag.php';
    $tournamentId = (int) ($row['tournament_id'] ?? 0);
    $tournamentName = trim((string) ($row['tournament_name'] ?? ''));
    $hostCountry = trim((string) ($row['tournament_country'] ?? ''));
    if ($tournamentName !== '' && $tournamentId > 0) {
        $hostFlag = k2_amiga_country_flag_link($hostCountry);

        return '<span class="k2-amiga-tgame-side k2-amiga-tgame-side--tournament">' . $hostFlag . amiga_tournament_link($tournamentId, $tournamentName) . '</span>';
    }

    return $tournamentName !== '' ? k2_h($tournamentName) : k2_fmt_dash();
}

/**
 * @param array<string, mixed> $row ratedresults-shaped Amiga row
 */
function amiga_rated_game_phase_cell(array $row, ?mysqli $con = null): string
{
    $display = trim((string) ($row['phase'] ?? ''));
    if ($display === '') {
        return k2_fmt_dash();
    }
    $tournamentId = (int) ($row['tournament_id'] ?? 0);
    $stageId = (int) ($row['stage_id'] ?? 0);
    $witness = array_key_exists('phase_witness', $row)
        ? trim((string) ($row['phase_witness'] ?? ''))
        : $display;
    $game = k2_player_game_normalize_row($row);
    if ($con !== null && $tournamentId > 0) {
        $scope = amiga_tournament_resolve_game_phase_scope(
            $con,
            $tournamentId,
            $stageId,
            $witness,
            (int) $game['idA'],
            (int) $game['idB'],
        );
        if ($scope !== null) {
            $href = amiga_tournament_url($tournamentId, $scope['scope_type'], $scope['scope_key']);
            $help = $scope['scope_type'] === 'knockout' ? 'Elimination tie' : 'Phase standings';

            return '<a href="' . k2_h($href) . '" data-k2-help="' . k2_h($help) . '" data-k2-tooltip-hide-title="1">'
                . k2_h($display) . '</a>';
        }
    }

    return k2_h($display);
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
        . '<td class="k2-table-cell--left k2-amiga-tgame-team">' . amiga_rated_game_tournament_cell($row) . '</td>'
        . '<td class="k2-table-cell--left">' . amiga_rated_game_phase_cell($row, $con) . '</td>'
        . '<td class="k2-table-cell--left k2-amiga-tgame-team k2-amiga-tgame-team--a">' . amiga_rated_game_player_side_cell((int) $game['idA'], (string) $game['NameA'], trim((string) ($row['country_a'] ?? '')), 'a') . '</td>'
        . '<td>' . $goalsACell . '</td>'
        . '<td class="k2-table-cell--left">' . $goalsBCell . '</td>'
        . '<td class="k2-table-cell--left k2-amiga-tgame-team k2-amiga-tgame-team--b">' . amiga_rated_game_player_side_cell((int) $game['idB'], (string) $game['NameB'], trim((string) ($row['country_b'] ?? '')), 'b') . '</td>'
        . '<td>' . $goalDiff . '</td>'
        . '<td>' . $sumGoals . '</td>'
        . '<td>' . $ratingACell . '</td>'
        . '<td>' . $ratingBCell . '</td>'
        . '<td>' . $ratingDiffCell . '</td>'
        . '<td class="k2-table-cell--pad-right-xs" data-k2-sort-value="' . $favoriteExpectedScore . '">' . $esCell . '</td>'
        . '<td class="k2-table-cell--left" data-k2-sort-value="' . (float) $winnerAdjustment['adj'] . '">' . $adjustmentCell . '</td>'
        . '<td class="k2-table-cell--left" data-k2-sort-value="' . (float) $loserAdjustment['adj'] . '">' . $adjustmentLoserCell . '</td>'
        . '</tr>';
}

/**
 * Compact row for Amiga Games Highlights (parity with online k2-games-highlights-table).
 *
 * @param array<string, mixed> $row ratedresults-shaped Amiga row
 * @param array{id_mode?: string, show_ts_column?: bool, show_gd_column?: bool, show_sum_column?: bool, highlight_winner_goal?: bool, team_a_align?: string} $options
 */
function amiga_rated_game_highlights_row_html(array $row, array $options = []): string
{
    $processed = k2_rated_game_is_processed($row);
    $game = k2_player_game_normalize_row($row);
    $idMode = ($options['id_mode'] ?? 'link') === 'plain' ? 'plain' : 'link';
    $showTs = !empty($options['show_ts_column']);
    $showGd = ($options['show_gd_column'] ?? true) !== false;
    $showSum = ($options['show_sum_column'] ?? true) !== false;
    $highlightWinnerGoal = !empty($options['highlight_winner_goal']);
    $teamAClass = ($options['team_a_align'] ?? 'left') === 'right' ? 'k2-table-cell--right' : 'k2-table-cell--left';

    $countryA = trim((string) ($row['country_a'] ?? ''));
    $countryB = trim((string) ($row['country_b'] ?? ''));
    $gameId = (int) $game['id'];
    $topScore = k2_rated_game_top_score($game);
    $dateSortValue = strtotime((string) $game['Date']) ?: 0;

    $teamA = amiga_rated_game_player_side_cell((int) $game['idA'], (string) $game['NameA'], $countryA, 'a');
    $teamB = amiga_rated_game_player_side_cell((int) $game['idB'], (string) $game['NameB'], $countryB, 'b');

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
        : abs($goalsA - $goalsB);
    $goalDiffCell = $goalDiff > 0 ? '+' . $goalDiff : (string) $goalDiff;
    $sumGoals = $processed
        ? (int) $game['SumOfGoals']
        : $goalsA + $goalsB;

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
        $dash = k2_fmt_dash();
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

    $html = '<tr data-k2-sort-tie-value="' . $gameId . '">'
        . '<td class="' . k2_games_highlights_col_classes('rank') . '"></td>'
        . '<td class="' . k2_games_highlights_col_classes('id', 'k2-games-highlights-table__id') . '" data-k2-sort-value="' . $gameId . '">' . amiga_rated_game_id_html($gameId, $idMode) . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('date', 'k2-table-cell--left k2-table-cell--pad-left-xs k2-amiga-player-games-date') . '" data-k2-sort-value="' . $dateSortValue . '">' . amiga_player_game_date_html((string) $game['Date']) . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('tournament', 'k2-table-cell--left k2-amiga-tgame-team') . '">' . amiga_rated_game_tournament_cell($row) . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('team-a', $teamAClass) . '">' . $teamA . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('goals-a') . '" data-k2-sort-value="' . $goalsA . '">' . $goalsACell . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('goals-b', 'k2-table-cell--left') . '" data-k2-sort-value="' . $goalsB . '">' . $goalsBCell . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('team-b', 'k2-table-cell--left') . '">' . $teamB . '</td>';

    if ($showGd) {
        $html .= '<td class="' . k2_games_highlights_col_classes('gd', 'k2-table-cell--pad-left-md') . '" data-k2-sort-value="' . $goalDiff . '">' . $goalDiffCell . '</td>';
    }

    if ($showSum) {
        $html .= '<td class="' . k2_games_highlights_col_classes('sum') . '">' . $sumGoals . '</td>';
    }

    if ($showTs) {
        $html .= '<td class="' . k2_games_highlights_col_classes('ts') . '" data-k2-sort-value="' . $topScore . '">' . $topScore . '</td>';
    }

    $html .= '<td class="' . k2_games_highlights_col_classes('rating-a', 'k2-table-cell--pad-left-md') . '">' . $ratingACell . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('rating-b') . '">' . $ratingBCell . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('elo-diff') . '">' . $ratingDiffCell . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('fav-es', 'k2-table-cell--pad-right-xs') . '" data-k2-sort-value="' . $favoriteExpectedScore . '">' . $esCell . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('adjustment', 'k2-table-cell--left') . '" data-k2-sort-value="' . (float) $winnerAdjustment['adj'] . '">' . $adjustmentCell . '</td>'
        . '<td class="' . k2_games_highlights_col_classes('adjustment-lost', 'k2-table-cell--left') . '" data-k2-sort-value="' . (float) $loserAdjustment['adj'] . '">' . $adjustmentLoserCell . '</td>'
        . '</tr>';

    return $html;
}
