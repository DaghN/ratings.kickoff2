<?php
/**
 * World Cup Hall of Fame compute + persist (mirrors scripts/amiga/wc_hof.py +
 * wc_hof_persist.py). Writes amiga_wc_hof_snapshots (per WC) + amiga_wc_hof_present
 * (id=1) at World Cup finalize, after the WC player slice for the event is persisted.
 *
 * Selection: strict > to beat; tie -> lowest player_id; ratio rows gated at games >= 20.
 * {Prefix}Date for cumulative / ratio rows is DERIVED from the holder's
 * amiga_player_slice_at_event timeline (decision ID1). Idempotent UPSERT (decision ID2).
 *
 * @see docs/amiga-wc-hof-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_honours_totals_lib.php';

const AMIGA_WC_HOF_MIN_GAMES = 20;
const AMIGA_WC_HOF_NAME_REGEXP = '^World Cup[[:space:]]+[^[:space:]]';

/**
 * @return list<string>
 */
function amiga_wc_hof_payload_columns(): array
{
    return [
        'MostWcPlayed',         'MostWcGold',         'MostWcGames',         'MostWcWins',         'MostWcPoints',         'BestWcPtsPerGame',         'BestWcWinRate',         'MostWcGoalsFor',         'BestWcGoalsForPerGame',         'BestWcGoalsAgainstPerGame',         'BestWcGoalDiffPerGame',         'BestWcGoalRatio',         'MostWcDoubleDigits',         'BestWcDoubleDigitsRatio',         'MostWcCleanSheets',         'BestWcCleanSheetsRatio',         'MostWcOpponents',         'MostWcVictims',         'MostWcDoubleDigitsVictims',         'MostWcCleanSheetsVictims',         'MostWcGoalsInOneGame',         'BiggestWcWinDifference',         'BiggestWcDrawSum',         'BiggestWcSumOfGoals',         'MostWcBestAttackAwards',         'MostWcBestDefenseAwards',         'BestSingleWcGoalsForPerGame',         'BestSingleWcGoalsAgainstPerGame',         'MostWcPlayedID',         'MostWcGoldID',         'MostWcGamesID',         'MostWcWinsID',         'MostWcPointsID',         'BestWcPtsPerGameID',         'BestWcWinRateID',         'MostWcGoalsForID',         'BestWcGoalsForPerGameID',         'BestWcGoalsAgainstPerGameID',         'BestWcGoalDiffPerGameID',         'BestWcGoalRatioID',         'MostWcDoubleDigitsID',         'BestWcDoubleDigitsRatioID',         'MostWcCleanSheetsID',         'BestWcCleanSheetsRatioID',         'MostWcOpponentsID',         'MostWcVictimsID',         'MostWcDoubleDigitsVictimsID',         'MostWcCleanSheetsVictimsID',         'MostWcGoalsInOneGameID',         'BiggestWcWinDifferenceID',         'BiggestWcDrawSumIDA',         'BiggestWcDrawSumIDB',         'BiggestWcSumOfGoalsIDA',         'BiggestWcSumOfGoalsIDB',         'MostWcBestAttackAwardsID',         'MostWcBestDefenseAwardsID',         'BestSingleWcGoalsForPerGameID',         'BestSingleWcGoalsAgainstPerGameID',         'MostWcPlayedName',         'MostWcGoldName',         'MostWcGamesName',         'MostWcWinsName',         'MostWcPointsName',         'BestWcPtsPerGameName',         'BestWcWinRateName',         'MostWcGoalsForName',         'BestWcGoalsForPerGameName',         'BestWcGoalsAgainstPerGameName',         'BestWcGoalDiffPerGameName',         'BestWcGoalRatioName',         'MostWcDoubleDigitsName',         'BestWcDoubleDigitsRatioName',         'MostWcCleanSheetsName',         'BestWcCleanSheetsRatioName',         'MostWcOpponentsName',         'MostWcVictimsName',         'MostWcDoubleDigitsVictimsName',         'MostWcCleanSheetsVictimsName',         'MostWcGoalsInOneGameName',         'BiggestWcWinDifferenceName',         'BiggestWcDrawSumNameA',         'BiggestWcDrawSumNameB',         'BiggestWcSumOfGoalsNameA',         'BiggestWcSumOfGoalsNameB',         'MostWcBestAttackAwardsName',         'MostWcBestDefenseAwardsName',         'BestSingleWcGoalsForPerGameName',         'BestSingleWcGoalsAgainstPerGameName',         'MostWcPlayedDate',         'MostWcGoldDate',         'MostWcGamesDate',         'MostWcWinsDate',         'MostWcPointsDate',         'BestWcPtsPerGameDate',         'BestWcWinRateDate',         'MostWcGoalsForDate',         'BestWcGoalsForPerGameDate',         'BestWcGoalsAgainstPerGameDate',         'BestWcGoalDiffPerGameDate',         'BestWcGoalRatioDate',         'MostWcDoubleDigitsDate',         'BestWcDoubleDigitsRatioDate',         'MostWcCleanSheetsDate',         'BestWcCleanSheetsRatioDate',         'MostWcOpponentsDate',         'MostWcVictimsDate',         'MostWcDoubleDigitsVictimsDate',         'MostWcCleanSheetsVictimsDate',         'MostWcGoalsInOneGameDate',         'BiggestWcWinDifferenceDate',         'BiggestWcDrawSumDate',         'BiggestWcSumOfGoalsDate',         'MostWcBestAttackAwardsDate',         'MostWcBestDefenseAwardsDate',         'BestSingleWcGoalsForPerGameDate',         'BestSingleWcGoalsAgainstPerGameDate',         'MostWcGoalsInOneGameGameID',         'BiggestWcWinDifferenceGameID',         'BiggestWcDrawSumGameID',         'BiggestWcSumOfGoalsGameID',         'BestSingleWcGoalsForPerGameTournamentID',         'BestSingleWcGoalsAgainstPerGameTournamentID',
    ];
}

