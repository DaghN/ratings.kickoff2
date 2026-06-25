<?php
/**
 * Player Games tab — optional ?from= back-link context (profile played-days vs Activity peaks, etc.).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_routes.php';
require_once __DIR__ . '/k2_safety.php';

function k2_player_games_valid_from(string $value): string
{
    $value = trim($value);

    return in_array($value, ['played-days', 'played-weeks', 'activity-peaks', 'result-streaks', 'profile-bursts', 'profile-games-chart'], true) ? $value : 'played-days';
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
    if ($from === 'result-streaks') {
        return [
            'href' => k2_route('lb-streaks'),
            'label' => '← Streaks',
        ];
    }
    if ($from === 'played-weeks') {
        return [
            'href' => k2_player_profile_href($playerId, 'played-weeks'),
            'label' => '← Played weeks',
        ];
    }
    if ($from === 'profile-bursts') {
        return [
            'href' => k2_player_profile_href($playerId, 'bursts-of-activity'),
            'label' => '← Bursts of activity',
        ];
    }
    if ($from === 'profile-games-chart') {
        return [
            'href' => k2_player_profile_href($playerId, 'games-per-month'),
            'label' => '← Games per month',
        ];
    }

    return [
        'href' => k2_player_profile_href($playerId, 'played-days'),
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
