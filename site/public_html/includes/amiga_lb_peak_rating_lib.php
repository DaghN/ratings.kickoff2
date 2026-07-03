<?php
/**
 * Amiga peak-rating LB — Peak + Peak rank column tournament-context tooltips.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_player_game_row.php';
require_once __DIR__ . '/peak_month_leaderboard_query.php';

function amiga_player_peak_rank_played_in_event(mysqli $con, int $playerId, int $tournamentId): bool
{
    if ($playerId < 1 || $tournamentId < 1) {
        return false;
    }

    $stmt = $con->prepare(
        'SELECT 1 FROM amiga_player_event_snapshots '
        . 'WHERE player_id = ? AND tournament_id = ? AND NumberGames > 0 LIMIT 1'
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $playerId, $tournamentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return false;
    }
    $res = $stmt->get_result();
    $found = $res && $res->fetch_row();
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $found !== false && $found !== null;
}

/** Plain-text absent clause for peak-rank tournament context (LB tooltip + rank chart peak line). */
function amiga_player_peak_rank_absent_clause(string $playerName): string
{
    $playerName = trim($playerName);
    if ($playerName === '') {
        return '';
    }

    return ' (' . $playerName . ' did not play in this tournament, but the results favored his standing in the rating list)';
}

function amiga_lb_peak_rating_peak_tooltip_enabled(array $row): bool
{
    if (k2_fmt_peak_rating($row['PeakRating'] ?? null) === '-') {
        return false;
    }

    $tournamentId = (int) ($row['peak_rating_tournament_id'] ?? 0);
    $tournamentName = trim((string) ($row['peak_rating_tournament_name'] ?? ''));

    return $tournamentId > 0 && $tournamentName !== '';
}

