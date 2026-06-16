<?php
/**
 * Player Games tab — optional ?from= back-link context (profile played-days vs Activity peaks, etc.).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_routes.php';

function k2_player_games_valid_from(string $value): string
{
    $value = trim($value);

    return in_array($value, ['played-days', 'activity-peaks'], true) ? $value : 'played-days';
}

/**
 * @return array{href: string, label: string}
 */
function k2_player_games_from_back_link(string $from, int $playerId): array
{
    if ($from === 'activity-peaks') {
        return [
            'href' => k2_route('lb-activity-peaks'),
            'label' => '← Activity peaks',
        ];
    }

    return [
        'href' => '/player/profile.php?id=' . $playerId . '#played-days',
        'label' => '← Played days',
    ];
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function k2_player_games_with_from_param(array $params, string $from): array
{
    if (k2_player_games_valid_from($from) !== 'played-days') {
        $params['from'] = $from;
    }

    return $params;
}

function k2_player_games_list_anchor_hash(): string
{
    return '#day-games';
}

function k2_player_games_url_with_list_anchor(string $url): string
{
    return $url . k2_player_games_list_anchor_hash();
}
