<?php
/**
 * Player Opponents wing — per-opponent table bodies.
 * W/D/L, Goals, and DDs read player_matchup_summary when present (SCH-019 extension for tail columns).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/lb_column_help.php';
require_once __DIR__ . '/player_opponents_load.php';
require_once __DIR__ . '/player_opponents_lib.php';
require_once __DIR__ . '/k2_player_display_names.php';

function player_opponents_dds_ratio_cell(float $ratio): string
{
    return $ratio == 0.0 ? '0%' : number_format(100 * $ratio, 1) . '%';
}

/**
 * @param list<array<string, mixed>> $rows
 */
function player_opponents_render_wdl_table_from_rows(array $rows, int $playerId): void
{
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--player-matchup ranked-pages-table k2-table--opponent-matchup" data-k2-table="sortable" data-k2-anchor-col="1" data-k2-default-sort="1" data-k2-default-direction="desc">
<thead>
    <tr>
        <th class="k2-table-cell--left" data-k2-sort="text">Opponent</th>
        <th data-k2-sort="number" data-k2-help="Rated games against this opponent.">Games</th>
        <th data-k2-sort="number" data-k2-help="Wins against this opponent.">Wins</th>
        <th data-k2-sort="number" data-k2-help="Draws against this opponent.">Draws</th>
        <th data-k2-sort="number" data-k2-help="Losses against this opponent.">Losses</th>
        <th data-k2-sort="number" data-k2-help="Share of games won against this opponent.">Win Ratio</th>
        <th data-k2-sort="number" data-k2-help="Share of games drawn against this opponent.">Draw Ratio</th>
        <th data-k2-sort="number" data-k2-help="Share of games lost against this opponent.">Loss Ratio</th>
    </tr>
</thead>
<tbody>
	<?php foreach ($rows as $row) {
	    $opponentId = (int) $row['opponent_id'];
	    $opponentName = (string) $row['opponent_name'];
	    $games = (int) $row['games'];
	    $wins = (int) $row['wins'];
	    $draws = (int) $row['draws'];
	    $losses = (int) $row['losses'];
	    $winRatio = player_opponents_matchup_ratio($wins, $games);
	    $drawRatio = player_opponents_matchup_ratio($draws, $games);
	    $lossRatio = player_opponents_matchup_ratio($losses, $games);
	    ?>
    <tr>
        <td class="k2-table-cell--left"><?php echo k2_player_link($opponentId, $opponentName); ?></td>
        <td><?php echo player_opponents_games_cell_html($playerId, $opponentId, $games); ?></td>
        <td><?php if ($wins != 0) {
            echo "<span class='blue'>";
            echo $wins;
            echo '</span>';
        } else {
            echo '0';
        } ?></td>
        <td><?php echo $draws; ?></td>
        <td><?php if ($losses != 0) {
            echo "<span class='red'>";
            echo $losses;
            echo '</span>';
        } else {
            echo '0';
        } ?></td>
        <td><?php echo $wins != 0 ? number_format(100 * $winRatio, 1) . '%' : '0%'; ?></td>
        <td><?php echo number_format(100 * $drawRatio, 1);
        echo '%'; ?></td>
        <td><?php echo $losses != 0 ? number_format(100 * $lossRatio, 1) . '%' : '0%'; ?></td>
    </tr>
    <?php } ?>
</tbody>
</table>
</div>
    <?php
}

