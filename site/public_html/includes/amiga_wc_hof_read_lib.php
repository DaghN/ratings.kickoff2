<?php
/**
 * Amiga World Cup Hall of Fame - website read path (present + time travel).
 *
 * Sparse store: amiga_wc_hof_present (id=1) for now; amiga_wc_hof_snapshots
 * (per World Cup) for time travel. Mirrors amiga_realm_snapshot_read_lib.php.
 *
 * @see docs/amiga-wc-hof-policy.md
 * @see docs/amiga-time-travel-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';

/**
 * WC HoF payload columns (mirror scripts/amiga/wc_hof_columns.py
 * WC_HOF_PAYLOAD_COLUMNS - 122 columns; keep in sync with the manifest).
 *
 * @return list<string>
 */
function amiga_wc_hof_record_column_names(): array
{
    return [
        'MostWcPlayed',
        'MostWcGold',
        'MostWcGames',
        'MostWcWins',
        'MostWcPoints',
        'BestWcPtsPerGame',
        'BestWcWinRate',
        'MostWcGoalsFor',
        'BestWcGoalsForPerGame',
        'BestWcGoalsAgainstPerGame',
        'BestWcGoalDiffPerGame',
        'BestWcGoalRatio',
        'MostWcDoubleDigits',
        'BestWcDoubleDigitsRatio',
        'MostWcCleanSheets',
        'BestWcCleanSheetsRatio',
        'MostWcOpponents',
        'MostWcVictims',
        'MostWcDoubleDigitsVictims',
        'MostWcCleanSheetsVictims',
        'MostWcGoalsInOneGame',
        'BiggestWcWinDifference',
        'BiggestWcDrawSum',
        'BiggestWcSumOfGoals',
        'MostWcBestAttackAwards',
        'MostWcBestDefenseAwards',
        'BestSingleWcGoalsForPerGame',
        'BestSingleWcGoalsAgainstPerGame',
        'MostWcPlayedID',
        'MostWcGoldID',
        'MostWcGamesID',
        'MostWcWinsID',
        'MostWcPointsID',
        'BestWcPtsPerGameID',
        'BestWcWinRateID',
        'MostWcGoalsForID',
        'BestWcGoalsForPerGameID',
        'BestWcGoalsAgainstPerGameID',
        'BestWcGoalDiffPerGameID',
        'BestWcGoalRatioID',
        'MostWcDoubleDigitsID',
        'BestWcDoubleDigitsRatioID',
        'MostWcCleanSheetsID',
        'BestWcCleanSheetsRatioID',
        'MostWcOpponentsID',
        'MostWcVictimsID',
        'MostWcDoubleDigitsVictimsID',
        'MostWcCleanSheetsVictimsID',
        'MostWcGoalsInOneGameID',
        'BiggestWcWinDifferenceID',
        'BiggestWcDrawSumIDA',
        'BiggestWcDrawSumIDB',
        'BiggestWcSumOfGoalsIDA',
        'BiggestWcSumOfGoalsIDB',
        'MostWcBestAttackAwardsID',
        'MostWcBestDefenseAwardsID',
        'BestSingleWcGoalsForPerGameID',
        'BestSingleWcGoalsAgainstPerGameID',
        'MostWcPlayedName',
        'MostWcGoldName',
        'MostWcGamesName',
        'MostWcWinsName',
        'MostWcPointsName',
        'BestWcPtsPerGameName',
        'BestWcWinRateName',
        'MostWcGoalsForName',
        'BestWcGoalsForPerGameName',
        'BestWcGoalsAgainstPerGameName',
        'BestWcGoalDiffPerGameName',
        'BestWcGoalRatioName',
        'MostWcDoubleDigitsName',
        'BestWcDoubleDigitsRatioName',
        'MostWcCleanSheetsName',
        'BestWcCleanSheetsRatioName',
        'MostWcOpponentsName',
        'MostWcVictimsName',
        'MostWcDoubleDigitsVictimsName',
        'MostWcCleanSheetsVictimsName',
        'MostWcGoalsInOneGameName',
        'BiggestWcWinDifferenceName',
        'BiggestWcDrawSumNameA',
        'BiggestWcDrawSumNameB',
        'BiggestWcSumOfGoalsNameA',
        'BiggestWcSumOfGoalsNameB',
        'MostWcBestAttackAwardsName',
        'MostWcBestDefenseAwardsName',
        'BestSingleWcGoalsForPerGameName',
        'BestSingleWcGoalsAgainstPerGameName',
        'MostWcPlayedDate',
        'MostWcGoldDate',
        'MostWcGamesDate',
        'MostWcWinsDate',
        'MostWcPointsDate',
        'BestWcPtsPerGameDate',
        'BestWcWinRateDate',
        'MostWcGoalsForDate',
        'BestWcGoalsForPerGameDate',
        'BestWcGoalsAgainstPerGameDate',
        'BestWcGoalDiffPerGameDate',
        'BestWcGoalRatioDate',
        'MostWcDoubleDigitsDate',
        'BestWcDoubleDigitsRatioDate',
        'MostWcCleanSheetsDate',
        'BestWcCleanSheetsRatioDate',
        'MostWcOpponentsDate',
        'MostWcVictimsDate',
        'MostWcDoubleDigitsVictimsDate',
        'MostWcCleanSheetsVictimsDate',
        'MostWcGoalsInOneGameDate',
        'BiggestWcWinDifferenceDate',
        'BiggestWcDrawSumDate',
        'BiggestWcSumOfGoalsDate',
        'MostWcBestAttackAwardsDate',
        'MostWcBestDefenseAwardsDate',
        'BestSingleWcGoalsForPerGameDate',
        'BestSingleWcGoalsAgainstPerGameDate',
        'MostWcGoalsInOneGameGameID',
        'BiggestWcWinDifferenceGameID',
        'BiggestWcDrawSumGameID',
        'BiggestWcSumOfGoalsGameID',
        'BestSingleWcGoalsForPerGameTournamentID',
        'BestSingleWcGoalsAgainstPerGameTournamentID',
    ];
}