function amiga_lb_peak_rating_peak_tooltip_html(array $row): string
{
    $name = htmlspecialchars((string) ($row['Name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $nameHtml = '<span class="k2-link-star">' . $name . '</span>';
    $tournamentName = htmlspecialchars(trim((string) ($row['peak_rating_tournament_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $peakInt = (int) round((float) ($row['PeakRating'] ?? 0));
    $peakHtml = '<span class="k2-link-star">' . $peakInt . '</span>';
    $tournamentHtml = '<span class="k2-link-star">' . $tournamentName . '</span>';

    $dateClause = '';
    $peakDate = $row['peak_rating_date'] ?? null;
    if ($peakDate !== null && $peakDate !== '') {
        $day = substr((string) $peakDate, 0, 10);
        if ($day !== '') {
            $formatted = k2_format_peak_period('day', $day);
            if ($formatted !== $day) {
                $dateHtml = htmlspecialchars($formatted, ENT_QUOTES, 'UTF-8');
                $dateClause = ' on <span class="k2-link-star">' . $dateHtml . '</span>';
            }
        }
    }

    $deltaClause = '';
    $delta = $row['peak_rating_delta'] ?? null;
    if ($delta !== null && $delta !== '' && !k2_db_is_null($delta)) {
        $deltaClause = ' where he won ' . k2_player_game_signed_number_html((float) $delta) . ' rating points';
    }

    return $nameHtml . ' obtained his highest Elo rating ever' . $dateClause . ' at the conclusion of ' . $tournamentHtml
        . $deltaClause . ' to obtain his all-time peak rating of ' . $peakHtml . '.';
}

function amiga_lb_peak_rating_peak_cell_html(array $row): string
{
    $peakDisplay = k2_fmt_peak_rating($row['PeakRating'] ?? null);
    if (!amiga_lb_peak_rating_peak_tooltip_enabled($row)) {
        return '<span class="blue">' . htmlspecialchars($peakDisplay, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    require_once __DIR__ . '/amiga_tournament_lib.php';

    $tournamentId = (int) $row['peak_rating_tournament_id'];
    $href = amiga_tournament_href(amiga_tournament_event_stats_url($tournamentId))
        . '#' . AMIGA_TOURNAMENT_PAGE_FRAGMENT;
    $help = htmlspecialchars(amiga_lb_peak_rating_peak_tooltip_html($row), ENT_QUOTES, 'UTF-8');

    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"'
        . ' class="blue k2-lb-amiga-peak-link k2-table-helped"'
        . ' data-k2-coarse-tap="1"'
        . ' data-k2-tooltip-hide-title="1"'
        . ' data-k2-help-html="1"'
        . ' data-k2-help="' . $help . '"'
        . ' data-k2-tooltip-action="Click to open this tournament"'
        . ' data-k2-tooltip-action-coarse="Tap again to open this tournament"'
        . '>' . htmlspecialchars($peakDisplay, ENT_QUOTES, 'UTF-8') . '</a>';
}

function amiga_lb_peak_rating_peak_rank_tooltip_enabled(array $row): bool
{
    if (k2_fmt_peak_elo_rank($row['peak_elo_rank'] ?? null) === '—') {
        return false;
    }

    $tournamentId = (int) ($row['peak_elo_rank_tournament_id'] ?? 0);
    $tournamentName = trim((string) ($row['peak_elo_rank_tournament_name'] ?? ''));

    return $tournamentId > 0 && $tournamentName !== '';
}

function amiga_lb_peak_rating_peak_rank_href(int $tournamentId): string
{
    require_once __DIR__ . '/amiga_snapshot_url.php';
    require_once __DIR__ . '/amiga_rating_history_lib.php';
    require_once __DIR__ . '/amiga_time_travel_stamp.php';

    $asParam = amiga_snapshot_format_as_param('event', (string) $tournamentId);
    $extraQuery = ['as_with' => null];
    if (!amiga_snapshot_time_travel_active_from_request()) {
        $extraQuery = array_merge($extraQuery, amiga_time_travel_stamp_arrival_entry_query());
    }

    $path = amiga_url_with_as_param('/amiga/leaderboards/rating.php', $asParam, $extraQuery);
    $hashPos = strpos($path, '#');
    if ($hashPos !== false) {
        $path = substr($path, 0, $hashPos);
    }
    $qPos = strpos($path, '?');
    if ($qPos !== false) {
        /** @var array<string, scalar> $query */
        $query = [];
        parse_str(substr($path, $qPos + 1), $query);
        unset($query['as_with']);
        $path = substr($path, 0, $qPos) . ($query === [] ? '' : '?' . http_build_query($query));
    }

    return $path;
}

function amiga_lb_peak_rating_peak_rank_tooltip_html(array $row): string
{
    $name = htmlspecialchars((string) ($row['Name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $nameHtml = '<span class="k2-link-star">' . $name . '</span>';
    $rankInt = (int) ($row['peak_elo_rank'] ?? 0);
    $rankHtml = '<span class="k2-link-star">#' . $rankInt . '</span>';
    $tournamentName = htmlspecialchars(trim((string) ($row['peak_elo_rank_tournament_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $tournamentHtml = '<span class="k2-link-star">' . $tournamentName . '</span>';

    $dateClause = '';
    $peakRankDate = $row['peak_elo_rank_date'] ?? null;
    if ($peakRankDate !== null && $peakRankDate !== '') {
        $day = substr((string) $peakRankDate, 0, 10);
        if ($day !== '') {
            $formatted = k2_format_peak_period('day', $day);
            if ($formatted !== $day) {
                $dateHtml = htmlspecialchars($formatted, ENT_QUOTES, 'UTF-8');
                $dateClause = ' on <span class="k2-link-star">' . $dateHtml . '</span>';
            }
        }
    }

    $absentClause = '';
    $played = (int) ($row['peak_elo_rank_played_in_event'] ?? 0) === 1;
    if (!$played) {
        $plain = amiga_player_peak_rank_absent_clause((string) ($row['Name'] ?? ''));
        $absentClause = $plain !== '' ? htmlspecialchars($plain, ENT_QUOTES, 'UTF-8') : '';
    }

    return $nameHtml . ' first obtained his peak rank ' . $rankHtml . $dateClause
        . ' at the conclusion of ' . $tournamentHtml . $absentClause
        . '. Click to see the time travel snapshot of the rating list at this time.';
}

function amiga_lb_peak_rating_peak_rank_cell_html(array $row): string
{
    $rankDisplay = k2_fmt_peak_elo_rank($row['peak_elo_rank'] ?? null);
    if (!amiga_lb_peak_rating_peak_rank_tooltip_enabled($row)) {
        return htmlspecialchars($rankDisplay, ENT_QUOTES, 'UTF-8');
    }

    $tournamentId = (int) ($row['peak_elo_rank_tournament_id'] ?? 0);
    $href = amiga_lb_peak_rating_peak_rank_href($tournamentId);
    $help = htmlspecialchars(amiga_lb_peak_rating_peak_rank_tooltip_html($row), ENT_QUOTES, 'UTF-8');

    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"'
        . ' class="k2-lb-amiga-peak-rank-link k2-table-helped"'
        . ' data-k2-coarse-tap="1"'
        . ' data-k2-tooltip-hide-title="1"'
        . ' data-k2-help-html="1"'
        . ' data-k2-help="' . $help . '"'
        . ' data-k2-tooltip-action="Click to open the rating list at this snapshot"'
        . ' data-k2-tooltip-action-coarse="Tap again to open the rating list at this snapshot"'
        . '>' . htmlspecialchars($rankDisplay, ENT_QUOTES, 'UTF-8') . '</a>';
}