/**
 * @return list<string>
 */
function amiga_wc_hof_snapshot_columns(): array
{
    return array_merge(
        ['tournament_id', 'event_date', 'event_chrono', 'tournament_name', 'finalized_at'],
        amiga_wc_hof_payload_columns()
    );
}

/**
 * Cumulative "Most ..." holders: prefix => WC slice column.
 *
 * @return array<string, string>
 */
function amiga_wc_hof_cumulative_holders(): array
{
    return [
        'MostWcPlayed' => 'tournaments_played',
        'MostWcGold' => 'gold',
        'MostWcGames' => 'games',
        'MostWcWins' => 'wins',
        'MostWcPoints' => 'points',
        'MostWcGoalsFor' => 'goals_for',
        'MostWcDoubleDigits' => 'double_digits',
        'MostWcCleanSheets' => 'clean_sheets',
        'MostWcOpponents' => 'different_opponents',
        'MostWcVictims' => 'different_victims',
        'MostWcDoubleDigitsVictims' => 'double_digits_victims',
        'MostWcCleanSheetsVictims' => 'clean_sheets_victims',
        'MostWcBestAttackAwards' => 'best_attack_awards',
        'MostWcBestDefenseAwards' => 'best_defense_awards',
    ];
}

function amiga_wc_hof_num($value): float
{
    return $value === null ? 0.0 : (float) $value;
}

function amiga_wc_hof_q4(?float $value): ?float
{
    return $value === null ? null : round($value, 4);
}

/**
 * @param array<string, mixed> $row
 */
function amiga_wc_hof_ratio(string $prefix, array $row): ?float
{
    $g = amiga_wc_hof_num($row['games'] ?? null);
    switch ($prefix) {
        case 'BestWcPtsPerGame':
            return $g <= 0 ? null : amiga_wc_hof_num($row['points'] ?? null) / $g;
        case 'BestWcWinRate':
            return $g <= 0 ? null
                : (amiga_wc_hof_num($row['wins'] ?? null) + 0.5 * amiga_wc_hof_num($row['draws'] ?? null)) / $g;
        case 'BestWcGoalsForPerGame':
            return $g <= 0 ? null : amiga_wc_hof_num($row['goals_for'] ?? null) / $g;
        case 'BestWcGoalsAgainstPerGame':
            return $g <= 0 ? null : amiga_wc_hof_num($row['goals_against'] ?? null) / $g;
        case 'BestWcGoalDiffPerGame':
            return $g <= 0 ? null
                : (amiga_wc_hof_num($row['goals_for'] ?? null) - amiga_wc_hof_num($row['goals_against'] ?? null)) / $g;
        case 'BestWcGoalRatio':
            return ($row['goal_ratio'] ?? null) === null ? null : (float) $row['goal_ratio'];
        case 'BestWcDoubleDigitsRatio':
            return ($row['double_digits_ratio'] ?? null) === null ? null : (float) $row['double_digits_ratio'];
        case 'BestWcCleanSheetsRatio':
            return ($row['clean_sheets_ratio'] ?? null) === null ? null : (float) $row['clean_sheets_ratio'];
    }

    return null;
}