function player_opponents_render_wdl_table_live(mysqli $con, int $playerId): void
{
    $playerId = max(0, $playerId);
    $query = 'SELECT opponentID, COUNT(*), SUM(win), SUM(draw), SUM(defeat), AVG(win), AVG(draw), AVG(defeat)
FROM(
    (
    SELECT idB AS opponentID, homewin AS win, draw AS draw, awaywin AS defeat FROM ratedresults
    WHERE idA = ' . $playerId . '
    )
    UNION ALL
    (
    SELECT idA AS opponentID, awaywin AS win, draw AS draw, homewin AS defeat FROM ratedresults
    WHERE idB = ' . $playerId . '
    )
    ) AS derivedtable
GROUP BY opponentID
ORDER BY COUNT(*) DESC';

    $result = k2_query_or_public_error($con, $query, 'player opponents W/D/L table');
    $opponentIds = [];
    $rawRows = [];
    while ($row = mysqli_fetch_row($result)) {
        $opponentIds[] = (int) $row[0];
        $rawRows[] = $row;
    }
    $displayNames = k2_player_display_names_load($con, $opponentIds);
    $rows = [];
    foreach ($rawRows as $row) {
        $opponentId = (int) $row[0];
        $games = (int) $row[1];
        $rows[] = [
            'opponent_id' => $opponentId,
            'opponent_name' => k2_player_display_name($displayNames, $opponentId),
            'games' => $games,
            'wins' => (int) $row[2],
            'draws' => (int) $row[3],
            'losses' => (int) $row[4],
            'goals_for' => 0,
            'goals_against' => 0,
        ];
    }

    player_opponents_render_wdl_table_from_rows($rows, $playerId);
}

function player_opponents_render_wdl_table(mysqli $con, int $playerId): void
{
    $rows = player_opponents_matchup_summary_rows($con, $playerId);
    if ($rows === null) {
        player_opponents_render_wdl_table_live($con, $playerId);

        return;
    }

    player_opponents_render_wdl_table_from_rows($rows, $playerId);
}

/**
 * @param list<array<string, mixed>> $rows
 */
function player_opponents_render_goals_table_from_rows(array $rows, bool $extremesStored, int $playerId): void
{
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table k2-table--opponent-matchup" data-k2-table="sortable" data-k2-anchor-col="1" data-k2-default-sort="1" data-k2-default-direction="desc">
<thead>
    <tr>
        <th class="k2-table-cell--left" data-k2-sort="text">Opponent</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals for" data-k2-help="Goals scored against this opponent.">GF</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals against" data-k2-help="Goals conceded against this opponent.">GA</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals scored per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_scored_avg(), ENT_QUOTES, 'UTF-8'); ?>">GF/g</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals conceded per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_conceded_avg(), ENT_QUOTES, 'UTF-8'); ?>">GA/g</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goal_ratio(), ENT_QUOTES, 'UTF-8'); ?>">Ratio</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Total goals per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_total_goals_per_game(), ENT_QUOTES, 'UTF-8'); ?>">TG/g</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_most_scored(), ENT_QUOTES, 'UTF-8'); ?>">Max GF</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_most_conceded(), ENT_QUOTES, 'UTF-8'); ?>">Max GA</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_win_margin(), ENT_QUOTES, 'UTF-8'); ?>">Max win</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_loss_margin(), ENT_QUOTES, 'UTF-8'); ?>">Max loss</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goal_sum(), ENT_QUOTES, 'UTF-8'); ?>">Max sum</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_biggest_draw(), ENT_QUOTES, 'UTF-8'); ?>">Draw</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_least_scored(), ENT_QUOTES, 'UTF-8'); ?>">Min GF</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_least_conceded(), ENT_QUOTES, 'UTF-8'); ?>">Min GA</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_least_goal_sum(), ENT_QUOTES, 'UTF-8'); ?>">Min sum</th>
    </tr>
