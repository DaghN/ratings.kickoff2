<?php
/**
 * Player Opponents wing — view parsing and URLs.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_routes.php';

/** @var list<string> */
const K2_PLAYER_OPPONENTS_VIEWS = ['wdl', 'goals', 'dds', 'h2h'];

function player_opponents_parse_view(?string $raw): string
{
    $view = is_string($raw) ? strtolower(trim($raw)) : '';

    return in_array($view, K2_PLAYER_OPPONENTS_VIEWS, true) ? $view : 'wdl';
}

function player_opponents_href(int $playerId, string $view = 'wdl', ?int $opponentId = null): string
{
    $view = player_opponents_parse_view($view);
    $params = ['id' => max(0, $playerId)];
    if ($view !== 'wdl') {
        $params['view'] = $view;
    }
    if ($view === 'h2h' && $opponentId !== null && $opponentId > 0 && $opponentId !== $playerId) {
        $params['opponent'] = $opponentId;
    }

    return k2_route('player-opponents', $params);
}

function player_opponents_view_label(string $view): string
{
    return match (player_opponents_parse_view($view)) {
        'goals' => 'Goals',
        'dds' => 'DDs',
        'h2h' => 'Head-to-head',
        default => 'W/D/L',
    };
}
