<?php
/**
 * Amiga hub tabs — present-day vs time-travel IA (T13–T16).
 *
 * @see docs/amiga-time-travel-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/amiga_snapshot_url.php';

/**
 * Hub tabs shown when `as=` is active — snapshot-worthy realm surfaces only (T13b).
 *
 * @var list<string>
 */
const K2_AMIGA_HUB_TIME_TRAVEL_TAB_IDS = ['leaderboards', 'world-cups', 'activity', 'hall-of-fame'];

/**
 * Editorial / live-ops paths: direct `?as=` is stripped (302 to present).
 *
 * @var list<string>
 */
const K2_AMIGA_HUB_EDITORIAL_PRESENT_ONLY_PATHS = [
    '/amiga/news.php',
    '/amiga/live-tournaments.php',
    '/amiga/live-tournament.php',
];

/** Present-mode realm home — header **Present day** toggle target (T19). */
const K2_AMIGA_HUB_PRESENT_ENTRY_PATH = '/amiga/news.php';

/** Time-travel realm home — header **Time travel** toggle target from present (T19). */
const K2_AMIGA_HUB_TIME_TRAVEL_ENTRY_PATH = '/amiga/leaderboards/rating.php';

/**
 * @return array<string, array{href: string, label: string}>
 */
function amiga_hub_all_tabs(): array
{
    return [
        'news' => ['href' => '/amiga/news.php', 'label' => 'News'],
        'world-cups' => ['href' => '/amiga/world-cups/chronology/index.php', 'label' => 'World Cups'],
        'leaderboards' => ['href' => '/amiga/leaderboards/rating.php', 'label' => 'Leaderboards'],
        'tournaments' => ['href' => '/amiga/tournaments.php', 'label' => 'Tournaments'],
        'activity' => ['href' => '/amiga/activity.php', 'label' => 'Activity'],
        'hall-of-fame' => ['href' => '/amiga/hall-of-fame.php', 'label' => 'Hall of Fame'],
        'live-tournaments' => ['href' => '/amiga/live-tournaments.php', 'label' => 'Live tournaments'],
        // Future hub Games tab (highlights + vault): present-only — add `'games' => …` here; omit from TIME_TRAVEL_TAB_IDS.
    ];
}

/**
 * @return list<string>
 */
function amiga_hub_present_only_paths(): array
{
    return K2_AMIGA_HUB_EDITORIAL_PRESENT_ONLY_PATHS;
}

function amiga_hub_is_present_only_path(string $path): bool
{
    return in_array(k2_table_path_only($path), amiga_hub_present_only_paths(), true);
}

function amiga_hub_time_travel_entry_path(): string
{
    return K2_AMIGA_HUB_TIME_TRAVEL_ENTRY_PATH;
}

function amiga_hub_present_entry_path(): string
{
    return K2_AMIGA_HUB_PRESENT_ENTRY_PATH;
}

/** Amiga realm home — News in present mode; rating LB with active `as=` in time travel. */
function amiga_realm_home_href(): string
{
    if (!amiga_snapshot_time_travel_active_from_request()) {
        return '/amiga/news.php';
    }

    return amiga_url_with_context(amiga_hub_time_travel_entry_path());
}

/**
 * @return array<string, array{href: string, label: string}>
 */
function amiga_hub_tabs_for_nav(bool $timeTravelActive): array
{
    $tabs = amiga_hub_all_tabs();
    if (!$timeTravelActive) {
        return $tabs;
    }

    $filtered = [];
    foreach (K2_AMIGA_HUB_TIME_TRAVEL_TAB_IDS as $tabId) {
        if (isset($tabs[$tabId])) {
            $filtered[$tabId] = $tabs[$tabId];
        }
    }

    return $filtered;
}

/**
 * Editorial present-only pages drop `as=` — redirect to the same path in present mode.
 */
function amiga_snapshot_redirect_present_only_page(): void
{
    if (!amiga_snapshot_time_travel_active_from_request()) {
        return;
    }

    $path = amiga_snapshot_request_path();
    if (!amiga_hub_is_present_only_path($path)) {
        return;
    }

    $query = $_SERVER['QUERY_STRING'] ?? '';
    /** @var array<string, scalar|null> $params */
    $params = [];
    if ($query !== '') {
        parse_str($query, $params);
    }
    unset($params['as'], $params['wing'], $params['at']);

    $target = k2_table_path_only($path);
    if ($params !== []) {
        $target .= '?' . http_build_query($params);
    }

    header('Location: ' . $target, true, 302);
    exit;
}