function amiga_wc_hof_sql_column_list(array $columns): string
{
    return implode(', ', array_map(static fn(string $c): string => '`' . $c . '`', $columns));
}

/**
 * Present-day WC HoF row (amiga_wc_hof_present id=1).
 *
 * @return array<string, mixed>|null
 */
function amiga_wc_hof_present_row(mysqli $con, ?array $columns = null): ?array
{
    $columns ??= amiga_wc_hof_record_column_names();
    $sql = 'SELECT ' . amiga_wc_hof_sql_column_list($columns)
        . ' FROM amiga_wc_hof_present WHERE id = 1 LIMIT 1';
    $result = mysqli_query($con, $sql);
    if (!$result) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);

    return is_array($row) ? $row : null;
}

/**
 * WC HoF snapshot at the time-travel cutoff (latest World Cup <= cutoff).
 *
 * @return array<string, mixed>|null
 */
function amiga_wc_hof_at_cutoff(
    mysqli $con,
    AmigaSnapshotContext $ctx,
    ?array $columns = null
): ?array {
    if (!$ctx->isActive()) {
        return amiga_wc_hof_present_row($con, $columns);
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return null;
    }

    $columns ??= amiga_wc_hof_record_column_names();
    $colSql = amiga_wc_hof_sql_column_list($columns);
    $eventDate = $cutoff['event_date'];
    $chrono = (float) $cutoff['chrono'];
    $tournamentId = (int) $cutoff['tournament_id'];

    // Latest World Cup snapshot at or before the cutoff (sparse - no exact match needed).
    $sql = 'SELECT ' . $colSql . ' FROM amiga_wc_hof_snapshots '
        . 'WHERE (event_date, event_chrono, tournament_id) <= (?, ?, ?) '
        . 'ORDER BY event_date DESC, event_chrono DESC, tournament_id DESC LIMIT 1';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return null;
    }
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
 * WC HoF records for the active context (present or time-travel cutoff).
 *
 * @return array<string, mixed>|null
 */
function amiga_wc_hof_records_load(mysqli $con, AmigaSnapshotContext $ctx): ?array
{
    if ($ctx->isActive()) {
        return amiga_wc_hof_at_cutoff($con, $ctx);
    }

    return amiga_wc_hof_present_row($con);
}

/**
 * Player holder ids from a WC HoF record row (for country-flag prefetch).
 *
 * Matches *ID / *IDA / *IDB player columns but excludes single-game *GameID
 * and *TournamentID anchors (those are game / tournament ids, not players).
 * Ratio-holder columns ending in *PerGameID are kept (real player holders).
 *
 * @param array<string, mixed> $records
 * @return list<int>
 */
function amiga_wc_hof_holder_ids_from_records(array $records): array
{
    $ids = [];
    foreach (amiga_wc_hof_record_column_names() as $column) {
        if (!preg_match('/ID[AB]?$/', $column)) {
            continue;
        }
        // Skip anchor columns (single-game / tournament ids), but keep the
        // *PerGameID ratio-holder player columns (e.g. BestWcGoalsForPerGameID).
        if (preg_match('/TournamentID$/', $column)) {
            continue;
        }
        if (preg_match('/(?<!Per)GameID$/', $column)) {
            continue;
        }
        $id = (int) ($records[$column] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}
