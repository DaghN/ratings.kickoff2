<?php
/**
 * Amiga realm snapshots — website read path (Hall of Fame at cutoff).
 *
 * @see docs/amiga-time-travel-policy.md
 * @see docs/amiga-realm-snapshot-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';

/**
 * HoF holder columns shared by amiga_generalstats and amiga_realm_snapshots.
 *
 * @return list<string>
 */
function amiga_hof_record_column_names(): array
{
    return [
        'MostGamesPlayed',
        'MostWins',
        'MostGoalsScored',
        'MostDoubleDigits',
        'MostCleanSheets',
        'MostDifferentOpponents',
        'MostDifferentVictims',
        'MostDoubleDigitsVictims',
        'MostCleanSheetsVictims',
        'MostGoalsScoredInOneGame',
        'BiggestWinDifference',
        'BiggestDrawSum',
        'BiggestSumOfGoals',
        'MostGamesPlayedID',
        'MostWinsID',
        'MostGoalsScoredID',
        'MostDoubleDigitsID',
        'MostCleanSheetsID',
        'MostDifferentOpponentsID',
        'MostDifferentVictimsID',
        'MostDoubleDigitsVictimsID',
        'MostCleanSheetsVictimsID',
        'MostGoalsScoredInOneGameID',
        'BiggestWinDifferenceID',
        'BiggestDrawSumIDA',
        'BiggestDrawSumIDB',
        'BiggestSumOfGoalsIDA',
        'BiggestSumOfGoalsIDB',
        'MostGamesPlayedName',
        'MostWinsName',
        'MostGoalsScoredName',
        'MostDoubleDigitsName',
        'MostCleanSheetsName',
        'MostDifferentOpponentsName',
        'MostDifferentVictimsName',
        'MostDoubleDigitsVictimsName',
        'MostCleanSheetsVictimsName',
        'MostGoalsScoredInOneGameName',
        'BiggestWinDifferenceName',
        'BiggestDrawSumNameA',
        'BiggestDrawSumNameB',
        'BiggestSumOfGoalsNameA',
        'BiggestSumOfGoalsNameB',
        'MostGamesPlayedDate',
        'MostWinsDate',
        'MostGoalsScoredDate',
        'MostDoubleDigitsDate',
        'MostCleanSheetsDate',
        'MostDifferentOpponentsDate',
        'MostDifferentVictimsDate',
        'MostDoubleDigitsVictimsDate',
        'MostCleanSheetsVictimsDate',
        'MostGoalsScoredInOneGameDate',
        'BiggestWinDifferenceDate',
        'BiggestDrawSumDate',
        'BiggestSumOfGoalsDate',
        'BiggestWinRatio',
        'BiggestWinRatioID',
        'BiggestWinRatioName',
        'BiggestGoalsForAverage',
        'BiggestGoalsForAverageID',
        'BiggestGoalsForAverageName',
        'SmallestGoalsAgainstAverage',
        'SmallestGoalsAgainstAverageID',
        'SmallestGoalsAgainstAverageName',
        'BiggestGoalRatio',
        'BiggestGoalRatioID',
        'BiggestGoalRatioName',
        'BiggestDoubleDigitsRatio',
        'BiggestDoubleDigitsRatioID',
        'BiggestDoubleDigitsRatioName',
        'BiggestCleanSheetsRatio',
        'BiggestCleanSheetsRatioID',
        'BiggestCleanSheetsRatioName',
        'MostGamesInOneYear',
        'MostTournamentsInOneYear',
        'MostTournamentsPlayed',
        'MostTournamentWins',
        'MostPerfectEvents',
        'MostCountriesPlayedIn',
        'MostOpponentCountriesFaced',
        'MostOpponentCountriesBeaten',
        'MostGamesInOneYearID',
        'MostTournamentsInOneYearID',
        'MostTournamentsPlayedID',
        'MostTournamentWinsID',
        'MostPerfectEventsID',
        'MostCountriesPlayedInID',
        'MostOpponentCountriesFacedID',
        'MostOpponentCountriesBeatenID',
        'MostGamesInOneYearName',
        'MostTournamentsInOneYearName',
        'MostTournamentsPlayedName',
        'MostTournamentWinsName',
        'MostPerfectEventsName',
        'MostCountriesPlayedInName',
        'MostOpponentCountriesFacedName',
        'MostOpponentCountriesBeatenName',
        'MostGamesInOneYearDate',
        'MostTournamentsInOneYearDate',
        'MostTournamentsPlayedDate',
        'MostTournamentWinsDate',
        'MostPerfectEventsDate',
        'MostCountriesPlayedInDate',
        'MostOpponentCountriesFacedDate',
        'MostOpponentCountriesBeatenDate',
    ];
}