</thead>
<tbody>
	<?php foreach ($rows as $row) {
	    $opponentId = (int) $row['opponent_id'];
	    $opponentName = (string) $row['opponent_name'];
	    $games = (int) $row['games'];
	    $goalsFor = (int) $row['goals_for'];
	    $goalsAgainst = (int) $row['goals_against'];
	    $averageFor = $games > 0 ? $goalsFor / $games : 0.0;
	    $averageAgainst = $games > 0 ? $goalsAgainst / $games : 0.0;
	    $averageTotal = $games > 0 ? ($goalsFor + $goalsAgainst) / $games : 0.0;
	    $goalRatio = player_opponents_goal_ratio($goalsFor, $goalsAgainst);

	    if ($extremesStored) {
	        $mostScored = (int) ($row['max_goals_for'] ?? 0);
	        $mostConceded = (int) ($row['max_goals_against'] ?? 0);
	        $leastScored = (int) ($row['min_goals_for'] ?? 0);
	        $leastConceded = (int) ($row['min_goals_against'] ?? 0);
	        $biggestWin = isset($row['max_win_margin']) && $row['max_win_margin'] !== null ? (int) $row['max_win_margin'] : null;
	        $biggestLoss = isset($row['max_loss_margin']) && $row['max_loss_margin'] !== null ? (int) $row['max_loss_margin'] : null;
	        $biggestGoalSum = (int) ($row['max_goal_sum'] ?? 0);
	        $smallestGoalSum = (int) ($row['min_goal_sum'] ?? 0);
	        $biggestDraw = isset($row['max_draw_goals']) && $row['max_draw_goals'] !== null ? (int) $row['max_draw_goals'] : null;
	        $numberDraws = (int) ($row['draws'] ?? 0);
	    } else {
	        $mostScored = null;
	        $mostConceded = null;
	        $leastScored = null;
	        $leastConceded = null;
	        $biggestWin = null;
	        $biggestLoss = null;
	        $biggestGoalSum = null;
	        $smallestGoalSum = null;
	        $biggestDraw = 0;
	        $numberDraws = (int) ($row['draws'] ?? 0);
	    }

	    $drawSort = $numberDraws > 0 && $extremesStored && $biggestDraw !== null ? $biggestDraw : -1;
	    $drawDisplay = $numberDraws > 0 && $extremesStored && $biggestDraw !== null ? $biggestDraw . '-' . $biggestDraw : '-';
	    ?>
    <tr>
        <td class="k2-table-cell--left"><?php echo k2_player_link($opponentId, $opponentName); ?></td>
        <td><?php echo player_opponents_games_cell_html($playerId, $opponentId, $games); ?></td>
        <td><?php if ($goalsFor != 0) {
            echo "<span class='blue'>";
            echo $goalsFor;
            echo '</span>';
        } else {
            echo '0';
        } ?></td>
        <td><?php if ($goalsAgainst != 0) {
            echo "<span class='red'>";
            echo $goalsAgainst;
            echo '</span>';
        } else {
            echo '0';
        } ?></td>
        <td><?php echo number_format($averageFor, 2); ?></td>
        <td><?php echo number_format($averageAgainst, 2); ?></td>
        <td><?php
            if ($goalRatio < 0) {
                echo '-';
            } else {
                echo number_format($goalRatio, 2);
            }
        ?></td>
        <td><?php echo number_format($averageTotal, 2); ?></td>
        <td><?php echo $extremesStored ? (string) $mostScored : '—'; ?></td>
        <td><?php echo $extremesStored ? (string) $mostConceded : '—'; ?></td>
        <td><?php echo $extremesStored && $biggestWin !== null ? (string) $biggestWin : '—'; ?></td>
        <td><?php echo $extremesStored && $biggestLoss !== null ? (string) $biggestLoss : '—'; ?></td>
        <td><?php echo $extremesStored ? (string) $biggestGoalSum : '—'; ?></td>
        <td data-k2-sort-value="<?php echo $drawSort; ?>"><?php echo $drawDisplay; ?></td>
        <td><?php echo $extremesStored ? (string) $leastScored : '—'; ?></td>
        <td><?php echo $extremesStored ? (string) $leastConceded : '—'; ?></td>
        <td><?php echo $extremesStored ? (string) $smallestGoalSum : '—'; ?></td>
    </tr>
    <?php } ?>
</tbody>
</table>
</div>
    <?php
}

