<?php
/**
 * Leaderboard player pool filters (ranked1–7 only; not Hall of Fame).
 *
 * Base pool: at least one rated game (`NumberGames >= 1`). Legacy `Display` is not used on read paths.
 * Query params inactive=0 / provisional=0 tighten the pool.
 *
 * Established player threshold (rated games): same as HoF ratio/average eligibility and
 * `established_20` milestone — see docs/ratedresults-schema.md.
 */
require_once __DIR__ . '/k2_routes.php';

if (!defined('K2_ESTABLISHED_MIN_GAMES')) {
    define('K2_ESTABLISHED_MIN_GAMES', 20);
}

/** Minimum rated games for ladder pool membership (replaces legacy Display = 1 reads). */
function k2_playertable_min_rated_games(): int
{
    return 1;
}

/**
 * SQL predicate: player has at least one rated game.
 *
 * @param string $alias Table alias without trailing dot (empty = unqualified columns).
 */
function k2_playertable_rated_pool_sql(string $alias = ''): string
{
    $prefix = $alias !== '' ? preg_replace('/[^a-zA-Z0-9_]/', '', $alias) . '.' : '';

    return $prefix . 'NumberGames >= ' . k2_playertable_min_rated_games();
}

function k2_established_min_games(): int
{
    return (int) K2_ESTABLISHED_MIN_GAMES;
}

/**
 * @return array{include_inactive: bool, include_provisional: bool}
 */
function k2_lb_filter_opts(): array
{
    return [
        'include_inactive' => !isset($_GET['inactive']) || $_GET['inactive'] !== '0',
        'include_provisional' => !isset($_GET['provisional']) || $_GET['provisional'] !== '0',
    ];
}

/**
 * @param array{include_inactive?: bool, include_provisional?: bool}|null $opts
 */
function k2_lb_player_where_sql(?array $opts = null): string
{
    return k2_lb_player_where_sql_for_alias('', $opts);
}

/**
 * @param string $alias Table alias without trailing dot (empty = unqualified columns).
 * @param array{include_inactive?: bool, include_provisional?: bool}|null $opts
 */
function k2_lb_player_where_sql_for_alias(string $alias, ?array $opts = null): string
{
    $opts = $opts ?? k2_lb_filter_opts();
    $prefix = $alias !== '' ? preg_replace('/[^a-zA-Z0-9_]/', '', $alias) . '.' : '';

    $parts = [k2_playertable_rated_pool_sql($alias)];
    if (empty($opts['include_inactive'])) {
        $parts[] = $prefix . 'LastGame >= DATE_SUB(NOW(), INTERVAL 12 MONTH)';
    }
    if (empty($opts['include_provisional'])) {
        $parts[] = $prefix . 'NumberGames >= ' . k2_established_min_games();
    }

    return implode(' AND ', $parts);
}

/**
 * Optional table sort from the query string (ranked wings only; same page as toggle).
 *
 * @return array{k2_sort?: string, k2_dir?: string}
 */
function k2_lb_sort_query_params(): array
{
    require_once __DIR__ . '/k2_table_helpers.php';

    return k2_table_sort_query_params();
}

/**
 * @param array{include_inactive?: bool, include_provisional?: bool}|null $opts
 * @return array<string, string>
 */
function k2_lb_filter_query_params(?array $opts = null): array
{
    $opts = $opts ?? k2_lb_filter_opts();
    $params = [];
    if (empty($opts['include_inactive'])) {
        $params['inactive'] = '0';
    }
    if (empty($opts['include_provisional'])) {
        $params['provisional'] = '0';
    }

    return $params;
}

/**
 * @param array{include_inactive?: bool, include_provisional?: bool}|null $opts
 */
function k2_lb_filter_query_string(?array $opts = null): string
{
    $params = k2_lb_filter_query_params($opts);

    return $params === [] ? '' : '?' . http_build_query($params);
}

/**
 * Href for toggling one filter on the current ranked page.
 */