/**
 * @param list<string> $columns
 */
function amiga_hof_sql_column_list(array $columns): string
{
    return implode(', ', array_map(static fn(string $c): string => '`' . $c . '`', $columns));
}

/**
 * Present-day HoF row (amiga_generalstats id=1).
 *
 * @return array<string, mixed>|null
 */
function amiga_generalstats_hof_row(mysqli $con, ?array $columns = null): ?array
{
    $columns ??= amiga_hof_record_column_names();
    $sql = 'SELECT ' . amiga_hof_sql_column_list($columns)
        . ' FROM amiga_generalstats WHERE id = 1 LIMIT 1';
    $result = mysqli_query($con, $sql);
    if (!$result) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return is_array($row) ? $row : null;
}

/**
 * Realm snapshot row at time-travel cutoff, shaped like amiga_generalstats holders.
 *
 * @return array<string, mixed>|null
 */
function amiga_realm_generalstats_at_cutoff(
    mysqli $con,
    AmigaSnapshotContext $ctx,
    ?array $columns = null
): ?array {
    if (!$ctx->isActive()) {
        return amiga_generalstats_hof_row($con, $columns);
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return null;
    }

    $columns ??= amiga_hof_record_column_names();
    $colSql = amiga_hof_sql_column_list($columns);
    $tournamentId = (int) $cutoff['tournament_id'];

    $stmt = $con->prepare(
        'SELECT ' . $colSql . ' FROM amiga_realm_snapshots WHERE tournament_id = ? LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('i', $tournamentId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : false;
            if ($res) {
                $res->free();
            }
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        } else {
            $stmt->close();
        }
    }

    $sql = 'SELECT ' . $colSql . ' FROM amiga_realm_snapshots '
        . 'WHERE (event_date, event_chrono, tournament_id) <= (?, ?, ?) '
        . 'ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC LIMIT 1';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $stmt->bind_param('sdi', $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return is_array($row) ? $row : null;
}

/**
 * @return array<string, mixed>|null
 */
function amiga_hof_records_load(mysqli $con, AmigaSnapshotContext $ctx): ?array
{
    if ($ctx->isActive()) {
        return amiga_realm_generalstats_at_cutoff($con, $ctx);
    }

    return amiga_generalstats_hof_row($con);
}

/**
 * HoF "Highest peak rating" — read-time projection from per-player PeakRating.
 *
 * @return array{value: float|null, player_id: int, name: string, date: string|null}
 */