function player_opponents_render_goals_table_live(mysqli $con, int $playerId): void
{
    $playerId = max(0, $playerId);
    $query = 'SELECT opponentID, COUNT(*), SUM(goalsfor), SUM(goalsagainst), AVG(goalsfor), AVG(goalsagainst), MAX(goalsfor), MAX(goalsagainst), MIN(goalsfor), MIN(goalsagainst), MAX(CASE WHEN goalsfor > goalsagainst THEN goalsfor - goalsagainst ELSE NULL END), MAX(CASE WHEN goalsagainst > goalsfor THEN goalsagainst - goalsfor ELSE NULL END), MAX(CASE WHEN draw = 1 THEN goalsfor ELSE NULL END), SUM(draw), MAX(goalsfor+goalsagainst), MIN(goalsfor+goalsagainst)
FROM(
    (
    SELECT idB AS opponentID, goalsA AS goalsfor, goalsB AS goalsagainst, draw AS draw FROM ratedresults
    WHERE idA = ' . $playerId . '
    )
    UNION ALL
    (
    SELECT idA AS opponentID, goalsB AS goalsfor, goalsA AS goalsagainst, draw AS draw FROM ratedresults
    WHERE idB = ' . $playerId . '
    )
    ) AS derivedtable
GROUP BY opponentID
ORDER BY COUNT(*) DESC';

    $result = k2_query_or_public_error($con, $query, 'player opponents goals table');
    $opponentIds = [];
    $rawRows = [];
    while ($row = mysqli_fetch_row($result)) {
        $opponentIds[] = (int) $row[0];
        $rawRows[] = $row;
    }
    $displayNames = k2_player_display_names_load($con, $opponentIds);
    $rows = [];
    foreach ($rawRows as $row) {
        $opponentId = (int) $row[0];
        $rows[] = [
            'opponent_id' => $opponentId,
            'opponent_name' => k2_player_display_name($displayNames, $opponentId),
            'games' => (int) $row[1],
            'goals_for' => (int) $row[2],
            'goals_against' => (int) $row[3],
            'draws' => (int) $row[13],
            'max_goals_for' => (int) $row[6],
            'max_goals_against' => (int) $row[7],
            'min_goals_for' => (int) $row[8],
            'min_goals_against' => (int) $row[9],
            'max_win_margin' => $row[10] !== null && $row[10] !== '' ? (int) $row[10] : null,
            'max_loss_margin' => $row[11] !== null && $row[11] !== '' ? (int) $row[11] : null,
            'max_draw_goals' => (int) $row[12],
            'max_goal_sum' => (int) $row[14],
            'min_goal_sum' => (int) $row[15],
        ];
    }

    player_opponents_render_goals_table_from_rows($rows, true, $playerId);
}

function player_opponents_render_goals_table(mysqli $con, int $playerId): void
{
    $rows = player_opponents_matchup_summary_rows($con, $playerId);
    if ($rows === null || !player_opponents_matchup_summary_has_extension($con)) {
        player_opponents_render_goals_table_live($con, $playerId);

        return;
    }

    player_opponents_render_goals_table_from_rows($rows, true, $playerId);
}

/**
 * @param list<array<string, mixed>> $rows
 */
function player_opponents_render_dds_table_from_rows(array $rows, int $playerId): void
{
    ?>
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table k2-table--opponent-matchup" data-k2-table="sortable" data-k2-anchor-col="1" data-k2-default-sort="1" data-k2-default-direction="desc">
<thead>
    <tr>
        <th class="k2-table-cell--left" data-k2-sort="text">Opponent</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits(), ENT_QUOTES, 'UTF-8'); ?>">Double Digits</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets(), ENT_QUOTES, 'UTF-8'); ?>">Clean Sheets</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Double Digits ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits_ratio(), ENT_QUOTES, 'UTF-8'); ?>">DD Ratio</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Clean Sheets ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets_ratio(), ENT_QUOTES, 'UTF-8'); ?>">CS Ratio</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits_conceded(), ENT_QUOTES, 'UTF-8'); ?>">DD conceded</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets_conceded(), ENT_QUOTES, 'UTF-8'); ?>">CS conceded</th>
        <th data-k2-sort="number" data-k2-tooltip-label="DD conceded ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_double_digits_conceded_ratio(), ENT_QUOTES, 'UTF-8'); ?>">DD C Ratio</th>
        <th data-k2-sort="number" data-k2-tooltip-label="CS conceded ratio" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_clean_sheets_conceded_ratio(), ENT_QUOTES, 'UTF-8'); ?>">CS C Ratio</th>
    </tr>