function k2_lb_filter_toggle_href(string $param): string
{
    if ($param !== 'inactive' && $param !== 'provisional') {
        return k2_route('lb-rating');
    }

    $opts = k2_lb_filter_opts();
    $params = k2_lb_filter_query_params($opts);

    if ($param === 'inactive') {
        if ($opts['include_inactive']) {
            $params['inactive'] = '0';
        } else {
            unset($params['inactive']);
        }
    } elseif ($param === 'provisional') {
        if ($opts['include_provisional']) {
            $params['provisional'] = '0';
        } else {
            unset($params['provisional']);
        }
    }

    $params = array_merge($params, k2_lb_sort_query_params());

    $page = k2_current_page_path() !== '' ? '/' . k2_current_page_path() : k2_route('lb-rating');
    if (k2_route_is_current('lb-league-honours')) {
        if (!function_exists('k2_lb_league_honours_merge_filter_params')) {
            require_once __DIR__ . '/league_honours_leaderboard.php';
        }
        $params = k2_lb_league_honours_merge_filter_params($params);
    }
    $qs = $params === [] ? '' : '?' . http_build_query($params);

    return $page . $qs;
}

/** DOM id on leaderboard `.k2-table-wrap` — hero stat links land here. */
function k2_lb_table_anchor_id(): string
{
    return 'k2-lb-table';
}

function k2_lb_table_anchor_hash(): string
{
    return '#' . k2_lb_table_anchor_id();
}

/** Zero-height scroll target immediately above the leaderboard table. */
function k2_lb_table_anchor_markup(): string
{
    return '<div id="' . k2_lb_table_anchor_id() . '" class="k2-lb-table-anchor" tabindex="-1"></div>';
}

/** DOM id on a leaderboard player row — hero rank/rating links land here. */
function k2_lb_player_row_anchor_id(int $playerId): string
{
    return 'k2-lb-player-' . max(0, $playerId);
}

function k2_lb_player_row_anchor_hash(int $playerId): string
{
    return '#' . k2_lb_player_row_anchor_id($playerId);
}

/** Zero-height scroll target at the top of a leaderboard player row. */
function k2_lb_player_row_anchor_markup(int $playerId): string
{
    return '<span id="' . k2_h(k2_lb_player_row_anchor_id($playerId)) . '" class="k2-lb-player-row-anchor" tabindex="-1"></span>';
}

/**
 * Leaderboard wing URL that scrolls to the table top (profile hero stat links).
 *
 * @param array<string, scalar> $query
 */
function k2_lb_table_href(string $routeKey, array $query = []): string
{
    $path = k2_route($routeKey);
    if ($query !== []) {
        $path .= '?' . http_build_query($query);
    }

    return $path . k2_lb_table_anchor_hash();
}

/**
 * Online rating LB URL that scrolls to a player's row (profile hero rank / rating links).
 *
 * @param array<string, scalar> $query
 */
function k2_lb_rating_player_href(int $playerId, array $query = []): string
{
    $path = k2_route('lb-rating');
    if ($query !== []) {
        $path .= '?' . http_build_query($query);
    }
    if ($playerId < 1) {
        return $path . k2_lb_table_anchor_hash();
    }

    return $path . k2_lb_player_row_anchor_hash($playerId);
}

/**
 * Activity peaks LB URL that scrolls to a player's row (profile hero games link).
 *
 * @param array<string, scalar> $query
 */
function k2_lb_activity_peaks_player_href(int $playerId, array $query = []): string
{
    if ($query === []) {
        $query = ['k2_sort' => '3', 'k2_dir' => 'desc'];
    }
    $path = k2_route('lb-activity-peaks');
    $path .= '?' . http_build_query($query);
    if ($playerId < 1) {
        return $path . k2_lb_table_anchor_hash();
    }

    return $path . k2_lb_player_row_anchor_hash($playerId);
}

/**
 * Hub LB / status table Elo cell → rating LB row anchor (same URL contract as profile hero rating link).
 */
function k2_lb_rating_cell_link(int $playerId, mixed $rating, string $playerName = ''): string
{
    $display = k2_fmt_int($rating, '—');
    if ($playerId < 1 || $display === '—') {
        return k2_h($display);
    }

    $href = k2_lb_rating_player_href($playerId);
    $name = trim($playerName);
    $ariaLabel = $name !== ''
        ? 'View ' . $name . ' on rating leaderboard'
        : 'View on rating leaderboard';

    return '<a class="k2-link-star" href="' . k2_h($href) . '" aria-label="' . k2_h($ariaLabel) . '"'
        . ' data-k2-player-glance-rating="' . $playerId . '">'
        . k2_h($display) . '</a>';
}
