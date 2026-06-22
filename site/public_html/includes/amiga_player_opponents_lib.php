<?php
/**
 * Amiga player Opponents wing — view parsing and URLs.
 *
 * @see docs/amiga-opponents-wing-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';

/** @var list<string> */
const K2_AMIGA_PLAYER_OPPONENTS_VIEWS = ['h2h', 'wdl', 'goals', 'dds'];

/** @var array<string, string> */
const K2_AMIGA_PLAYER_OPPONENTS_ROUTE_KEYS = [
    'h2h' => 'amiga-player-opponents-h2h',
    'wdl' => 'amiga-player-opponents-wdl',
    'goals' => 'amiga-player-opponents-goals',
    'dds' => 'amiga-player-opponents-dds',
];

function amiga_player_opponents_view_from_script(): string
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

function amiga_player_opponents_parse_view(?string $raw): string
{
    if (is_string($raw) && $raw !== '') {
        $view = strtolower(trim($raw));
        if (in_array($view, K2_AMIGA_PLAYER_OPPONENTS_VIEWS, true)) {
            return $view;
        }
    }

    return amiga_player_opponents_view_from_script();
}

function amiga_player_opponents_default_href(int $playerId): string
{
    return k2_amiga_route('amiga-player-opponents-h2h', ['id' => max(0, $playerId)]);
}

function amiga_player_opponents_href(int $playerId, string $view = 'h2h', ?int $opponentId = null): string
{
    $view = amiga_player_opponents_parse_view($view);
    $routeKey = K2_AMIGA_PLAYER_OPPONENTS_ROUTE_KEYS[$view] ?? K2_AMIGA_PLAYER_OPPONENTS_ROUTE_KEYS['h2h'];
    $params = ['id' => max(0, $playerId)];
    if ($view === 'h2h' && $opponentId !== null && $opponentId > 0 && $opponentId !== $playerId) {
        $params['opponent'] = $opponentId;
    }

    return k2_amiga_route($routeKey, $params);
}

function amiga_player_opponents_view_label(string $view): string
{
    return match (amiga_player_opponents_parse_view($view)) {
        'goals' => 'Goals',
        'dds' => 'DDs',
        'h2h' => 'Head-to-head',
        'wdl' => 'W/D/L',
        default => 'Head-to-head',
    };
}

/** Hero games tab filtered to one opponent (carries active `as=` when time travelling). */
function amiga_player_opponents_games_filtered_href(int $playerId, int $opponentId): string
{
    require_once __DIR__ . '/amiga_player_games_lib.php';

    return amiga_games_build_url([
        'id' => max(0, $playerId),
        'opponent' => max(0, $opponentId),
    ]) . '#matching-games';
}

function amiga_player_opponents_games_cell_html(int $playerId, int $opponentId, int $games): string
{
    if ($games <= 0 || $playerId <= 0 || $opponentId <= 0) {
        return (string) $games;
    }

    return '<a class="k2-link-star" href="'
        . htmlspecialchars(amiga_player_opponents_games_filtered_href($playerId, $opponentId), ENT_QUOTES, 'UTF-8')
        . '">' . $games . '</a>';
}