</thead>
<tbody>
	<?php foreach ($rows as $row) {
	    $opponentId = (int) $row['opponent_id'];
	    $opponentName = (string) $row['opponent_name'];
	    $games = (int) $row['games'];
	    $doubleDigits = (int) ($row['double_digits'] ?? 0);
	    $doubleDigitsConceded = (int) ($row['double_digits_conceded'] ?? 0);
	    $cleanSheets = (int) ($row['clean_sheets'] ?? 0);
	    $cleanSheetsConceded = (int) ($row['clean_sheets_conceded'] ?? 0);
	    $ddRatio = player_opponents_matchup_ratio($doubleDigits, $games);
	    $ddConcededRatio = player_opponents_matchup_ratio($doubleDigitsConceded, $games);
	    $csRatio = player_opponents_matchup_ratio($cleanSheets, $games);
	    $csConcededRatio = player_opponents_matchup_ratio($cleanSheetsConceded, $games);
	    ?>
    <tr>
        <td class="k2-table-cell--left"><?php echo k2_player_link($opponentId, $opponentName); ?></td>
        <td><?php echo player_opponents_games_cell_html($playerId, $opponentId, $games); ?></td>
        <td><?php if ($doubleDigits != 0) {
            echo "<span class='blue'>";
            echo $doubleDigits;
            echo '</span>';
        } else {
            echo '0';
        } ?></td>
        <td><?php echo $cleanSheets; ?></td>
        <td><?php echo player_opponents_dds_ratio_cell($ddRatio); ?></td>
        <td><?php echo player_opponents_dds_ratio_cell($csRatio); ?></td>
        <td><?php if ($doubleDigitsConceded != 0) {
            echo "<span class='red'>";
            echo $doubleDigitsConceded;
            echo '</span>';
        } else {
            echo '0';
        } ?></td>
        <td><?php echo $cleanSheetsConceded; ?></td>
        <td><?php echo player_opponents_dds_ratio_cell($ddConcededRatio); ?></td>
        <td><?php echo player_opponents_dds_ratio_cell($csConcededRatio); ?></td>
    </tr>
    <?php } ?>
</tbody>
</table>
</div>
    <?php
}

function player_opponents_render_dds_table_live(mysqli $con, int $playerId): void
{
    $playerId = max(0, $playerId);
    $query = 'SELECT opponentID, COUNT(*), SUM(DD), SUM(DDC), SUM(CS), SUM(CSC), AVG(DD), AVG(DDC), AVG(CS), AVG(CSC)
FROM(
    (
    SELECT idB AS opponentID, DDPlayerA AS DD, DDPlayerB AS DDC, CSPlayerA AS CS, CSPlayerB AS CSC FROM ratedresults
    WHERE idA = ' . $playerId . '
    )
    UNION ALL
    (
    SELECT idA AS opponentID, DDPlayerB AS DD, DDPlayerA AS DDC, CSPlayerB AS CS, CSPlayerA AS CSC FROM ratedresults
    WHERE idB = ' . $playerId . '
    )
    ) AS derivedtable
GROUP BY opponentID
ORDER BY COUNT(*) DESC';

    $result = k2_query_or_public_error($con, $query, 'player opponents DDs table');
    $opponentIds = [];
    $rawRows = [];
    while ($row = mysqli_fetch_row($result)) {
        $opponentIds[] = (int) $row[0];
        $rawRows[] = $row;
    }
    $displayNames = k2_player_display_names_load($con, $opponentIds);
    $rows = [];
    foreach ($rawRows as $row) {
        $opponentId = (int) $row[0];
        $games = (int) $row[1];
        $rows[] = [
            'opponent_id' => $opponentId,
            'opponent_name' => k2_player_display_name($displayNames, $opponentId),
            'games' => $games,
            'double_digits' => (int) $row[2],
            'double_digits_conceded' => (int) $row[3],
            'clean_sheets' => (int) $row[4],
            'clean_sheets_conceded' => (int) $row[5],
        ];
    }

    player_opponents_render_dds_table_from_rows($rows, $playerId);
}

function player_opponents_render_dds_table(mysqli $con, int $playerId): void
{
    $rows = player_opponents_matchup_summary_rows($con, $playerId);
    if ($rows === null || !player_opponents_matchup_summary_has_extension($con)) {
        player_opponents_render_dds_table_live($con, $playerId);

        return;
    }

    player_opponents_render_dds_table_from_rows($rows, $playerId);
}
