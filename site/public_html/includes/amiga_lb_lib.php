<?php
/**
 * Amiga leaderboard wings — shared read-path SQL (amiga_player_current).
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/lb_player_filters.php';

/** WHERE clause for career-stat wings (players with at least one rated game). */
function amiga_lb_player_where_sql(): string
{
    return 's.NumberGames > 0';
}

/** Load time-travel context for leaderboard pages (after DB connect). */
function amiga_lb_context(mysqli $con): AmigaSnapshotContext
{
    return amiga_snapshot_context_from_request($con);
}

/**
 * Amiga leaderboard wing URL that scrolls to the table top (profile hero stat links).
 *
 * @param array<string, scalar> $query
 */
function amiga_lb_table_href(string $wingPath, array $query = []): string
{
    return amiga_url_with_context($wingPath, $query) . k2_lb_table_anchor_hash();
}

/**
 * Amiga rating LB URL that scrolls to a player's row (profile hero rank / rating links).
 *
 * @param array<string, scalar> $query
 */
function amiga_lb_rating_player_href(int $playerId, array $query = []): string
{
    $path = amiga_url_with_context('/amiga/leaderboards/rating.php', $query);
    if ($playerId < 1) {
        return $path . k2_lb_table_anchor_hash();
    }

    return $path . k2_lb_player_row_anchor_hash($playerId);
}

/**
 * Country roster / table Elo cell → rating LB row anchor (same URL contract as profile hero rating link).
 */
function k2_amiga_lb_rating_cell_link(int $playerId, mixed $rating, string $playerName = ''): string
{
    $display = k2_fmt_int($rating, '—');
    if ($playerId < 1 || $display === '—') {
        return k2_h($display);
    }

    $href = amiga_lb_rating_player_href($playerId);
    $name = trim($playerName);
    $ariaLabel = $name !== ''
        ? 'View ' . $name . ' on rating leaderboard'
        : 'View on rating leaderboard';

    return '<a class="k2-link-star" href="' . k2_h($href) . '" aria-label="' . k2_h($ariaLabel) . '"'
        . ' data-k2-amiga-player-glance-rating="' . $playerId . '">'
        . k2_h($display) . '</a>';
}

/**
 * ORDER BY for tournament honours LB (must match default sort col + skip-initial-sort).
 *
 * @see site/public_html/amiga/leaderboards/tournament-honours.php
 */
function amiga_lb_tournament_honours_order_sql(string $alias = 't'): string
{
    $a = $alias;

    return "{$a}.event_gold DESC, {$a}.event_silver DESC, {$a}.event_bronze DESC, {$a}.tournaments_played DESC, {$a}.player_id ASC";
}

function amiga_lb_chapter_lede_html(int $gameCount, int $tournamentCount): string
{
    $gamesHtml = '<span class="blue">' . number_format($gameCount) . '</span>';
    $tournamentsHtml = '<span class="blue">' . number_format($tournamentCount) . '</span>';

    return 'Leaderboards from ' . $gamesHtml
        . ' Amiga games played in ' . $tournamentsHtml
        . ' official KOA tournaments. Elo ratings and tournament honours, goals, double digits and clean sheets, victims and culprits, peak and performance ratings — sort any column to see who leads a different way.';
}

function amiga_lb_chapter_lede_html_for_request(?mysqli $con = null, ?AmigaSnapshotContext $ctx = null): string
{
    require_once __DIR__ . '/amiga_snapshot_context.php';

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $cutoffForKey = $ctx->isActive() ? $ctx->cutoff() : null;
    $cacheKey = $cutoffForKey === null
        ? 'present'
        : $cutoffForKey['event_date'] . '|' . $cutoffForKey['chrono'] . '|' . $cutoffForKey['tournament_id'];

    static $cache = [];
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    require_once __DIR__ . '/k2_safety.php';
    require_once __DIR__ . '/amiga_lb_snapshot_lib.php';
    require_once __DIR__ . '/amiga_tournament_lib.php';

    $ownedConnection = false;
    if ($con === null) {
        $configPath = __DIR__ . '/../../config/ko2amiga_config.php';
        if (!is_file($configPath)) {
            return $cache[$cacheKey] = amiga_lb_chapter_lede_html(0, 0);
        }
        include $configPath;
        $con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
        $ownedConnection = true;
        $peeked = amiga_snapshot_context_peek();
        if ($peeked !== null) {
            $ctx = $peeked;
        } else {
            $ctx = amiga_lb_context($con);
        }
    }

    $gameCount = amiga_lb_games_count($con, $ctx);
    $tournamentCount = amiga_tournament_index_count($con, $ctx);
    if ($ownedConnection) {
        mysqli_close($con);
    }

    return $cache[$cacheKey] = amiga_lb_chapter_lede_html($gameCount, $tournamentCount);
}