function amiga_hof_peak_rating_holder(mysqli $con, AmigaSnapshotContext $ctx): array
{
    require_once __DIR__ . '/amiga_lb_snapshot_lib.php';
    require_once __DIR__ . '/amiga_lb_lib.php';

    $empty = ['value' => null, 'player_id' => 0, 'name' => '', 'date' => null];

    if (!$ctx->isActive()) {
        $sql = 'SELECT p.id AS player_id, p.name AS name, s.PeakRating AS peak_value, '
            . "DATE_FORMAT(tpr.event_date, '%Y-%m-%d') AS peak_date "
            . 'FROM amiga_players p '
            . 'INNER JOIN amiga_player_current s ON s.player_id = p.id '
            . 'LEFT JOIN tournaments tpr ON tpr.id = s.peak_rating_tournament_id '
            . 'WHERE ' . amiga_lb_player_where_sql() . ' AND s.PeakRating > 0 '
            . 'ORDER BY s.PeakRating DESC, s.Rating DESC, p.id ASC LIMIT 1';
        $result = k2_query_or_public_error($con, $sql, 'amiga hof peak rating holder');
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
    } else {
        $cutoff = $ctx->cutoff();
        if ($cutoff === null) {
            return $empty;
        }
        $sql = 'SELECT p.id AS player_id, p.name AS name, s.PeakRating AS peak_value, '
            . "DATE_FORMAT(tpr.event_date, '%Y-%m-%d') AS peak_date "
            . amiga_lb_snapshot_from_sql('s')
            . ' LEFT JOIN tournaments tpr ON tpr.id = s.peak_rating_tournament_id '
            . 'WHERE ' . amiga_lb_player_where_sql() . ' AND s.PeakRating > 0 '
            . 'ORDER BY s.PeakRating DESC, s.Rating DESC, p.id ASC LIMIT 1';
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('prepare amiga hof peak rating holder: ' . $con->error);
        }
        $eventDate = $cutoff['event_date'];
        $chrono = (float) $cutoff['chrono'];
        $tournamentId = (int) $cutoff['tournament_id'];
        $stmt->bind_param('sdi', $eventDate, $chrono, $tournamentId);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('execute amiga hof peak rating holder: ' . $err);
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : false;
        if ($res) {
            $res->free();
        }
        $stmt->close();
    }

    if (!is_array($row)) {
        return $empty;
    }

    return [
        'value' => (float) $row['peak_value'],
        'player_id' => (int) $row['player_id'],
        'name' => (string) $row['name'],
        'date' => $row['peak_date'] !== null ? (string) $row['peak_date'] : null,
    ];
}

/**
 * HoF "Highest winning frequency" — read-time win rate (draws = half a win).
 *
 * Matches rating LB `amiga_wc_lb_win_rate()` / sort column 8; not stored `WinRatio`.
 *
 * @return array{value: float|null, player_id: int, name: string}
 */
function amiga_hof_win_rate_holder(mysqli $con, AmigaSnapshotContext $ctx): array
{
    require_once __DIR__ . '/amiga_lb_snapshot_lib.php';
    require_once __DIR__ . '/amiga_lb_lib.php';

    $empty = ['value' => null, 'player_id' => 0, 'name' => ''];
    $minGames = (int) k2_established_min_games();
    $winRateExpr = '(s.NumberWins + 0.5 * s.NumberDraws) / s.NumberGames';

    if (!$ctx->isActive()) {
        $sql = 'SELECT p.id AS player_id, p.name AS name, '
            . $winRateExpr . ' AS win_rate_value '
            . 'FROM amiga_players p '
            . 'INNER JOIN amiga_player_current s ON s.player_id = p.id '
            . 'WHERE ' . amiga_lb_player_where_sql()
            . ' AND s.NumberGames >= ' . $minGames . ' '
            . 'ORDER BY win_rate_value DESC, p.id ASC LIMIT 1';
        $result = k2_query_or_public_error($con, $sql, 'amiga hof win rate holder');
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
    } else {
        $cutoff = $ctx->cutoff();
        if ($cutoff === null) {
            return $empty;
        }
        $sql = 'SELECT p.id AS player_id, p.name AS name, '
            . $winRateExpr . ' AS win_rate_value '
            . amiga_lb_snapshot_from_sql('s')
            . ' WHERE ' . amiga_lb_player_where_sql()
            . ' AND s.NumberGames >= ' . $minGames . ' '
            . 'ORDER BY win_rate_value DESC, p.id ASC LIMIT 1';
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('prepare amiga hof win rate holder: ' . $con->error);
        }
        $eventDate = $cutoff['event_date'];
        $chrono = (float) $cutoff['chrono'];
        $tournamentId = (int) $cutoff['tournament_id'];
        $stmt->bind_param('sdi', $eventDate, $chrono, $tournamentId);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('execute amiga hof win rate holder: ' . $err);
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : false;
        if ($res) {
            $res->free();
        }
        $stmt->close();
    }

    if (!is_array($row)) {
        return $empty;
    }

    return [
        'value' => $row['win_rate_value'] !== null ? (float) $row['win_rate_value'] : null,
        'player_id' => (int) $row['player_id'],
        'name' => (string) $row['name'],
    ];
}
