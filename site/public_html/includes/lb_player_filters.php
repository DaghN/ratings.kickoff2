<?php
/**
 * Leaderboard player pool filters (ranked1–7 only; not Hall of Fame).
 *
 * Default: include inactive and provisional (Display = 1 only).
 * Query params inactive=0 / provisional=0 tighten the pool.
 *
 * Established player threshold (rated games): same as HoF ratio/average eligibility and
 * `established_20` milestone — see docs/ratedresults-schema.md.
 */
require_once __DIR__ . '/k2_routes.php';

if (!defined('K2_ESTABLISHED_MIN_GAMES')) {
    define('K2_ESTABLISHED_MIN_GAMES', 20);
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

    $parts = [$prefix . 'Display = 1'];
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
