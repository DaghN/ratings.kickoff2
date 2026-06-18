<?php
/**
 * Player Milestones wing — view parsing and URLs (Garden · Chronology).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_routes.php';

/** @var list<string> */
const K2_PLAYER_MILESTONES_VIEWS = ['garden', 'chronology'];

/** @var array<string, string> */
const K2_PLAYER_MILESTONES_ROUTE_KEYS = [
    'garden' => 'player-milestones-garden',
    'chronology' => 'player-milestones-chronology',
];

function player_milestones_view_from_script(): string
{
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
    $map = [
        'garden' => 'garden',
        'chronology' => 'chronology',
    ];

    return $map[$script] ?? 'garden';
}

function player_milestones_parse_view(?string $raw): string
{
    if (is_string($raw) && $raw !== '') {
        $view = strtolower(trim($raw));
        if (in_array($view, K2_PLAYER_MILESTONES_VIEWS, true)) {
            return $view;
        }
    }

    return player_milestones_view_from_script();
}

function player_milestones_default_href(int $playerId): string
{
    return k2_route('player-milestones-garden', ['id' => max(0, $playerId)]);
}

function player_milestones_href(int $playerId, string $view = 'garden'): string
{
    $view = player_milestones_parse_view($view);
    $routeKey = K2_PLAYER_MILESTONES_ROUTE_KEYS[$view] ?? K2_PLAYER_MILESTONES_ROUTE_KEYS['garden'];

    return k2_route($routeKey, ['id' => max(0, $playerId)]);
}

function player_milestones_view_label(string $view): string
{
    return match (player_milestones_parse_view($view)) {
        'chronology' => 'Chronology',
        'garden' => 'Garden',
        default => 'Garden',
    };
}
