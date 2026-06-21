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
        'BiggestPeakRating',
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
        'BiggestPeakRatingID',
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
        'BiggestPeakRatingName',
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
        'BiggestPeakRatingDate',
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
        'GamesPlayed',
        'MostGamesInOneYear',
        'MostTournamentsInOneYear',
        'MostTournamentsPlayed',
        'MostTournamentWins',
        'MostWcPlayed',
        'MostCountriesPlayedIn',
        'MostOpponentCountriesFaced',
        'MostOpponentCountriesBeaten',
        'MostGamesInOneYearID',
        'MostTournamentsInOneYearID',
        'MostTournamentsPlayedID',
        'MostTournamentWinsID',
        'MostWcPlayedID',
        'MostCountriesPlayedInID',
        'MostOpponentCountriesFacedID',
        'MostOpponentCountriesBeatenID',
        'MostGamesInOneYearName',
        'MostTournamentsInOneYearName',
        'MostTournamentsPlayedName',
        'MostTournamentWinsName',
        'MostWcPlayedName',
        'MostCountriesPlayedInName',
        'MostOpponentCountriesFacedName',
        'MostOpponentCountriesBeatenName',
        'MostGamesInOneYearDate',
        'MostTournamentsInOneYearDate',
        'MostTournamentsPlayedDate',
        'MostTournamentWinsDate',
        'MostWcPlayedDate',
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
