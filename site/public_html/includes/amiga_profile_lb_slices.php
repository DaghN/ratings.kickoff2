<?php
/**
 * Amiga profile — LB wing stat slices (label | value rows; present or cutoff snapshot).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/lb_column_help.php';
require_once __DIR__ . '/amiga_wc_lb_lib.php';
require_once __DIR__ . '/amiga_wc_podium_th.php';
require_once __DIR__ . '/amiga_profile_blocks.php';
require_once __DIR__ . '/amiga_lb_peak_rating_lib.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_snapshot_lib.php';
require_once __DIR__ . '/amiga_inverse_count_lib.php';

/**
 * Present-mode career row for profile LB slices (single indexed read).
 *
 * @return ?array<string, mixed>
 */
function amiga_profile_lb_slices_load_present(mysqli $con, int $playerId): ?array
{
    if ($playerId < 1) {
        return null;
    }

    $sql = 'SELECT p.id AS ID, p.name AS Name, p.country AS Country,'
        . ' s.Rating, s.NumberGames, s.NumberWins, s.NumberDraws, s.NumberLosses, s.AverageOpponentRating,'
        . ' s.GoalsFor, s.GoalsAgainst, s.AverageGoalsFor, s.AverageGoalsAgainst, s.GoalRatio,'
        . ' s.MostGoalsScored, s.MostGoalsConceded, s.BiggestWinDifference, s.BiggestLossDifference,'
        . ' s.BiggestSumOfGoals, s.BiggestDrawSum,'
        . ' s.DoubleDigits, s.CleanSheets, s.DoubleDigitsRatio, s.CleanSheetsRatio,'
        . ' s.DoubleDigitsConceded, s.CleanSheetsConceded, s.DoubleDigitsConcededRatio, s.CleanSheetsConcededRatio,'
        . ' s.DifferentOpponents, s.DifferentVictims, s.DoubleDigitsVictims, s.CleanSheetsVictims,'
        . ' s.MostGoalsConcededVictims, s.BiggestLossVictims, s.DifferentCulprits,'
        . ' s.DoubleDigitsCulprits, s.CleanSheetsCulprits, s.MostGoalsScoredCulprits, s.BiggestWinCulprits,'
        . ' s.tournaments_played, s.event_gold, s.event_silver, s.event_bronze, s.event_podiums, s.perfect_events,'
        . ' s.peak_year_games, s.peak_year_games_year, s.peak_year_tournaments, s.peak_year_tournaments_year,'
        . ' s.countries_played_in, s.opponent_countries_faced, s.opponent_countries_beaten, s.opponent_countries_beaten_by,'
        . ' s.PeakRating, s.LowestRating, s.HighestRatedVictim, s.LowestRatedCulprit,'
        . ' s.peak_rating_tournament_id, tpr.event_date AS peak_rating_date, peak_snap.rating_delta AS peak_rating_delta,'
        . ' tpr.name AS peak_rating_tournament_name,'
        . ' s.lowest_rating_tournament_id, tlow.event_date AS lowest_rating_date,'
        . ' s.peak_elo_rank, s.peak_elo_rank_tournament_id, tpke.event_date AS peak_elo_rank_date,'
        . ' tpke.name AS peak_elo_rank_tournament_name,'
        . ' (pr_rank_snap.player_id IS NOT NULL) AS peak_elo_rank_played_in_event,'
        . ' s.last_tournament_id, s.last_event_date,'
        . ' lt.name AS last_tournament_name, lt.country AS last_tournament_country'
        . ' FROM amiga_players p'
        . ' INNER JOIN amiga_player_current s ON s.player_id = p.id'
        . ' LEFT JOIN tournaments tpr ON tpr.id = s.peak_rating_tournament_id'
        . ' LEFT JOIN amiga_player_event_snapshots peak_snap'
        . '   ON peak_snap.player_id = p.id AND peak_snap.tournament_id = s.peak_rating_tournament_id'
        . ' LEFT JOIN tournaments tlow ON tlow.id = s.lowest_rating_tournament_id'
        . ' LEFT JOIN tournaments tpke ON tpke.id = s.peak_elo_rank_tournament_id'
        . ' LEFT JOIN amiga_player_event_snapshots pr_rank_snap'
        . '   ON pr_rank_snap.player_id = p.id AND pr_rank_snap.tournament_id = s.peak_elo_rank_tournament_id'
        . '  AND pr_rank_snap.NumberGames > 0'
        . ' LEFT JOIN tournaments lt ON lt.id = s.last_tournament_id'
        . ' WHERE p.id = ?'
        . ' LIMIT 1';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_profile_lb_slices_load_present: ' . $con->error);
    }
    $stmt->bind_param('i', $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga_profile_lb_slices_load_present: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : false;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    if ($row === false || $row === null) {
        return null;
    }

    amiga_profile_lb_slices_enrich_activity($con, $playerId, $row);

    amiga_profile_lb_slices_enrich_rating_lb_link_context($con, $playerId, $row);

    amiga_profile_lb_slices_enrich_goals_lb_link_context($playerId, $row);

    amiga_profile_lb_slices_enrich_double_digits_lb_link_context($playerId, $row);

    amiga_profile_lb_slices_enrich_calendar_geo_lb_link_context($playerId, $row);

    amiga_profile_lb_slices_enrich_peak_rating_lb_link_context($playerId, $row);

    amiga_inverse_count_overlay_row($con, $playerId, $row, null);

    return $row;
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slices_enrich_activity(mysqli $con, int $playerId, array &$row, ?AmigaSnapshotContext $ctx = null): void
{
    $ctx ??= amiga_snapshot_context_peek();
    $cutoff = $ctx instanceof AmigaSnapshotContext && $ctx->isActive() ? $ctx->cutoff() : null;

    if ($cutoff !== null) {
        $lastId = (int) ($row['tournament_id'] ?? 0);
        $row['activity_last_tournament'] = amiga_profile_lb_slices_tournament_event_from_id(
            $con,
            $lastId,
            (string) ($row['tournament_name'] ?? '')
        );
    } else {
        $lastId = (int) ($row['last_tournament_id'] ?? 0);
        if ($lastId > 0) {
            $row['activity_last_tournament'] = [
                'tournament_id' => $lastId,
                'name' => (string) ($row['last_tournament_name'] ?? ''),
                'country' => (string) ($row['last_tournament_country'] ?? ''),
                'event_date' => isset($row['last_event_date']) && $row['last_event_date'] !== null
                    ? (string) $row['last_event_date'] : null,
            ];
        } else {
            $row['activity_last_tournament'] = null;
        }
    }

    $row['activity_first_tournament'] = amiga_profile_lb_slices_fetch_boundary_event(
        $con,
        $playerId,
        false,
        false,
        $cutoff
    );
    $row['activity_last_world_cup'] = amiga_profile_lb_slices_fetch_boundary_event(
        $con,
        $playerId,
        true,
        true,
        $cutoff
    );
    $row['activity_first_world_cup'] = amiga_profile_lb_slices_fetch_boundary_event(
        $con,
        $playerId,
        false,
        true,
        $cutoff
    );
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slices_enrich_rating_lb_link_context(
    mysqli $con,
    int $playerId,
    array &$row,
    ?AmigaSnapshotContext $ctx = null
): void {
    require_once __DIR__ . '/amiga_lb_lib.php';

    $row['rating_lb_win_rate_sort_col'] = AMIGA_LB_RATING_COL_WIN_RATE;
    if ($playerId > 0) {
        $row['rating_lb_win_rate_href'] = amiga_lb_rating_win_rate_player_href($playerId);
        $row['rating_lb_opponent_avg_href'] = amiga_lb_rating_opponent_avg_player_href($playerId);
    }
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slices_enrich_goals_lb_link_context(int $playerId, array &$row): void
{
    if ($playerId < 1) {
        return;
    }

    require_once __DIR__ . '/amiga_lb_lib.php';

    $row['goals_lb_gf_href'] = amiga_lb_goals_player_href($playerId, 4);
    $row['goals_lb_ga_href'] = amiga_lb_goals_player_href($playerId, 5);
    $row['goals_lb_gd_href'] = amiga_lb_goals_player_href($playerId, 6);
    $row['goals_lb_gf_per_game_href'] = amiga_lb_goals_player_href($playerId, 7);
    $row['goals_lb_ga_per_game_href'] = amiga_lb_goals_player_href($playerId, 8, 'asc');
    $row['goals_lb_gd_per_game_href'] = amiga_lb_goals_player_href($playerId, 9);
    $row['goals_lb_ratio_href'] = amiga_lb_goals_player_href($playerId, 10);
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slices_enrich_double_digits_lb_link_context(int $playerId, array &$row): void
{
    if ($playerId < 1) {
        return;
    }

    require_once __DIR__ . '/amiga_lb_lib.php';

    $row['dd_lb_dd_ratio_href'] = amiga_lb_double_digits_player_href($playerId, 6);
    $row['dd_lb_cs_ratio_href'] = amiga_lb_double_digits_player_href($playerId, 7);
    $row['dd_lb_dd_conceded_ratio_href'] = amiga_lb_double_digits_player_href($playerId, 10, 'asc');
    $row['dd_lb_cs_conceded_ratio_href'] = amiga_lb_double_digits_player_href($playerId, 11, 'asc');
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slices_enrich_calendar_geo_lb_link_context(int $playerId, array &$row): void
{
    if ($playerId < 1) {
        return;
    }

    require_once __DIR__ . '/amiga_lb_lib.php';

    $row['calendar_geo_lb_peak_games_href'] = amiga_lb_calendar_geo_player_href($playerId, 3);
    $row['calendar_geo_lb_peak_events_href'] = amiga_lb_calendar_geo_player_href($playerId, 5);
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slices_enrich_peak_rating_lb_link_context(int $playerId, array &$row): void
{
    if ($playerId < 1) {
        return;
    }

    require_once __DIR__ . '/amiga_lb_lib.php';

    $row['peak_rating_lb_nadir_href'] = amiga_lb_peak_rating_player_href($playerId, 8);
}

/**
 * @return ?array{tournament_id: int, name: string, country: string, event_date: ?string}
 */
function amiga_profile_lb_slices_tournament_event_from_id(mysqli $con, int $tournamentId, string $fallbackName = ''): ?array
{
    if ($tournamentId < 1) {
        return null;
    }

    $stmt = $con->prepare('SELECT name, country, event_date FROM tournaments WHERE id = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_profile_lb_slices_tournament_event_from_id: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga_profile_lb_slices_tournament_event_from_id: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $tRow = $result ? $result->fetch_assoc() : false;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    if ($tRow === false || $tRow === null) {
        if ($fallbackName === '') {
            return null;
        }

        return [
            'tournament_id' => $tournamentId,
            'name' => $fallbackName,
            'country' => '',
            'event_date' => null,
        ];
    }

    $name = trim((string) ($tRow['name'] ?? ''));
    if ($name === '') {
        $name = $fallbackName;
    }

    $eventDate = $tRow['event_date'] ?? null;

    return [
        'tournament_id' => $tournamentId,
        'name' => $name,
        'country' => (string) ($tRow['country'] ?? ''),
        'event_date' => $eventDate !== null && $eventDate !== '' ? (string) $eventDate : null,
    ];
}

/**
 * First or last participated event (optionally World Cups only) at present or ≤ cutoff.
 *
 * @param ?array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 * @return ?array{tournament_id: int, name: string, country: string, event_date: ?string}
 */
function amiga_profile_lb_slices_fetch_boundary_event(
    mysqli $con,
    int $playerId,
    bool $last,
    bool $worldCupOnly,
    ?array $cutoff = null
): ?array {
    if ($playerId < 1) {
        return null;
    }

    $sql = 'SELECT snap.tournament_id,'
        . ' COALESCE(NULLIF(t.name, \'\'), snap.tournament_name) AS name,'
        . ' COALESCE(t.country, \'\') AS country,'
        . ' t.event_date AS event_date'
        . ' FROM amiga_player_event_snapshots snap'
        . ' INNER JOIN tournaments t ON t.id = snap.tournament_id'
        . ' WHERE snap.player_id = ? AND snap.NumberGames > 0';

    if ($worldCupOnly) {
        $sql .= ' AND t.is_world_cup = 1';
    }

    if ($cutoff !== null) {
        $sql .= ' AND (snap.event_date, snap.event_chrono, snap.tournament_id) <= (?, ?, ?)';
    }

    if ($last) {
        $sql .= ' ORDER BY snap.event_date DESC, snap.event_chrono DESC, snap.tournament_id DESC';
    } else {
        $sql .= ' ORDER BY snap.event_date ASC, snap.event_chrono ASC, snap.tournament_id ASC';
    }

    $sql .= ' LIMIT 1';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_profile_lb_slices_fetch_boundary_event: ' . $con->error);
    }

    if ($cutoff !== null) {
        $eventDate = (string) $cutoff['event_date'];
        $chrono = (float) $cutoff['chrono'];
        $tournamentId = (int) $cutoff['tournament_id'];
        $stmt->bind_param('isdi', $playerId, $eventDate, $chrono, $tournamentId);
    } else {
        $stmt->bind_param('i', $playerId);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga_profile_lb_slices_fetch_boundary_event: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : false;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    if ($row === false || $row === null) {
        return null;
    }

    $tid = (int) ($row['tournament_id'] ?? 0);
    if ($tid < 1) {
        return null;
    }

    $eventDate = $row['event_date'] ?? null;

    return [
        'tournament_id' => $tid,
        'name' => (string) ($row['name'] ?? ''),
        'country' => (string) ($row['country'] ?? ''),
        'event_date' => $eventDate !== null && $eventDate !== '' ? (string) $eventDate : null,
    ];
}

/**
 * Cutoff snapshot row shaped for profile LB slice renderers (PPT2–PPT4).
 *
 * @return ?array<string, mixed>
 */
function amiga_profile_lb_slices_load_at_cutoff(mysqli $con, int $playerId, AmigaSnapshotContext $ctx): ?array
{
    $snap = amiga_player_snapshot_row_at_cutoff($con, $playerId, $ctx);
    if ($snap === null || (int) ($snap['NumberGames'] ?? 0) <= 0) {
        return null;
    }

    $identity = amiga_profile_lb_slices_player_identity($con, $playerId);
    if ($identity === null) {
        return null;
    }

    $row = $snap;
    $row['ID'] = $playerId;
    $row['Name'] = $identity['Name'];
    $row['Country'] = $identity['Country'];

    amiga_profile_lb_slices_enrich_peak_context($con, $playerId, $row, $ctx);
    amiga_profile_lb_slices_enrich_activity($con, $playerId, $row, $ctx);
    amiga_profile_lb_slices_enrich_rating_lb_link_context($con, $playerId, $row, $ctx);
    amiga_profile_lb_slices_enrich_goals_lb_link_context($playerId, $row);
    amiga_profile_lb_slices_enrich_double_digits_lb_link_context($playerId, $row);

    amiga_profile_lb_slices_enrich_calendar_geo_lb_link_context($playerId, $row);

    amiga_profile_lb_slices_enrich_peak_rating_lb_link_context($playerId, $row);

    amiga_inverse_count_overlay_row($con, $playerId, $row, $ctx);

    return $row;
}

/**
 * @return ?array{Name: string, Country: string}
 */
function amiga_profile_lb_slices_player_identity(mysqli $con, int $playerId): ?array
{
    $stmt = $con->prepare('SELECT name, country FROM amiga_players WHERE id = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_profile_lb_slices_player_identity: ' . $con->error);
    }
    $stmt->bind_param('i', $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga_profile_lb_slices_player_identity: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : false;
    if ($result) {
        $result->free();
    }
    $stmt->close();
    if ($row === false || $row === null) {
        return null;
    }

    return [
        'Name' => (string) ($row['name'] ?? ''),
        'Country' => (string) ($row['country'] ?? ''),
    ];
}

/**
 * Peak-rating subsection joins — tournament names/dates from snapshot IDs at cutoff.
 *
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slices_enrich_peak_context(
    mysqli $con,
    int $playerId,
    array &$row,
    AmigaSnapshotContext $ctx
): void {
    $peakRatingTournamentId = (int) ($row['peak_rating_tournament_id'] ?? 0);
    if ($peakRatingTournamentId > 0) {
        $sql = 'SELECT tpr.event_date AS peak_rating_date, tpr.name AS peak_rating_tournament_name,'
            . ' peak_snap.rating_delta AS peak_rating_delta'
            . ' FROM tournaments tpr'
            . ' LEFT JOIN amiga_player_event_snapshots peak_snap'
            . '   ON peak_snap.player_id = ? AND peak_snap.tournament_id = tpr.id'
            . ' WHERE tpr.id = ?'
            . ' LIMIT 1';
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('prepare amiga_profile_lb_slices_enrich_peak_context rating: ' . $con->error);
        }
        $stmt->bind_param('ii', $playerId, $peakRatingTournamentId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute amiga_profile_lb_slices_enrich_peak_context rating: ' . $stmt->error);
        }
        $result = $stmt->get_result();
        $peakRow = $result ? $result->fetch_assoc() : false;
        if ($result) {
            $result->free();
        }
        $stmt->close();
        if ($peakRow !== false && $peakRow !== null) {
            $row['peak_rating_date'] = $peakRow['peak_rating_date'] ?? null;
            $row['peak_rating_tournament_name'] = $peakRow['peak_rating_tournament_name'] ?? null;
            $row['peak_rating_delta'] = $peakRow['peak_rating_delta'] ?? null;
        }
    } else {
        $row['peak_rating_date'] = null;
        $row['peak_rating_tournament_name'] = null;
        $row['peak_rating_delta'] = null;
    }

    $lowestRatingTournamentId = (int) ($row['lowest_rating_tournament_id'] ?? 0);
    if ($lowestRatingTournamentId > 0) {
        $nadirEvent = amiga_profile_lb_slices_tournament_event_from_id($con, $lowestRatingTournamentId);
        $row['lowest_rating_date'] = $nadirEvent['event_date'] ?? null;
    } else {
        $row['lowest_rating_date'] = null;
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        $row['peak_elo_rank'] = null;
        $row['peak_elo_rank_tournament_id'] = null;
        $row['peak_elo_rank_date'] = null;
        $row['peak_elo_rank_tournament_name'] = null;
        $row['peak_elo_rank_played_in_event'] = 0;

        return;
    }

    $cutoffTournamentId = (int) $cutoff['tournament_id'];
    $sql = 'SELECT er.peak_elo_rank, er.peak_elo_rank_tournament_id,'
        . ' tpke.name AS peak_elo_rank_tournament_name, tpke.event_date AS peak_elo_rank_date'
        . ' FROM amiga_player_elo_rank_at_event er'
        . ' LEFT JOIN tournaments tpke ON tpke.id = er.peak_elo_rank_tournament_id'
        . ' WHERE er.player_id = ? AND er.tournament_id = ?'
        . ' LIMIT 1';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_profile_lb_slices_enrich_peak_context rank: ' . $con->error);
    }
    $stmt->bind_param('ii', $playerId, $cutoffTournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga_profile_lb_slices_enrich_peak_context rank: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $rankRow = $result ? $result->fetch_assoc() : false;
    if ($result) {
        $result->free();
    }
    $stmt->close();

    if ($rankRow === false || $rankRow === null) {
        $row['peak_elo_rank'] = null;
        $row['peak_elo_rank_tournament_id'] = null;
        $row['peak_elo_rank_date'] = null;
        $row['peak_elo_rank_tournament_name'] = null;
        $row['peak_elo_rank_played_in_event'] = 0;

        return;
    }

    $row['peak_elo_rank'] = $rankRow['peak_elo_rank'] ?? null;
    $row['peak_elo_rank_tournament_id'] = $rankRow['peak_elo_rank_tournament_id'] ?? null;
    $row['peak_elo_rank_date'] = $rankRow['peak_elo_rank_date'] ?? null;
    $row['peak_elo_rank_tournament_name'] = $rankRow['peak_elo_rank_tournament_name'] ?? null;

    $peakRankTournamentId = (int) ($row['peak_elo_rank_tournament_id'] ?? 0);
    $row['peak_elo_rank_played_in_event'] = $peakRankTournamentId > 0
        && amiga_player_peak_rank_played_in_event($con, $playerId, $peakRankTournamentId)
        ? 1
        : 0;
}

/**
 * Present or cutoff career row for profile LB mosaic (context from amiga_player_load).
 *
 * @return ?array<string, mixed>
 */
function amiga_profile_lb_slices_load(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): ?array
{
    if ($playerId < 1) {
        return null;
    }

    $ctx ??= amiga_snapshot_context_peek();
    if ($ctx instanceof AmigaSnapshotContext && $ctx->isActive()) {
        return amiga_profile_lb_slices_load_at_cutoff($con, $playerId, $ctx);
    }

    return amiga_profile_lb_slices_load_present($con, $playerId);
}

function amiga_profile_lb_slice_label(
    string $labelHtml,
    ?string $help = null,
    ?string $tooltipLabel = null,
    string $extraClass = ''
): string {
    $classes = [];
    if ($extraClass !== '') {
        $classes[] = $extraClass;
    }
    if ($help !== null && $help !== '') {
        $classes[] = 'k2-table-helped';
    }
    $attrs = '';
    if ($classes !== []) {
        $attrs .= ' class="' . htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') . '"';
    }
    if ($help !== null && $help !== '') {
        $attrs .= ' data-k2-help="' . htmlspecialchars($help, ENT_QUOTES, 'UTF-8') . '" tabindex="0"';
    }
    if ($tooltipLabel !== null && $tooltipLabel !== '') {
        $attrs .= ' data-k2-tooltip-label="' . htmlspecialchars($tooltipLabel, ENT_QUOTES, 'UTF-8') . '"';
    }

    return '<td' . $attrs . '>' . $labelHtml . '</td>';
}

function amiga_profile_lb_slice_value(string $html, string $extraClass = ''): string
{
    $class = 'k2-table-cell--right';
    if ($extraClass !== '') {
        $class .= ' ' . $extraClass;
    }

    return '<td class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '">' . $html . '</td>';
}

function amiga_profile_lb_slice_row(
    string $labelHtml,
    string $valueHtml,
    ?string $help = null,
    ?string $tooltipLabel = null,
    string $labelClass = '',
    string $valueClass = ''
): string {
    return '<tr>'
        . amiga_profile_lb_slice_label($labelHtml, $help, $tooltipLabel, $labelClass)
        . amiga_profile_lb_slice_value($valueHtml, $valueClass)
        . '</tr>';
}

function amiga_profile_lb_slice_render_section_table(string $title, callable $renderRows): void
{
    $safe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo '<div class="k2-amiga-profile-lb-slice-block server-records-panel">';
    echo '<h3 class="k2-panel-heading">' . $safe . '</h3>';
    echo '<div class="k2-table-wrap">';
    ?>
<table class="k2-table server-records-table k2-table--calm-stats" data-k2-anchor-col="1">
<tbody class="black">
<?php
    $renderRows();
    ?>
</tbody>
</table>
<?php
    echo "</div></div>\n";
}

/**
 * Seven LB-wing sections keyed for column placement.
 *
 * @return array<string, array{title: string, render: callable(): void}>
 */
function amiga_profile_lb_slice_sections(array $row): array
{
    return [
        'activity' => [
            'title' => 'Activity',
            'render' => static fn () => amiga_profile_lb_slice_rows_activity($row),
        ],
        'results' => [
            'title' => 'Results',
            'render' => static fn () => amiga_profile_lb_slice_rows_rating($row),
        ],
        'goals' => [
            'title' => 'Goals',
            'render' => static fn () => amiga_profile_lb_slice_rows_goals($row),
        ],
        'double-digits' => [
            'title' => 'Double Digits & Clean Sheets',
            'render' => static fn () => amiga_profile_lb_slice_rows_double_digits($row),
        ],
        'victims' => [
            'title' => 'Victims & Culprits',
            'render' => static fn () => amiga_profile_lb_slice_rows_victims($row),
        ],
        'honours' => [
            'title' => 'Tournament honours',
            'render' => static fn () => amiga_profile_lb_slice_rows_tournament_honours($row),
        ],
        'calendar' => [
            'title' => 'Calendar & geography',
            'render' => static fn () => amiga_profile_lb_slice_rows_calendar_geo($row),
        ],
        'peak' => [
            'title' => 'Peak rating',
            'render' => static fn () => amiga_profile_lb_slice_rows_peak_rating($row),
        ],
    ];
}

/**
 * Balanced three-column plan (~18 / ~19 / ~21 data rows).
 *
 * @return array<int, array<int, string>>
 */
function amiga_profile_lb_slice_column_plan(): array
{
    return [
        ['activity', 'results', 'goals'],
        ['double-digits', 'victims'],
        ['honours', 'calendar', 'peak'],
    ];
}

/**
 * @param array<string, array{title: string, render: callable(): void}> $sectionsByKey
 * @param array<int, array<int, string>> $columnPlan
 */
function amiga_profile_lb_slice_render_columns(array $sectionsByKey, array $columnPlan): void
{
    echo '<div class="k2-amiga-profile-lb-slices__grid">';
    foreach ($columnPlan as $columnKeys) {
        echo '<div class="k2-amiga-profile-lb-slices__col">';
        foreach ($columnKeys as $key) {
            if (!isset($sectionsByKey[$key])) {
                continue;
            }
            $section = $sectionsByKey[$key];
            amiga_profile_lb_slice_render_section_table($section['title'], $section['render']);
        }
        echo '</div>';
    }
    echo "</div>\n";
}

/**
 * @param ?array{tournament_id: int, name: string, country: string, event_date: ?string} $event
 */
function amiga_profile_lb_slice_tournament_value(?array $event): string
{
    if ($event === null || (int) ($event['tournament_id'] ?? 0) < 1) {
        return k2_fmt_dash();
    }

    $name = trim((string) ($event['name'] ?? ''));
    if ($name === '') {
        return k2_fmt_dash();
    }

    return k2_amiga_lb_tournament_cell(
        (int) $event['tournament_id'],
        $name,
        (string) ($event['country'] ?? '')
    );
}

/**
 * Primary value on first line, optional muted secondary line below (Activity tournament dates).
 */
function amiga_profile_lb_slice_value_stacked(string $primaryHtml, ?string $mutedHtml = null): string
{
    $html = '<span class="k2-amiga-profile-activity-value k2-amiga-profile-activity-value--stacked">'
        . '<span class="k2-amiga-profile-activity-event">' . $primaryHtml . '</span>';
    if ($mutedHtml !== null && $mutedHtml !== '' && $mutedHtml !== '—') {
        $html .= '<span class="k2-amiga-profile-activity-date">' . $mutedHtml . '</span>';
    }
    $html .= '</span>';

    return $html;
}

/**
 * One line: muted prefix then value (Calendar peak year; Peak rating event dates).
 */
function amiga_profile_lb_slice_value_inline_muted_prefix(string $valueHtml, ?string $mutedPrefixHtml = null): string
{
    if ($mutedPrefixHtml === null || $mutedPrefixHtml === '' || $mutedPrefixHtml === '—') {
        return $valueHtml;
    }

    return '<span class="k2-amiga-profile-activity-value k2-amiga-profile-activity-value--inline">'
        . '<span class="k2-amiga-profile-activity-date">' . $mutedPrefixHtml . '</span>'
        . '<span class="k2-amiga-profile-activity-event">' . $valueHtml . '</span>'
        . '</span>';
}

/**
 * Tournament link on first line, full event date muted below.
 *
 * @param ?array{tournament_id: int, name: string, country: string, event_date: ?string} $event
 */
function amiga_profile_lb_slice_tournament_value_stacked(?array $event): string
{
    $link = amiga_profile_lb_slice_tournament_value($event);
    if ($link === k2_fmt_dash()) {
        return $link;
    }
    $dateHtml = amiga_profile_format_event_date($event['event_date'] ?? null);
    $muted = $dateHtml === '—' ? null : $dateHtml;

    return amiga_profile_lb_slice_value_stacked($link, $muted);
}

/**
 * Muted event date line for stacked profile slice values (— → omit).
 */
function amiga_profile_lb_slice_event_date_stacked_muted(mixed $eventDate): ?string
{
    $formatted = amiga_profile_format_event_date($eventDate);

    return $formatted === '—' ? null : $formatted;
}

function amiga_profile_lb_slice_player_games_href(
    int $playerId,
    string $resultFilter = 'all',
    ?string $sortKey = null,
    ?string $sortDir = null,
    int $heroGfMin = -1,
    int $heroGfMax = -1,
    int $heroGaMin = -1,
    int $heroGaMax = -1,
): string {
    require_once __DIR__ . '/amiga_player_games_lib.php';

    if ($playerId < 1) {
        return '';
    }

    $params = ['id' => $playerId];
    $resultFilter = amiga_games_valid_result($resultFilter);
    if ($resultFilter !== 'all') {
        $params['result'] = $resultFilter;
    }
    if ($sortKey !== null && $sortKey !== '') {
        $params['sort'] = $sortKey === 'for' ? 'goals_for' : $sortKey;
    }
    if ($sortDir !== null && $sortDir !== '') {
        $params['dir'] = amiga_games_valid_direction($sortDir);
    }
    if ($heroGfMin >= 0) {
        $params['gf_min'] = amiga_games_valid_hero_goals_bound($heroGfMin);
    }
    if ($heroGfMax >= 0) {
        $params['gf_max'] = amiga_games_valid_hero_goals_bound($heroGfMax);
    }
    if ($heroGaMin >= 0) {
        $params['ga_min'] = amiga_games_valid_hero_goals_bound($heroGaMin);
    }
    if ($heroGaMax >= 0) {
        $params['ga_max'] = amiga_games_valid_hero_goals_bound($heroGaMax);
    }

    return amiga_games_build_url($params) . k2_player_matching_games_anchor_fragment();
}

function amiga_profile_lb_slice_player_tournaments_href(
    int $playerId,
    string $eventFilter = 'all',
    string $perfectFilter = '',
    string $winnerFilter = '',
    string $podiumFilter = '',
    int $finishFilter = 0,
): string {
    require_once __DIR__ . '/amiga_player_tournament_lib.php';

    if ($playerId < 1) {
        return '';
    }

    return amiga_player_tournaments_filter_url(
        $playerId,
        $eventFilter,
        '',
        0,
        $perfectFilter,
        $winnerFilter,
        $podiumFilter,
        $finishFilter,
    ) . amiga_player_tournaments_table_anchor_fragment();
}

function amiga_profile_lb_slice_games_score_inventory_link_html(
    string $countDisplay,
    string $displayHtml,
    int $playerId,
    int $games,
    int $heroGfMin = -1,
    int $heroGfMax = -1,
    int $heroGaMin = -1,
    int $heroGaMax = -1,
): string {
    $href = '';
    if ($playerId > 0 && k2_derived_games_started($games) && $countDisplay !== '—' && $countDisplay !== '-') {
        $href = amiga_profile_lb_slice_player_games_href(
            $playerId,
            'all',
            null,
            null,
            $heroGfMin,
            $heroGfMax,
            $heroGaMin,
            $heroGaMax
        );
    }

    return amiga_profile_lb_slice_link_star_value_html($displayHtml, $href);
}

function amiga_profile_lb_slice_games_inventory_link(
    string $display,
    int $playerId,
    int $games,
    string $resultFilter = 'all',
    ?string $sortKey = null,
    ?string $sortDir = null,
): string {
    $href = '';
    if ($playerId > 0 && k2_derived_games_started($games) && $display !== '—' && $display !== '-') {
        $href = amiga_profile_lb_slice_player_games_href($playerId, $resultFilter, $sortKey, $sortDir);
    }

    return amiga_profile_lb_slice_link_star_value($display, $href);
}

function amiga_profile_lb_slice_link_star_value(string $display, string $href): string
{
    if ($href === '' || $display === '—' || $display === '-') {
        return $display;
    }

    return '<a class="k2-link-star" href="' . k2_h($href) . '">' . k2_h($display) . '</a>';
}

function amiga_profile_lb_slice_link_star_value_html(string $displayHtml, string $href): string
{
    if ($href === '' || $displayHtml === '—' || $displayHtml === '-') {
        return $displayHtml;
    }

    return '<a class="k2-link-star" href="' . k2_h($href) . '">' . $displayHtml . '</a>';
}

function amiga_profile_lb_slice_link_star_value_stacked_html(
    string $primaryHtml,
    ?string $mutedHtml,
    string $href
): string {
    $primary = amiga_profile_lb_slice_link_star_value_html($primaryHtml, $href);

    return amiga_profile_lb_slice_value_stacked($primary, $mutedHtml);
}

function amiga_profile_lb_slice_link_star_value_inline_muted_prefix_html(
    string $primaryHtml,
    ?string $mutedPrefixHtml,
    string $href
): string {
    $primary = amiga_profile_lb_slice_link_star_value_html($primaryHtml, $href);

    return amiga_profile_lb_slice_value_inline_muted_prefix($primary, $mutedPrefixHtml);
}

function amiga_profile_lb_slice_link_star_value_stacked(
    string $primaryDisplay,
    ?string $mutedHtml,
    string $href
): string {
    return amiga_profile_lb_slice_value_stacked(
        amiga_profile_lb_slice_link_star_value($primaryDisplay, $href),
        $mutedHtml
    );
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_activity(array $row): void
{
    $valueClass = 'k2-amiga-profile-activity-value-cell--stacked';

    echo amiga_profile_lb_slice_row(
        'Last tournament',
        amiga_profile_lb_slice_tournament_value_stacked($row['activity_last_tournament'] ?? null),
        null,
        null,
        '',
        $valueClass
    );
    echo amiga_profile_lb_slice_row(
        'First tournament',
        amiga_profile_lb_slice_tournament_value_stacked($row['activity_first_tournament'] ?? null),
        null,
        null,
        '',
        $valueClass
    );
    echo amiga_profile_lb_slice_row(
        'Last World Cup',
        amiga_profile_lb_slice_tournament_value_stacked($row['activity_last_world_cup'] ?? null),
        null,
        null,
        '',
        $valueClass
    );
    echo amiga_profile_lb_slice_row(
        'First World Cup',
        amiga_profile_lb_slice_tournament_value_stacked($row['activity_first_world_cup'] ?? null),
        null,
        null,
        '',
        $valueClass
    );
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_rating(array $row): void
{
    $games = (int) ($row['NumberGames'] ?? 0);
    $wins = (int) ($row['NumberWins'] ?? 0);
    $draws = (int) ($row['NumberDraws'] ?? 0);
    $winRate = amiga_wc_lb_win_rate($wins, $draws, $games);

    $playerId = (int) ($row['ID'] ?? 0);
    $gamesValue = amiga_profile_lb_slice_link_star_value(
        k2_fmt_games_played($games),
        amiga_profile_lb_slice_player_games_href($playerId)
    );
    echo amiga_profile_lb_slice_row('Games', $gamesValue, k2_lb_help_games());
    $winsDisplay = k2_fmt_wdl_count($row['NumberWins'] ?? null, $games, 'win');
    $winsValue = amiga_profile_lb_slice_link_star_value_html(
        $winsDisplay,
        amiga_profile_lb_slice_player_games_href($playerId, 'win')
    );
    echo amiga_profile_lb_slice_row('Wins', $winsValue);
    $drawsValue = amiga_profile_lb_slice_link_star_value(
        k2_fmt_count($row['NumberDraws'] ?? null, $games),
        amiga_profile_lb_slice_player_games_href($playerId, 'draw')
    );
    echo amiga_profile_lb_slice_row('Draws', $drawsValue);
    $lossesDisplay = k2_fmt_wdl_count($row['NumberLosses'] ?? null, $games, 'loss');
    $lossesValue = amiga_profile_lb_slice_link_star_value_html(
        $lossesDisplay,
        amiga_profile_lb_slice_player_games_href($playerId, 'loss')
    );
    echo amiga_profile_lb_slice_row('Losses', $lossesValue);
    $winRateDisplay = k2_fmt_pct_from_ratio($winRate, $games);
    $winRateHref = '';
    if ($playerId > 0 && k2_derived_games_started($games) && $winRateDisplay !== '-') {
        $winRateHref = (string) ($row['rating_lb_win_rate_href'] ?? '');
    }
    $winRateValue = amiga_profile_lb_slice_link_star_value($winRateDisplay, $winRateHref);
    echo amiga_profile_lb_slice_row('Win rate', $winRateValue, k2_lb_help_amiga_wc_win_rate());
    $oppAvgDisplay = k2_fmt_lb_stat($row['AverageOpponentRating'] ?? null, $games);
    $oppAvgHref = '';
    if ($playerId > 0 && k2_derived_games_started($games) && $oppAvgDisplay !== '-') {
        $oppAvgHref = (string) ($row['rating_lb_opponent_avg_href'] ?? '');
    }
    $oppAvgValue = amiga_profile_lb_slice_link_star_value($oppAvgDisplay, $oppAvgHref);
    echo amiga_profile_lb_slice_row(
        'Opponent Average',
        $oppAvgValue,
        k2_lb_help_opponent_avg(),
        'Opponent Average'
    );
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_goals(array $row): void
{
    $games = (int) ($row['NumberGames'] ?? 0);
    $playerId = (int) ($row['ID'] ?? 0);
    $gd = k2_derived_games_started($games)
        ? (int) ($row['GoalsFor'] ?? 0) - (int) ($row['GoalsAgainst'] ?? 0)
        : null;
    $gdPer = $gd !== null ? $gd / $games : null;

    if (!k2_derived_games_started($games)) {
        $ratioCell = k2_fmt_dash();
    } elseif (k2_db_is_null($row['GoalRatio'] ?? null) || (float) ($row['GoalRatio'] ?? 0) == -1.0) {
        $ratioCell = k2_fmt_dash();
    } else {
        $ratioCell = k2_fmt_decimal($row['GoalRatio'], $games);
    }

    if (!k2_derived_games_started($games) || (int) ($row['NumberDraws'] ?? 0) === 0) {
        $drawCell = k2_fmt_dash();
    } else {
        $drawSum = k2_db_is_null($row['BiggestDrawSum'] ?? null) ? 0 : (int) $row['BiggestDrawSum'];
        $half = (int) ($drawSum / 2);
        $drawCell = $half . '-' . $half;
    }

    $goalsLbLink = static function (string $display, string $hrefKey) use ($row, $playerId, $games): string {
        $href = '';
        if ($playerId > 0 && k2_derived_games_started($games) && $display !== '-') {
            $href = (string) ($row[$hrefKey] ?? '');
        }

        return amiga_profile_lb_slice_link_star_value_html($display, $href);
    };

    echo amiga_profile_lb_slice_row(
        'Goals scored',
        $goalsLbLink('<span class="blue">' . k2_fmt_count($row['GoalsFor'] ?? null, $games) . '</span>', 'goals_lb_gf_href'),
        k2_lb_help_amiga_goals_scored()
    );
    echo amiga_profile_lb_slice_row(
        'Goals conceded',
        $goalsLbLink('<span class="red">' . k2_fmt_count($row['GoalsAgainst'] ?? null, $games) . '</span>', 'goals_lb_ga_href'),
        k2_lb_help_amiga_goals_conceded()
    );
    $gdDisplay = $gd !== null ? k2_fmt_signed_int_text($gd) : k2_fmt_dash();
    echo amiga_profile_lb_slice_row(
        'Goal difference',
        $goalsLbLink($gdDisplay, 'goals_lb_gd_href'),
        k2_lb_help_amiga_goal_difference()
    );
    echo amiga_profile_lb_slice_row(
        'Goals scored per game',
        $goalsLbLink(k2_fmt_decimal($row['AverageGoalsFor'] ?? null, $games), 'goals_lb_gf_per_game_href'),
        k2_lb_help_amiga_goals_scored_avg()
    );
    echo amiga_profile_lb_slice_row(
        'Goals conceded per game',
        $goalsLbLink(k2_fmt_decimal($row['AverageGoalsAgainst'] ?? null, $games), 'goals_lb_ga_per_game_href'),
        k2_lb_help_amiga_goals_conceded_avg()
    );
    $gdDisplay = $gdPer !== null ? k2_fmt_decimal($gdPer, $games) : k2_fmt_dash();
    echo amiga_profile_lb_slice_row(
        'Goal difference per game',
        $goalsLbLink($gdDisplay, 'goals_lb_gd_per_game_href'),
        k2_lb_help_amiga_goal_difference_per_game()
    );
    echo amiga_profile_lb_slice_row(
        'Goal ratio',
        $goalsLbLink($ratioCell, 'goals_lb_ratio_href'),
        k2_lb_help_goal_ratio()
    );
    $maxGfDisplay = k2_fmt_count($row['MostGoalsScored'] ?? null, $games);
    echo amiga_profile_lb_slice_row(
        'Most goals scored',
        amiga_profile_lb_slice_games_inventory_link($maxGfDisplay, $playerId, $games, 'all', 'goals_for', 'desc'),
        k2_lb_help_amiga_most_scored()
    );
    $maxGaDisplay = k2_fmt_count($row['MostGoalsConceded'] ?? null, $games);
    echo amiga_profile_lb_slice_row(
        'Most goals conceded',
        amiga_profile_lb_slice_games_inventory_link($maxGaDisplay, $playerId, $games, 'all', 'against', 'desc'),
        k2_lb_help_amiga_most_conceded()
    );
    $maxWinDisplay = k2_fmt_count($row['BiggestWinDifference'] ?? null, $games);
    echo amiga_profile_lb_slice_row(
        'Biggest winning margin',
        amiga_profile_lb_slice_games_inventory_link($maxWinDisplay, $playerId, $games, 'win', 'diff', 'desc'),
        k2_lb_help_win_margin()
    );
    $maxLossDisplay = k2_fmt_count($row['BiggestLossDifference'] ?? null, $games);
    echo amiga_profile_lb_slice_row(
        'Biggest losing margin',
        amiga_profile_lb_slice_games_inventory_link($maxLossDisplay, $playerId, $games, 'loss', 'diff', 'asc'),
        k2_lb_help_loss_margin()
    );
    $maxSumDisplay = k2_fmt_count($row['BiggestSumOfGoals'] ?? null, $games);
    echo amiga_profile_lb_slice_row(
        'Highest total goals',
        amiga_profile_lb_slice_games_inventory_link($maxSumDisplay, $playerId, $games, 'all', 'sum', 'desc'),
        k2_lb_help_goal_sum()
    );
    echo amiga_profile_lb_slice_row(
        'Biggest draw',
        amiga_profile_lb_slice_games_inventory_link($drawCell, $playerId, $games, 'draw', 'sum', 'desc'),
        k2_lb_help_biggest_draw()
    );
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_double_digits(array $row): void
{
    $games = (int) ($row['NumberGames'] ?? 0);
    $playerId = (int) ($row['ID'] ?? 0);
    require_once __DIR__ . '/amiga_player_games_lib.php';

    $ddCountDisplay = k2_fmt_count($row['DoubleDigits'] ?? null, $games);
    echo amiga_profile_lb_slice_row(
        'Double Digits',
        amiga_profile_lb_slice_games_score_inventory_link_html(
            $ddCountDisplay,
            '<span class="blue">' . $ddCountDisplay . '</span>',
            $playerId,
            $games,
            AMIGA_PLAYER_GAMES_DOUBLE_DIGITS_GF_MIN
        ),
        k2_lb_help_double_digits()
    );
    $csCountDisplay = k2_fmt_count($row['CleanSheets'] ?? null, $games);
    echo amiga_profile_lb_slice_row(
        'Clean Sheets',
        amiga_profile_lb_slice_games_score_inventory_link_html(
            $csCountDisplay,
            $csCountDisplay,
            $playerId,
            $games,
            -1,
            -1,
            -1,
            0
        ),
        k2_lb_help_clean_sheets()
    );
    $ddLbRatioLink = static function (string $display, string $hrefKey) use ($row, $playerId, $games): string {
        $href = '';
        if ($playerId > 0 && k2_derived_games_started($games) && $display !== '-') {
            $href = (string) ($row[$hrefKey] ?? '');
        }

        return amiga_profile_lb_slice_link_star_value($display, $href);
    };
    echo amiga_profile_lb_slice_row(
        'Double Digits ratio',
        $ddLbRatioLink(
            k2_fmt_pct_from_ratio($row['DoubleDigitsRatio'] ?? null, $games),
            'dd_lb_dd_ratio_href'
        ),
        k2_lb_help_double_digits_ratio()
    );
    echo amiga_profile_lb_slice_row(
        'Clean Sheets ratio',
        $ddLbRatioLink(
            k2_fmt_pct_from_ratio($row['CleanSheetsRatio'] ?? null, $games),
            'dd_lb_cs_ratio_href'
        ),
        k2_lb_help_clean_sheets_ratio()
    );
    $ddConcededDisplay = k2_fmt_count($row['DoubleDigitsConceded'] ?? null, $games);
    echo amiga_profile_lb_slice_row(
        'DD conceded',
        amiga_profile_lb_slice_games_score_inventory_link_html(
            $ddConcededDisplay,
            '<span class="red">' . $ddConcededDisplay . '</span>',
            $playerId,
            $games,
            -1,
            -1,
            AMIGA_PLAYER_GAMES_DOUBLE_DIGITS_GA_MIN
        ),
        k2_lb_help_double_digits_conceded()
    );
    $csConcededDisplay = k2_fmt_count($row['CleanSheetsConceded'] ?? null, $games);
    echo amiga_profile_lb_slice_row(
        'CS conceded',
        amiga_profile_lb_slice_games_score_inventory_link_html(
            $csConcededDisplay,
            $csConcededDisplay,
            $playerId,
            $games,
            -1,
            0
        ),
        k2_lb_help_clean_sheets_conceded()
    );
    echo amiga_profile_lb_slice_row(
        'DD conceded ratio',
        $ddLbRatioLink(
            k2_fmt_pct_from_ratio($row['DoubleDigitsConcededRatio'] ?? null, $games),
            'dd_lb_dd_conceded_ratio_href'
        ),
        k2_lb_help_double_digits_conceded_ratio()
    );
    echo amiga_profile_lb_slice_row(
        'CS conceded ratio',
        $ddLbRatioLink(
            k2_fmt_pct_from_ratio($row['CleanSheetsConcededRatio'] ?? null, $games),
            'dd_lb_cs_conceded_ratio_href'
        ),
        k2_lb_help_clean_sheets_conceded_ratio()
    );
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_victims(array $row): void
{
    $games = (int) ($row['NumberGames'] ?? 0);
    $playerId = (int) ($row['ID'] ?? 0);
    $opponents = (int) ($row['DifferentOpponents'] ?? 0);
    require_once __DIR__ . '/amiga_player_chronologies_lib.php';
    $opponentsDisplay = '<span class="blue">' . k2_fmt_count($row['DifferentOpponents'] ?? null, $games) . '</span>';
    $opponentsValue = amiga_profile_lb_slice_link_star_value_html(
        $opponentsDisplay,
        $playerId > 0 && $opponents > 0 ? amiga_player_chronology_opponents_entry_href($playerId) : ''
    );

    echo amiga_profile_lb_slice_row('Opponents', $opponentsValue, k2_lb_help_opponents());
    $victims = (int) ($row['DifferentVictims'] ?? 0);
    $victimsDisplay = '<span class="blue">' . k2_fmt_count($row['DifferentVictims'] ?? null, $games) . '</span>';
    $victimsValue = amiga_profile_lb_slice_link_star_value_html(
        $victimsDisplay,
        $playerId > 0 && $victims > 0 ? amiga_player_chronology_victims_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('Victims', $victimsValue, k2_lb_help_victims());
    $culprits = (int) ($row['DifferentCulprits'] ?? 0);
    $culpritsDisplay = '<span class="blue">' . k2_fmt_count($row['DifferentCulprits'] ?? null, $games) . '</span>';
    $culpritsValue = amiga_profile_lb_slice_link_star_value_html(
        $culpritsDisplay,
        $playerId > 0 && $culprits > 0 ? amiga_player_chronology_culprits_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('Culprits', $culpritsValue, k2_lb_help_culprits());
    $ddVictims = (int) ($row['DoubleDigitsVictims'] ?? 0);
    $ddVictimsDisplay = '<span class="blue">' . k2_fmt_count($row['DoubleDigitsVictims'] ?? null, $games) . '</span>';
    $ddVictimsValue = amiga_profile_lb_slice_link_star_value_html(
        $ddVictimsDisplay,
        $playerId > 0 && $ddVictims > 0 ? amiga_player_chronology_dd_victims_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('Double Digits victims', $ddVictimsValue, k2_lb_help_dd_victims());
    $ddCulprits = (int) ($row['DoubleDigitsCulprits'] ?? 0);
    $ddCulpritsDisplay = '<span class="blue">' . k2_fmt_count($row['DoubleDigitsCulprits'] ?? null, $games) . '</span>';
    $ddCulpritsValue = amiga_profile_lb_slice_link_star_value_html(
        $ddCulpritsDisplay,
        $playerId > 0 && $ddCulprits > 0 ? amiga_player_chronology_dd_culprits_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('Double Digits culprits', $ddCulpritsValue, k2_lb_help_dd_culprits());
    $csVictims = (int) ($row['CleanSheetsVictims'] ?? 0);
    $csVictimsDisplay = '<span class="blue">' . k2_fmt_count($row['CleanSheetsVictims'] ?? null, $games) . '</span>';
    $csVictimsValue = amiga_profile_lb_slice_link_star_value_html(
        $csVictimsDisplay,
        $playerId > 0 && $csVictims > 0 ? amiga_player_chronology_cs_victims_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('Clean Sheets victims', $csVictimsValue, k2_lb_help_cs_victims());
    $csCulprits = (int) ($row['CleanSheetsCulprits'] ?? 0);
    $csCulpritsDisplay = '<span class="blue">' . k2_fmt_count($row['CleanSheetsCulprits'] ?? null, $games) . '</span>';
    $csCulpritsValue = amiga_profile_lb_slice_link_star_value_html(
        $csCulpritsDisplay,
        $playerId > 0 && $csCulprits > 0 ? amiga_player_chronology_cs_culprits_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('Clean Sheets culprits', $csCulpritsValue, k2_lb_help_cs_culprits());
    $mgcVictims = (int) ($row['MostGoalsConcededVictims'] ?? 0);
    $mgcVictimsDisplay = '<span class="blue">' . k2_fmt_count($row['MostGoalsConcededVictims'] ?? null, $games) . '</span>';
    $mgcVictimsValue = amiga_profile_lb_slice_link_star_value_html(
        $mgcVictimsDisplay,
        $playerId > 0 && $mgcVictims > 0 ? amiga_player_chronology_mgc_victims_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('MGC victims', $mgcVictimsValue, k2_lb_help_mgc_victims(), 'Most Goals Conceded victims');
    $blVictims = (int) ($row['BiggestLossVictims'] ?? 0);
    $blVictimsDisplay = '<span class="blue">' . k2_fmt_count($row['BiggestLossVictims'] ?? null, $games) . '</span>';
    $blVictimsValue = amiga_profile_lb_slice_link_star_value_html(
        $blVictimsDisplay,
        $playerId > 0 && $blVictims > 0 ? amiga_player_chronology_bl_victims_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('Biggest loss victims', $blVictimsValue, k2_lb_help_bl_victims());
    $mgsCulprits = (int) ($row['MostGoalsScoredCulprits'] ?? 0);
    $mgsCulpritsDisplay = '<span class="blue">' . k2_fmt_count($row['MostGoalsScoredCulprits'] ?? null, $games) . '</span>';
    $mgsCulpritsValue = amiga_profile_lb_slice_link_star_value_html(
        $mgsCulpritsDisplay,
        $playerId > 0 && $mgsCulprits > 0 ? amiga_player_chronology_mgs_culprits_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('MGS culprits', $mgsCulpritsValue, k2_lb_help_mgs_culprits(), 'Most Goals Scored culprits');
    $bwCulprits = (int) ($row['BiggestWinCulprits'] ?? 0);
    $bwCulpritsDisplay = '<span class="blue">' . k2_fmt_count($row['BiggestWinCulprits'] ?? null, $games) . '</span>';
    $bwCulpritsValue = amiga_profile_lb_slice_link_star_value_html(
        $bwCulpritsDisplay,
        $playerId > 0 && $bwCulprits > 0 ? amiga_player_chronology_bw_culprits_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('Biggest win culprits', $bwCulpritsValue, k2_lb_help_bw_culprits());
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_tournament_honours(array $row): void
{
    $playerId = (int) ($row['ID'] ?? 0);
    $events = (int) ($row['tournaments_played'] ?? 0);
    $eventsValue = amiga_profile_lb_slice_link_star_value(
        (string) $events,
        $playerId > 0 && $events > 0
            ? amiga_profile_lb_slice_player_tournaments_href($playerId)
            : ''
    );
    echo amiga_profile_lb_slice_row('Tournaments played', $eventsValue, k2_lb_help_amiga_tournament_events());

    $podiums = (int) ($row['event_podiums'] ?? 0);
    $podiumsValue = amiga_profile_lb_slice_link_star_value(
        (string) $podiums,
        $playerId > 0 && $podiums > 0
            ? amiga_profile_lb_slice_player_tournaments_href($playerId, 'all', '', '', 'with-podium')
            : ''
    );
    echo amiga_profile_lb_slice_row('Podiums', $podiumsValue, k2_lb_help_amiga_event_podiums());

    $gold = (int) ($row['event_gold'] ?? 0);
    $goldMarkup = amiga_wc_podium_medal_value_markup($gold, 1);
    $goldValue = amiga_profile_lb_slice_link_star_value_html(
        $goldMarkup,
        $playerId > 0 && $gold > 0
            ? amiga_profile_lb_slice_player_tournaments_href($playerId, 'all', '', 'with-win')
            : ''
    );
    echo amiga_profile_lb_slice_row(
        k2_lb_honours_medal_th(1) . '<span class="visually-hidden">Event gold</span>',
        $goldValue,
        k2_lb_help_amiga_event_gold(),
        'Event gold',
        '',
        'k2-lb-honours-medal-td'
    );

    $silver = (int) ($row['event_silver'] ?? 0);
    $silverMarkup = amiga_wc_podium_medal_value_markup($silver, 2);
    $silverValue = amiga_profile_lb_slice_link_star_value_html(
        $silverMarkup,
        $playerId > 0 && $silver > 0
            ? amiga_profile_lb_slice_player_tournaments_href($playerId, 'all', '', '', '', 2)
            : ''
    );
    echo amiga_profile_lb_slice_row(
        k2_lb_honours_medal_th(2) . '<span class="visually-hidden">Event silver</span>',
        $silverValue,
        k2_lb_help_amiga_event_silver(),
        'Event silver',
        '',
        'k2-lb-honours-medal-td'
    );

    $bronze = (int) ($row['event_bronze'] ?? 0);
    $bronzeMarkup = amiga_wc_podium_medal_value_markup($bronze, 3);
    $bronzeValue = amiga_profile_lb_slice_link_star_value_html(
        $bronzeMarkup,
        $playerId > 0 && $bronze > 0
            ? amiga_profile_lb_slice_player_tournaments_href($playerId, 'all', '', '', '', 3)
            : ''
    );
    echo amiga_profile_lb_slice_row(
        k2_lb_honours_medal_th(3) . '<span class="visually-hidden">Event bronze</span>',
        $bronzeValue,
        k2_lb_help_amiga_event_bronze(),
        'Event bronze',
        '',
        'k2-lb-honours-medal-td'
    );

    $perfect = (int) ($row['perfect_events'] ?? 0);
    $perfectValue = amiga_profile_lb_slice_link_star_value(
        (string) $perfect,
        $playerId > 0 && $perfect > 0
            ? amiga_profile_lb_slice_player_tournaments_href($playerId, 'all', 'with-participant')
            : ''
    );
    echo amiga_profile_lb_slice_row('Perfect tournaments', $perfectValue, k2_lb_help_amiga_perfect_events());
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_calendar_geo(array $row): void
{
    require_once __DIR__ . '/amiga_player_chronologies_lib.php';
    $playerId = (int) ($row['ID'] ?? 0);
    $games = (int) ($row['NumberGames'] ?? 0);
    $peakGamesYear = $row['peak_year_games_year'] ?? null;
    $peakEventsYear = $row['peak_year_tournaments_year'] ?? null;
    $peakGames = (int) ($row['peak_year_games'] ?? 0);
    $peakEvents = (int) ($row['peak_year_tournaments'] ?? 0);

    $peakGamesHref = '';
    if ($playerId > 0 && k2_derived_games_started($games) && $peakGames > 0) {
        $peakGamesHref = (string) ($row['calendar_geo_lb_peak_games_href'] ?? '');
    }
    $peakGamesYearHtml = $peakGamesYear !== null ? k2_h((string) (int) $peakGamesYear) : null;
    $peakGamesValue = amiga_profile_lb_slice_link_star_value_inline_muted_prefix_html(
        '<span class="blue">' . $peakGames . '</span>',
        $peakGamesYearHtml,
        $peakGamesHref
    );

    echo amiga_profile_lb_slice_row(
        'Most games in one year',
        $peakGamesValue,
        k2_lb_help_amiga_peak_year_games()
    );

    $peakEventsHref = '';
    if ($playerId > 0 && k2_derived_games_started($games) && $peakEvents > 0) {
        $peakEventsHref = (string) ($row['calendar_geo_lb_peak_events_href'] ?? '');
    }
    $peakEventsYearHtml = $peakEventsYear !== null ? k2_h((string) (int) $peakEventsYear) : null;
    $peakEventsValue = amiga_profile_lb_slice_link_star_value_inline_muted_prefix_html(
        (string) $peakEvents,
        $peakEventsYearHtml,
        $peakEventsHref
    );

    echo amiga_profile_lb_slice_row(
        'Most tournaments in one year',
        $peakEventsValue,
        k2_lb_help_amiga_peak_year_tournaments()
    );
    $hostCountries = (int) ($row['countries_played_in'] ?? 0);
    $hostCountriesDisplay = '<span class="blue">' . (string) $hostCountries . '</span>';
    $hostCountriesValue = amiga_profile_lb_slice_link_star_value_html(
        $hostCountriesDisplay,
        $playerId > 0 && $hostCountries > 0 ? amiga_player_chronology_host_countries_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('Different host countries', $hostCountriesValue, k2_lb_help_amiga_countries_played_in());
    $countriesFaced = (int) ($row['opponent_countries_faced'] ?? 0);
    $countriesFacedDisplay = '<span class="blue">' . (string) $countriesFaced . '</span>';
    $countriesFacedValue = amiga_profile_lb_slice_link_star_value_html(
        $countriesFacedDisplay,
        $playerId > 0 && $countriesFaced > 0 ? amiga_player_chronology_countries_faced_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('Countries faced', $countriesFacedValue, k2_lb_help_amiga_opponent_countries_faced());
    $countriesBeaten = (int) ($row['opponent_countries_beaten'] ?? 0);
    $countriesBeatenDisplay = '<span class="blue">' . (string) $countriesBeaten . '</span>';
    $countriesBeatenValue = amiga_profile_lb_slice_link_star_value_html(
        $countriesBeatenDisplay,
        $playerId > 0 && $countriesBeaten > 0 ? amiga_player_chronology_countries_beaten_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('Countries beaten', $countriesBeatenValue, k2_lb_help_amiga_opponent_countries_beaten());
    $countriesBeatenBy = (int) ($row['opponent_countries_beaten_by'] ?? 0);
    $countriesBeatenByDisplay = '<span class="blue">' . (string) $countriesBeatenBy . '</span>';
    $countriesBeatenByValue = amiga_profile_lb_slice_link_star_value_html(
        $countriesBeatenByDisplay,
        $playerId > 0 && $countriesBeatenBy > 0 ? amiga_player_chronology_countries_beaten_by_entry_href($playerId) : ''
    );
    echo amiga_profile_lb_slice_row('Countries beaten by', $countriesBeatenByValue, k2_lb_help_amiga_opponent_countries_beaten_by());
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_peak_rating(array $row): void
{
    $playerId = (int) ($row['ID'] ?? 0);
    $games = (int) ($row['NumberGames'] ?? 0);

    echo amiga_profile_lb_slice_row(
        'Peak rating',
        amiga_profile_lb_slice_value_inline_muted_prefix(
            amiga_lb_peak_rating_peak_cell_html($row),
            amiga_profile_lb_slice_event_date_stacked_muted($row['peak_rating_date'] ?? null)
        ),
        k2_lb_help_peak()
    );
    echo amiga_profile_lb_slice_row(
        'Peak rank',
        amiga_profile_lb_slice_value_inline_muted_prefix(
            amiga_lb_peak_rating_peak_rank_cell_html($row),
            amiga_profile_lb_slice_event_date_stacked_muted($row['peak_elo_rank_date'] ?? null)
        ),
        k2_lb_help_peak_elo_rank()
    );

    $nadirDisplay = k2_fmt_nadir_rating($row['LowestRating'] ?? null);
    $nadirHref = '';
    if ($playerId > 0 && k2_derived_games_started($games) && $nadirDisplay !== '-') {
        $nadirHref = (string) ($row['peak_rating_lb_nadir_href'] ?? '');
    }
    echo amiga_profile_lb_slice_row(
        'Rating nadir',
        amiga_profile_lb_slice_value_inline_muted_prefix(
            amiga_profile_lb_slice_link_star_value($nadirDisplay, $nadirHref),
            amiga_profile_lb_slice_event_date_stacked_muted($row['lowest_rating_date'] ?? null)
        ),
        k2_lb_help_amiga_profile_nadir()
    );
    echo amiga_profile_lb_slice_row(
        'Highest rated victim',
        amiga_profile_lb_slice_games_inventory_link(
            k2_fmt_lb_stat($row['HighestRatedVictim'] ?? null, $games),
            $playerId,
            $games,
            'win',
            'opp_rating',
            'desc'
        ),
        k2_lb_help_highest_victim()
    );
    echo amiga_profile_lb_slice_row(
        'Lowest rated culprit',
        amiga_profile_lb_slice_games_inventory_link(
            k2_fmt_lb_stat($row['LowestRatedCulprit'] ?? null, $games, 5000.0),
            $playerId,
            $games,
            'loss',
            'opp_rating',
            'asc'
        ),
        k2_lb_help_lowest_culprit()
    );
}

/**
 * @param ?array<string, mixed> $row from amiga_profile_lb_slices_load()
 */
function amiga_profile_render_lb_slices(?array $row): void
{
    if ($row === null) {
        return;
    }

    k2_table_js_enqueue();

    $sectionsByKey = amiga_profile_lb_slice_sections($row);
    $columnPlan = amiga_profile_lb_slice_column_plan();
    ?>
<section class="k2-amiga-profile-lb-slices" aria-label="Career stats">
<?php amiga_profile_lb_slice_render_columns($sectionsByKey, $columnPlan); ?>
</section>
    <?php
}