/**
 * Ratio holders: prefix => higher_better.
 *
 * @return array<string, bool>
 */
function amiga_wc_hof_ratio_holders(): array
{
    return [
        'BestWcPtsPerGame' => true,
        'BestWcWinRate' => true,
        'BestWcGoalsForPerGame' => true,
        'BestWcGoalsAgainstPerGame' => false,
        'BestWcGoalDiffPerGame' => true,
        'BestWcGoalRatio' => true,
        'BestWcDoubleDigitsRatio' => true,
        'BestWcCleanSheetsRatio' => true,
    ];
}

/**
 * Latest WC slice_at_event row per player with (date, chrono, tid) <= cutoff.
 *
 * @return list<array<string, mixed>>
 */
function amiga_wc_hof_slice_cutoff_rows(mysqli $con, $eventDate, float $chrono, int $tid): array
{
    $sql = 'SELECT x.*, p.name AS player_name FROM ('
        . '  SELECT s.*, ROW_NUMBER() OVER ('
        . '    PARTITION BY s.player_id '
        . '    ORDER BY s.event_date DESC, s.event_chrono DESC, s.as_of_tournament_id DESC'
        . '  ) AS rn '
        . "  FROM amiga_player_slice_at_event s "
        . "  WHERE s.slice_key = 'world_cup' "
        . '    AND (s.event_date, s.event_chrono, s.as_of_tournament_id) <= (?, ?, ?)'
        . ') x INNER JOIN amiga_players p ON p.id = x.player_id WHERE x.rn = 1';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare wc hof slice cutoff: ' . $con->error);
    }
    $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute wc hof slice cutoff: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_wc_hof_player_timeline(mysqli $con, int $pid, $eventDate, float $chrono, int $tid): array
{
    $sql = "SELECT * FROM amiga_player_slice_at_event s "
        . "WHERE s.slice_key = 'world_cup' AND s.player_id = ? "
        . '  AND (s.event_date, s.event_chrono, s.as_of_tournament_id) <= (?, ?, ?) '
        . 'ORDER BY s.event_date ASC, s.event_chrono ASC, s.as_of_tournament_id ASC';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare wc hof timeline: ' . $con->error);
    }
    $stmt->bind_param('isdi', $pid, $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute wc hof timeline: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

/**
 * @param list<array<string, mixed>> $timeline
 */
function amiga_wc_hof_rise_date_cumulative(array $timeline, string $column)
{
    $last = null;
    $prev = null;
    foreach ($timeline as $row) {
        $value = amiga_wc_hof_num($row[$column] ?? null);
        if ($prev === null || $value > $prev) {
            $last = $row['event_date'] ?? null;
        }
        $prev = $value;
    }

    return $last;
}

/**
 * @param list<array<string, mixed>> $timeline
 */
function amiga_wc_hof_rise_date_ratio(array $timeline, string $prefix, bool $higherBetter)
{
    $last = null;
    $prev = null;
    foreach ($timeline as $row) {
        if (amiga_wc_hof_num($row['games'] ?? null) < AMIGA_WC_HOF_MIN_GAMES) {
            continue;
        }
        $value = amiga_wc_hof_ratio($prefix, $row);
        if ($value === null) {
            continue;
        }
        $improved = $prev === null || ($higherBetter ? $value > $prev : $value < $prev);
        if ($improved) {
            $last = $row['event_date'] ?? null;
            $prev = $value;
        }
    }

    return $last;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>|null
 */
function amiga_wc_hof_pick_cumulative(array $rows, string $column): ?array
{
    $best = null;
    $bestVal = 0.0;
    foreach ($rows as $row) {
        $value = amiga_wc_hof_num($row[$column] ?? null);
        if ($value <= 0) {
            continue;
        }
        $pid = (int) $row['player_id'];
        if ($best === null || $value > $bestVal || ($value == $bestVal && $pid < (int) $best['player_id'])) {
            $best = $row;
            $bestVal = $value;
        }
    }

    return $best;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array{0: array<string, mixed>, 1: float}|null
 */
function amiga_wc_hof_pick_ratio(array $rows, string $prefix, bool $higherBetter): ?array
{
    $best = null;
    $bestVal = null;
    foreach ($rows as $row) {
        if (amiga_wc_hof_num($row['games'] ?? null) < AMIGA_WC_HOF_MIN_GAMES) {
            continue;
        }
        $value = amiga_wc_hof_ratio($prefix, $row);
        if ($value === null) {
            continue;
        }
        $pid = (int) $row['player_id'];
        if ($best === null) {
            $best = $row;
            $bestVal = $value;
            continue;
        }
        $better = $higherBetter ? $value > $bestVal : $value < $bestVal;
        if ($better || ($value == $bestVal && $pid < (int) $best['player_id'])) {
            $best = $row;
            $bestVal = $value;
        }
    }

    return $best === null ? null : [$best, (float) $bestVal];
}

function amiga_wc_hof_date_expr(): string
{
    return "COALESCE(DATE_FORMAT(t.event_date, '%Y-%m-%d'), DATE_FORMAT(g.game_date, '%Y-%m-%d'))";
}

/**
 * @return array<string, mixed>
 */
function amiga_wc_hof_single_game_patches(mysqli $con, $eventDate, float $chrono, int $tid): array
{
    $patch = [];
    $regex = AMIGA_WC_HOF_NAME_REGEXP;
    $dateExpr = amiga_wc_hof_date_expr();

    // Most goals in one game (per side).
    $sql = "SELECT game_id, player_id, player_name, goals, record_date FROM ("
        . "  SELECT g.id AS game_id, g.player_a_id AS player_id, pa.name AS player_name, "
        . "         g.goals_a AS goals, {$dateExpr} AS record_date "
        . "  FROM amiga_games g INNER JOIN amiga_players pa ON pa.id = g.player_a_id "
        . "  INNER JOIN tournaments t ON t.id = g.tournament_id "
        . "  WHERE t.name REGEXP ? AND (t.event_date, t.chrono, t.id) <= (?, ?, ?) "
        . "  UNION ALL "
        . "  SELECT g.id, g.player_b_id, pb.name, g.goals_b, {$dateExpr} "
        . "  FROM amiga_games g INNER JOIN amiga_players pb ON pb.id = g.player_b_id "
        . "  INNER JOIN tournaments t ON t.id = g.tournament_id "
        . "  WHERE t.name REGEXP ? AND (t.event_date, t.chrono, t.id) <= (?, ?, ?) "
        . ") sides ORDER BY goals DESC, game_id ASC LIMIT 1";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare wc hof most goals: ' . $con->error);
    }
    $stmt->bind_param('ssdissdi', $regex, $eventDate, $chrono, $tid, $regex, $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute wc hof most goals: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row !== null && (int) $row['goals'] > 0) {
        $patch['MostWcGoalsInOneGame'] = (int) $row['goals'];
        $patch['MostWcGoalsInOneGameID'] = (int) $row['player_id'];
        $patch['MostWcGoalsInOneGameName'] = $row['player_name'];
        $patch['MostWcGoalsInOneGameDate'] = $row['record_date'];
        $patch['MostWcGoalsInOneGameGameID'] = (int) $row['game_id'];
    }

    // Biggest winning margin.
    $sql = "SELECT g.id AS game_id, r.goal_difference AS margin, "
        . "  CASE WHEN r.actual_score = 1.0 THEN g.player_a_id WHEN r.actual_score = 0.0 THEN g.player_b_id END AS player_id, "
        . "  CASE WHEN r.actual_score = 1.0 THEN pa.name WHEN r.actual_score = 0.0 THEN pb.name END AS player_name, "
        . "  {$dateExpr} AS record_date "
        . "FROM amiga_games g INNER JOIN amiga_game_ratings r ON r.game_id = g.id "
        . "INNER JOIN amiga_players pa ON pa.id = g.player_a_id "
        . "INNER JOIN amiga_players pb ON pb.id = g.player_b_id "
        . "INNER JOIN tournaments t ON t.id = g.tournament_id "
        . "WHERE r.actual_score IN (0.0, 1.0) AND r.goal_difference IS NOT NULL "
        . "  AND t.name REGEXP ? AND (t.event_date, t.chrono, t.id) <= (?, ?, ?) "
        . "ORDER BY r.goal_difference DESC, g.id ASC LIMIT 1";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare wc hof win margin: ' . $con->error);
    }
    $stmt->bind_param('ssdi', $regex, $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute wc hof win margin: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row !== null && $row['player_id'] !== null) {
        $patch['BiggestWcWinDifference'] = (int) $row['margin'];
        $patch['BiggestWcWinDifferenceID'] = (int) $row['player_id'];
        $patch['BiggestWcWinDifferenceName'] = $row['player_name'];
        $patch['BiggestWcWinDifferenceDate'] = $row['record_date'];
        $patch['BiggestWcWinDifferenceGameID'] = (int) $row['game_id'];
    }

    // Biggest draw sum.
    $sql = "SELECT g.id AS game_id, (g.goals_a + g.goals_b) AS draw_sum, "
        . "  g.player_a_id, g.player_b_id, pa.name AS name_a, pb.name AS name_b, {$dateExpr} AS record_date "
        . "FROM amiga_games g INNER JOIN amiga_game_ratings r ON r.game_id = g.id "
        . "INNER JOIN amiga_players pa ON pa.id = g.player_a_id "
        . "INNER JOIN amiga_players pb ON pb.id = g.player_b_id "
        . "INNER JOIN tournaments t ON t.id = g.tournament_id "
        . "WHERE r.actual_score = 0.5 AND t.name REGEXP ? AND (t.event_date, t.chrono, t.id) <= (?, ?, ?) "
        . "ORDER BY draw_sum DESC, g.id ASC LIMIT 1";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare wc hof draw sum: ' . $con->error);
    }
    $stmt->bind_param('ssdi', $regex, $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute wc hof draw sum: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row !== null) {
        $patch['BiggestWcDrawSum'] = (int) $row['draw_sum'];
        $patch['BiggestWcDrawSumIDA'] = (int) $row['player_a_id'];
        $patch['BiggestWcDrawSumIDB'] = (int) $row['player_b_id'];
        $patch['BiggestWcDrawSumNameA'] = $row['name_a'];
        $patch['BiggestWcDrawSumNameB'] = $row['name_b'];
        $patch['BiggestWcDrawSumDate'] = $row['record_date'];
        $patch['BiggestWcDrawSumGameID'] = (int) $row['game_id'];
    }

    // Biggest sum of goals.
    $sql = "SELECT g.id AS game_id, COALESCE(r.sum_of_goals, g.goals_a + g.goals_b) AS goal_sum, "
        . "  g.player_a_id, g.player_b_id, pa.name AS name_a, pb.name AS name_b, {$dateExpr} AS record_date "
        . "FROM amiga_games g INNER JOIN amiga_game_ratings r ON r.game_id = g.id "
        . "INNER JOIN amiga_players pa ON pa.id = g.player_a_id "
        . "INNER JOIN amiga_players pb ON pb.id = g.player_b_id "
        . "INNER JOIN tournaments t ON t.id = g.tournament_id "
        . "WHERE t.name REGEXP ? AND (t.event_date, t.chrono, t.id) <= (?, ?, ?) "
        . "ORDER BY goal_sum DESC, g.id ASC LIMIT 1";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare wc hof sum goals: ' . $con->error);
    }
    $stmt->bind_param('ssdi', $regex, $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute wc hof sum goals: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row !== null) {
        $patch['BiggestWcSumOfGoals'] = (int) $row['goal_sum'];
        $patch['BiggestWcSumOfGoalsIDA'] = (int) $row['player_a_id'];
        $patch['BiggestWcSumOfGoalsIDB'] = (int) $row['player_b_id'];
        $patch['BiggestWcSumOfGoalsNameA'] = $row['name_a'];
        $patch['BiggestWcSumOfGoalsNameB'] = $row['name_b'];
        $patch['BiggestWcSumOfGoalsDate'] = $row['record_date'];
        $patch['BiggestWcSumOfGoalsGameID'] = (int) $row['game_id'];
    }

    return $patch;
}

