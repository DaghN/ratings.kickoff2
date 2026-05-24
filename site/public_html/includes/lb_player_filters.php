<?php
/**
 * Leaderboard player pool filters (ranked1–7 only; not Hall of Fame).
 *
 * Default: include inactive and provisional (Display = 1 only).
 * Query params inactive=0 / provisional=0 tighten the pool.
 */

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
        $parts[] = $prefix . 'NumberGames >= 20';
    }

    return implode(' AND ', $parts);
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
        return basename($_SERVER['SCRIPT_NAME'] ?? 'ranked7.php');
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

    $page = basename($_SERVER['SCRIPT_NAME'] ?? 'ranked7.php');
    $qs = $params === [] ? '' : '?' . http_build_query($params);

    return $page . $qs;
}
