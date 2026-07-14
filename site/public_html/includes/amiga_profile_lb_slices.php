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
        . ' s.countries_played_in, s.opponent_countries_faced, s.opponent_countries_beaten,'
        . ' s.PeakRating, s.LowestRating, s.HighestRatedVictim, s.LowestRatedCulprit,'
        . ' s.peak_rating_tournament_id, tpr.event_date AS peak_rating_date, peak_snap.rating_delta AS peak_rating_delta,'
        . ' tpr.name AS peak_rating_tournament_name,'
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

function amiga_profile_lb_slice_section_header(string $title): void
{
    $safe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo '    <tr class="server-records-section-header"><td colspan="2">' . $safe . "</td></tr>\n";
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
            'title' => 'DDs & CSs',
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

function amiga_profile_lb_slice_render_section_table(string $title, callable $renderRows): void
{
    echo '<div class="k2-amiga-profile-lb-slice-block server-records-panel">';
    echo '<div class="k2-table-wrap">';
    ?>
<table class="k2-table server-records-table k2-table--calm-stats" data-k2-anchor-col="1">
<tbody class="black">
<?php
    amiga_profile_lb_slice_section_header($title);
    $renderRows();
    ?>
</tbody>
</table>
<?php
    echo "</div></div>\n";
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
 * Primary value on first line, optional muted secondary line below (Activity dates, calendar peak years).
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

    echo amiga_profile_lb_slice_row('Games', k2_fmt_games_played($games), k2_lb_help_games());
    echo amiga_profile_lb_slice_row('Wins', k2_fmt_wdl_count($row['NumberWins'] ?? null, $games, 'win'));
    echo amiga_profile_lb_slice_row('Draws', k2_fmt_count($row['NumberDraws'] ?? null, $games));
    echo amiga_profile_lb_slice_row('Losses', k2_fmt_wdl_count($row['NumberLosses'] ?? null, $games, 'loss'));
    echo amiga_profile_lb_slice_row('Win rate', k2_fmt_pct_from_ratio($winRate, $games), k2_lb_help_amiga_wc_win_rate());
    echo amiga_profile_lb_slice_row(
        'Opponent Average',
        k2_fmt_lb_stat($row['AverageOpponentRating'] ?? null, $games),
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
    $gdPer = k2_derived_games_started($games)
        ? ((int) ($row['GoalsFor'] ?? 0) - (int) ($row['GoalsAgainst'] ?? 0)) / $games
        : null;

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

    echo amiga_profile_lb_slice_row(
        'GF',
        '<span class="blue">' . k2_fmt_count($row['GoalsFor'] ?? null, $games) . '</span>',
        k2_lb_help_amiga_goals_scored(),
        'Goals for'
    );
    echo amiga_profile_lb_slice_row(
        'GA',
        '<span class="red">' . k2_fmt_count($row['GoalsAgainst'] ?? null, $games) . '</span>',
        k2_lb_help_amiga_goals_conceded(),
        'Goals against'
    );
    echo amiga_profile_lb_slice_row('GF/g', k2_fmt_decimal($row['AverageGoalsFor'] ?? null, $games), k2_lb_help_amiga_goals_scored_avg(), 'Goals scored per game');
    echo amiga_profile_lb_slice_row('GA/g', k2_fmt_decimal($row['AverageGoalsAgainst'] ?? null, $games), k2_lb_help_amiga_goals_conceded_avg(), 'Goals conceded per game');
    echo amiga_profile_lb_slice_row('GD/g', $gdPer !== null ? k2_fmt_decimal($gdPer, $games) : k2_fmt_dash());
    echo amiga_profile_lb_slice_row('Ratio', $ratioCell, k2_lb_help_goal_ratio());
    echo amiga_profile_lb_slice_row('Max GF', k2_fmt_count($row['MostGoalsScored'] ?? null, $games), k2_lb_help_amiga_most_scored());
    echo amiga_profile_lb_slice_row('Max GA', k2_fmt_count($row['MostGoalsConceded'] ?? null, $games), k2_lb_help_amiga_most_conceded());
    echo amiga_profile_lb_slice_row('Max win', k2_fmt_count($row['BiggestWinDifference'] ?? null, $games), k2_lb_help_win_margin());
    echo amiga_profile_lb_slice_row('Max loss', k2_fmt_count($row['BiggestLossDifference'] ?? null, $games), k2_lb_help_loss_margin());
    echo amiga_profile_lb_slice_row('Max sum', k2_fmt_count($row['BiggestSumOfGoals'] ?? null, $games), k2_lb_help_goal_sum());
    echo amiga_profile_lb_slice_row('Max draw', $drawCell, k2_lb_help_biggest_draw());
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_double_digits(array $row): void
{
    $games = (int) ($row['NumberGames'] ?? 0);

    echo amiga_profile_lb_slice_row('Double Digits', '<span class="blue">' . k2_fmt_count($row['DoubleDigits'] ?? null, $games) . '</span>', k2_lb_help_double_digits());
    echo amiga_profile_lb_slice_row('Clean Sheets', k2_fmt_count($row['CleanSheets'] ?? null, $games), k2_lb_help_clean_sheets());
    echo amiga_profile_lb_slice_row('DD Ratio', k2_fmt_pct_from_ratio($row['DoubleDigitsRatio'] ?? null, $games), k2_lb_help_double_digits_ratio(), 'Double Digits ratio');
    echo amiga_profile_lb_slice_row('CS Ratio', k2_fmt_pct_from_ratio($row['CleanSheetsRatio'] ?? null, $games), k2_lb_help_clean_sheets_ratio(), 'Clean Sheets ratio');
    echo amiga_profile_lb_slice_row('DD conceded', '<span class="red">' . k2_fmt_count($row['DoubleDigitsConceded'] ?? null, $games) . '</span>', k2_lb_help_double_digits_conceded());
    echo amiga_profile_lb_slice_row('CS conceded', k2_fmt_count($row['CleanSheetsConceded'] ?? null, $games), k2_lb_help_clean_sheets_conceded());
    echo amiga_profile_lb_slice_row('DD C Ratio', k2_fmt_pct_from_ratio($row['DoubleDigitsConcededRatio'] ?? null, $games), k2_lb_help_double_digits_conceded_ratio(), 'DD conceded ratio');
    echo amiga_profile_lb_slice_row('CS C Ratio', k2_fmt_pct_from_ratio($row['CleanSheetsConcededRatio'] ?? null, $games), k2_lb_help_clean_sheets_conceded_ratio(), 'CS conceded ratio');
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_victims(array $row): void
{
    $games = (int) ($row['NumberGames'] ?? 0);

    echo amiga_profile_lb_slice_row('Opponents', '<span class="blue">' . k2_fmt_count($row['DifferentOpponents'] ?? null, $games) . '</span>', k2_lb_help_opponents());
    echo amiga_profile_lb_slice_row('Victims', k2_fmt_count($row['DifferentVictims'] ?? null, $games), k2_lb_help_victims());
    echo amiga_profile_lb_slice_row('DD Victims', k2_fmt_count($row['DoubleDigitsVictims'] ?? null, $games), k2_lb_help_dd_victims(), 'Double Digit victims');
    echo amiga_profile_lb_slice_row('CS Victims', k2_fmt_count($row['CleanSheetsVictims'] ?? null, $games), k2_lb_help_cs_victims(), 'Clean Sheet victims');
    echo amiga_profile_lb_slice_row('MGC Victims', k2_fmt_count($row['MostGoalsConcededVictims'] ?? null, $games), k2_lb_help_mgc_victims(), 'Most Goals Conceded victims');
    echo amiga_profile_lb_slice_row('BL Victims', k2_fmt_count($row['BiggestLossVictims'] ?? null, $games), k2_lb_help_bl_victims(), 'Biggest Loss victims');
    echo amiga_profile_lb_slice_row('Culprits', k2_fmt_count($row['DifferentCulprits'] ?? null, $games), k2_lb_help_culprits());
    echo amiga_profile_lb_slice_row('DD Culprits', k2_fmt_count($row['DoubleDigitsCulprits'] ?? null, $games), k2_lb_help_dd_culprits(), 'Double Digit culprits');
    echo amiga_profile_lb_slice_row('CS Culprits', k2_fmt_count($row['CleanSheetsCulprits'] ?? null, $games), k2_lb_help_cs_culprits(), 'Clean Sheet culprits');
    echo amiga_profile_lb_slice_row('MGS Culprits', k2_fmt_count($row['MostGoalsScoredCulprits'] ?? null, $games), k2_lb_help_mgs_culprits(), 'Most Goals Scored culprits');
    echo amiga_profile_lb_slice_row('BW Culprits', k2_fmt_count($row['BiggestWinCulprits'] ?? null, $games), k2_lb_help_bw_culprits(), 'Biggest Win culprits');
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_tournament_honours(array $row): void
{
    echo amiga_profile_lb_slice_row('Events', (string) (int) ($row['tournaments_played'] ?? 0), k2_lb_help_amiga_tournament_events());
    echo amiga_profile_lb_slice_row('Podiums', (string) (int) ($row['event_podiums'] ?? 0), k2_lb_help_amiga_event_podiums());
    echo amiga_profile_lb_slice_row(
        k2_lb_honours_medal_th(1) . '<span class="visually-hidden">Event gold</span>',
        amiga_wc_podium_medal_value_markup((int) ($row['event_gold'] ?? 0), 1),
        k2_lb_help_amiga_event_gold(),
        'Event gold',
        '',
        'k2-lb-honours-medal-td'
    );
    echo amiga_profile_lb_slice_row(
        k2_lb_honours_medal_th(2) . '<span class="visually-hidden">Event silver</span>',
        amiga_wc_podium_medal_value_markup((int) ($row['event_silver'] ?? 0), 2),
        k2_lb_help_amiga_event_silver(),
        'Event silver',
        '',
        'k2-lb-honours-medal-td'
    );
    echo amiga_profile_lb_slice_row(
        k2_lb_honours_medal_th(3) . '<span class="visually-hidden">Event bronze</span>',
        amiga_wc_podium_medal_value_markup((int) ($row['event_bronze'] ?? 0), 3),
        k2_lb_help_amiga_event_bronze(),
        'Event bronze',
        '',
        'k2-lb-honours-medal-td'
    );
    echo amiga_profile_lb_slice_row('Perfect', (string) (int) ($row['perfect_events'] ?? 0), k2_lb_help_amiga_perfect_events());
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_calendar_geo(array $row): void
{
    $peakGamesYear = $row['peak_year_games_year'] ?? null;
    $peakEventsYear = $row['peak_year_tournaments_year'] ?? null;
    $stackedClass = 'k2-amiga-profile-activity-value-cell--stacked';

    echo amiga_profile_lb_slice_row(
        'Peak games',
        amiga_profile_lb_slice_value_stacked(
            '<span class="blue">' . (int) ($row['peak_year_games'] ?? 0) . '</span>',
            $peakGamesYear !== null ? k2_h((string) (int) $peakGamesYear) : null
        ),
        k2_lb_help_amiga_peak_year_games(),
        null,
        '',
        $stackedClass
    );
    echo amiga_profile_lb_slice_row(
        'Peak events',
        amiga_profile_lb_slice_value_stacked(
            (string) (int) ($row['peak_year_tournaments'] ?? 0),
            $peakEventsYear !== null ? k2_h((string) (int) $peakEventsYear) : null
        ),
        k2_lb_help_amiga_peak_year_tournaments(),
        null,
        '',
        $stackedClass
    );
    echo amiga_profile_lb_slice_row('Host countries', (string) (int) ($row['countries_played_in'] ?? 0), k2_lb_help_amiga_countries_played_in());
    echo amiga_profile_lb_slice_row('Countries faced', (string) (int) ($row['opponent_countries_faced'] ?? 0), k2_lb_help_amiga_opponent_countries_faced());
    echo amiga_profile_lb_slice_row('Countries beaten', (string) (int) ($row['opponent_countries_beaten'] ?? 0), k2_lb_help_amiga_opponent_countries_beaten());
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_lb_slice_rows_peak_rating(array $row): void
{
    $games = (int) ($row['NumberGames'] ?? 0);

    echo amiga_profile_lb_slice_row('Peak Rating', amiga_lb_peak_rating_peak_cell_html($row), k2_lb_help_peak());
    echo amiga_profile_lb_slice_row('Peak date', amiga_profile_format_event_date($row['peak_rating_date'] ?? null), k2_lb_help_peak_rating_date());
    echo amiga_profile_lb_slice_row('Peak rank', amiga_lb_peak_rating_peak_rank_cell_html($row), k2_lb_help_peak_elo_rank());
    echo amiga_profile_lb_slice_row('Peak rank date', amiga_profile_format_event_date($row['peak_elo_rank_date'] ?? null), k2_lb_help_peak_elo_rank_date());
    echo amiga_profile_lb_slice_row('Nadir', k2_fmt_nadir_rating($row['LowestRating'] ?? null), k2_lb_help_nadir());
    echo amiga_profile_lb_slice_row('Opponent Avg.', k2_fmt_lb_stat($row['AverageOpponentRating'] ?? null, $games), k2_lb_help_opponent_avg());
    echo amiga_profile_lb_slice_row('Highest Victim', k2_fmt_lb_stat($row['HighestRatedVictim'] ?? null, $games), k2_lb_help_highest_victim());
    echo amiga_profile_lb_slice_row('Lowest Culprit', k2_fmt_lb_stat($row['LowestRatedCulprit'] ?? null, $games, 5000.0), k2_lb_help_lowest_culprit());
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