function amiga_wc_hof_tournament_event_date(mysqli $con, ?int $tournamentId)
{
    if ($tournamentId === null) {
        return null;
    }
    $stmt = $con->prepare('SELECT event_date FROM tournaments WHERE id = ? LIMIT 1');
    if ($stmt === false) {
        return null;
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row !== null ? ($row['event_date'] ?? null) : null;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function amiga_wc_hof_single_peak_patches(mysqli $con, array $rows): array
{
    $patch = [];
    $bestGf = null;
    $bestGa = null;
    foreach ($rows as $row) {
        $pid = (int) $row['player_id'];
        $gf = $row['best_single_wc_gf_per_game'] ?? null;
        if ($gf !== null) {
            if (
                $bestGf === null
                || (float) $gf > (float) $bestGf['best_single_wc_gf_per_game']
                || ((float) $gf == (float) $bestGf['best_single_wc_gf_per_game'] && $pid < (int) $bestGf['player_id'])
            ) {
                $bestGf = $row;
            }
        }
        $ga = $row['best_single_wc_ga_per_game'] ?? null;
        if ($ga !== null) {
            if (
                $bestGa === null
                || (float) $ga < (float) $bestGa['best_single_wc_ga_per_game']
                || ((float) $ga == (float) $bestGa['best_single_wc_ga_per_game'] && $pid < (int) $bestGa['player_id'])
            ) {
                $bestGa = $row;
            }
        }
    }
    if ($bestGf !== null) {
        $tidAnchor = $bestGf['best_single_wc_gf_per_game_tournament_id'] ?? null;
        $tidAnchor = $tidAnchor !== null ? (int) $tidAnchor : null;
        $patch['BestSingleWcGoalsForPerGame'] = amiga_wc_hof_q4((float) $bestGf['best_single_wc_gf_per_game']);
        $patch['BestSingleWcGoalsForPerGameID'] = (int) $bestGf['player_id'];
        $patch['BestSingleWcGoalsForPerGameName'] = $bestGf['player_name'] ?? null;
        $patch['BestSingleWcGoalsForPerGameTournamentID'] = $tidAnchor;
        $patch['BestSingleWcGoalsForPerGameDate'] = amiga_wc_hof_tournament_event_date($con, $tidAnchor);
    }
    if ($bestGa !== null) {
        $tidAnchor = $bestGa['best_single_wc_ga_per_game_tournament_id'] ?? null;
        $tidAnchor = $tidAnchor !== null ? (int) $tidAnchor : null;
        $patch['BestSingleWcGoalsAgainstPerGame'] = amiga_wc_hof_q4((float) $bestGa['best_single_wc_ga_per_game']);
        $patch['BestSingleWcGoalsAgainstPerGameID'] = (int) $bestGa['player_id'];
        $patch['BestSingleWcGoalsAgainstPerGameName'] = $bestGa['player_name'] ?? null;
        $patch['BestSingleWcGoalsAgainstPerGameTournamentID'] = $tidAnchor;
        $patch['BestSingleWcGoalsAgainstPerGameDate'] = amiga_wc_hof_tournament_event_date($con, $tidAnchor);
    }

    return $patch;
}

/**
 * Full WC HoF holder payload as of World Cup $tournamentId.
 *
 * @return array<string, mixed>
 */
function amiga_wc_hof_build_payload(mysqli $con, int $tournamentId, $eventDate, float $chrono): array
{
    $rows = amiga_wc_hof_slice_cutoff_rows($con, $eventDate, $chrono, $tournamentId);
    $timelines = [];
    $patch = [];

    foreach (amiga_wc_hof_cumulative_holders() as $prefix => $column) {
        $holder = amiga_wc_hof_pick_cumulative($rows, $column);
        if ($holder === null) {
            continue;
        }
        $pid = (int) $holder['player_id'];
        if (!isset($timelines[$pid])) {
            $timelines[$pid] = amiga_wc_hof_player_timeline($con, $pid, $eventDate, $chrono, $tournamentId);
        }
        $patch[$prefix] = (int) amiga_wc_hof_num($holder[$column] ?? null);
        $patch["{$prefix}ID"] = $pid;
        $patch["{$prefix}Name"] = $holder['player_name'] ?? null;
        $patch["{$prefix}Date"] = amiga_wc_hof_rise_date_cumulative($timelines[$pid], $column);
    }

    foreach (amiga_wc_hof_ratio_holders() as $prefix => $higherBetter) {
        $picked = amiga_wc_hof_pick_ratio($rows, $prefix, $higherBetter);
        if ($picked === null) {
            continue;
        }
        [$holder, $value] = $picked;
        $pid = (int) $holder['player_id'];
        if (!isset($timelines[$pid])) {
            $timelines[$pid] = amiga_wc_hof_player_timeline($con, $pid, $eventDate, $chrono, $tournamentId);
        }
        $patch[$prefix] = amiga_wc_hof_q4($value);
        $patch["{$prefix}ID"] = $pid;
        $patch["{$prefix}Name"] = $holder['player_name'] ?? null;
        $patch["{$prefix}Date"] = amiga_wc_hof_rise_date_ratio($timelines[$pid], $prefix, $higherBetter);
    }

    foreach (amiga_wc_hof_single_game_patches($con, $eventDate, $chrono, $tournamentId) as $k => $v) {
        $patch[$k] = $v;
    }
    foreach (amiga_wc_hof_single_peak_patches($con, $rows) as $k => $v) {
        $patch[$k] = $v;
    }

    $payload = [];
    foreach (amiga_wc_hof_payload_columns() as $col) {
        $payload[$col] = $patch[$col] ?? null;
    }

    return $payload;
}

/**
 * Compute + UPSERT amiga_wc_hof_snapshots row + amiga_wc_hof_present id=1.
 */
function amiga_wc_hof_persist_for_tournament(
    mysqli $con,
    int $tournamentId,
    ?string $finalizedAt = null,
): bool {
    $stmt = $con->prepare(
        'SELECT id, name, event_date, chrono, rating_finalized_at FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare wc hof tournament: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute wc hof tournament: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $tour = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($tour === null || !amiga_honours_is_world_cup_tournament((string) ($tour['name'] ?? ''))) {
        return false;
    }

    $eventDate = $tour['event_date'] ?? null;
    $chrono = (float) ($tour['chrono'] ?? 0);
    if ($finalizedAt === null) {
        $finalizedAt = $tour['rating_finalized_at'] !== null ? (string) $tour['rating_finalized_at'] : null;
    }
    if ($finalizedAt === null) {
        $finalizedAt = gmdate('Y-m-d H:i:s');
    }

    $payload = amiga_wc_hof_build_payload($con, $tournamentId, $eventDate, $chrono);

    $row = [
        'tournament_id' => $tournamentId,
        'event_date' => $eventDate,
        'event_chrono' => $chrono,
        'tournament_name' => (string) ($tour['name'] ?? ''),
        'finalized_at' => $finalizedAt,
    ] + $payload;

    amiga_wc_hof_upsert($con, 'amiga_wc_hof_snapshots', amiga_wc_hof_snapshot_columns(), $row, ['tournament_id']);

    $presentRow = ['id' => 1] + $payload;
    amiga_wc_hof_upsert($con, 'amiga_wc_hof_present', array_merge(['id'], amiga_wc_hof_payload_columns()), $presentRow, ['id']);

    return true;
}

/**
 * @param list<string> $columns
 * @param array<string, mixed> $row
 * @param list<string> $keyColumns
 */
function amiga_wc_hof_upsert(mysqli $con, string $table, array $columns, array $row, array $keyColumns): void
{
    $colList = implode(', ', array_map(static fn (string $c): string => "`{$c}`", $columns));
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $updates = [];
    foreach ($columns as $col) {
        if (!in_array($col, $keyColumns, true)) {
            $updates[] = "`{$col}` = VALUES(`{$col}`)";
        }
    }
    $sql = "INSERT INTO `{$table}` ({$colList}) VALUES ({$placeholders}) "
        . 'ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException("prepare {$table} upsert: " . $con->error);
    }
    $types = '';
    $values = [];
    foreach ($columns as $col) {
        $val = $row[$col] ?? null;
        if ($val === null) {
            $types .= 's';
            $values[] = null;
        } elseif (is_int($val)) {
            $types .= 'i';
            $values[] = $val;
        } elseif (is_float($val)) {
            $types .= 'd';
            $values[] = $val;
        } else {
            $types .= 's';
            $values[] = (string) $val;
        }
    }
    $bind = [$types];
    foreach ($values as $i => $v) {
        $bind[] = &$values[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException("execute {$table} upsert: " . $err);
    }
    $stmt->close();
}
