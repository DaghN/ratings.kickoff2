<?php
/**
 * Player Opponents wing — view parsing and URLs.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_routes.php';

/** @var list<string> */
const K2_PLAYER_OPPONENTS_VIEWS = ['h2h', 'wdl', 'goals', 'dds'];

/** @var array<string, string> */
const K2_PLAYER_OPPONENTS_ROUTE_KEYS = [
    'h2h' => 'player-opponents-h2h',
    'wdl' => 'player-opponents-wdl',
    'goals' => 'player-opponents-goals',
    'dds' => 'player-opponents-dds',
];

function player_opponents_view_from_script(): string
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
    $map = [
        'h2h' => 'h2h',
        'wdl' => 'wdl',
        'goals' => 'goals',
        'dds' => 'dds',
    ];

    return $map[$script] ?? 'h2h';
}

function player_opponents_parse_view(?string $raw): string
{
    if (is_string($raw) && $raw !== '') {
        $view = strtolower(trim($raw));
        if (in_array($view, K2_PLAYER_OPPONENTS_VIEWS, true)) {
            return $view;
        }
    }

    return player_opponents_view_from_script();
}

function player_opponents_default_href(int $playerId): string
{
    return k2_route('player-opponents-h2h', ['id' => max(0, $playerId)]);
}

function player_opponents_href(int $playerId, string $view = 'h2h', ?int $opponentId = null): string
{
    $view = player_opponents_parse_view($view);
    $routeKey = K2_PLAYER_OPPONENTS_ROUTE_KEYS[$view] ?? K2_PLAYER_OPPONENTS_ROUTE_KEYS['h2h'];
    $params = ['id' => max(0, $playerId)];
    if ($view === 'h2h' && $opponentId !== null && $opponentId > 0 && $opponentId !== $playerId) {
        $params['opponent'] = $opponentId;
    }

    return k2_route($routeKey, $params);
}

function player_opponents_view_label(string $view): string
{
    return match (player_opponents_parse_view($view)) {
        'goals' => 'Goals',
        'dds' => 'DDs',
        'h2h' => 'Head-to-head',
        'wdl' => 'W/D/L',
        default => 'Head-to-head',
    };
}

/** Hero games tab filtered to one opponent (online). */
function player_opponents_games_filtered_href(int $playerId, int $opponentId): string
{
    return k2_route('player-games', [
        'id' => max(0, $playerId),
        'opponent' => max(0, $opponentId),
    ]) . '#matching-games';
}

function player_opponents_games_cell_html(int $playerId, int $opponentId, int $games): string
{
    if ($games <= 0 || $playerId <= 0 || $opponentId <= 0) {
        return (string) $games;
    }

    return '<a class="k2-link-star" href="'
        . htmlspecialchars(player_opponents_games_filtered_href($playerId, $opponentId), ENT_QUOTES, 'UTF-8')
        . '">' . $games . '</a>';
}
