<?php
/**
 * Tournament entity chevrons — wing-preserving href resolver.
 *
 * @see docs/with-player-stepper-policy.md §5.6
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_tournament_videos_lib.php';
require_once __DIR__ . '/amiga_id_with_url.php';
require_once __DIR__ . '/amiga_tournament_step_catalog.php';

/**
 * @return array{
 *   view: string,
 *   scope_type: string,
 *   scope_key: string,
 *   videos_mode: string
 * }
 */
function amiga_tournament_step_nav_intent_from_request(
    string $scopeType = 'league',
    string $scopeKey = '',
    ?string $pageView = null,
    ?string $videosMode = null,
): array {
    $view = $pageView ?? amiga_tournament_view_from_request() ?? 'event-stats';
    if (!in_array($view, ['event-stats', 'games', 'videos', 'stages', 'standings'], true)) {
        $view = 'event-stats';
    }

    $scopeType = in_array($scopeType, ['league', 'knockout'], true) ? $scopeType : 'league';
    $videosMode = $videosMode ?? amiga_tournament_videos_mode_from_request();
    if (!in_array($videosMode, ['games', 'atmosphere'], true)) {
        $videosMode = 'games';
    }

    return [
        'view' => $view,
        'scope_type' => $scopeType,
        'scope_key' => $scopeKey,
        'videos_mode' => $videosMode,
    ];
}

/**
 * @return array{
 *   is_world_cup: bool,
 *   league_labeled_scopes: list<string>,
 *   knockout_scopes: list<string>,
 *   has_implicit_league: bool,
 *   has_bracket: bool,
 *   has_games: bool,
 *   has_videos: bool
 * }|null
 */
function amiga_tournament_step_target_caps(mysqli $con, int $tournamentId): ?array
{
    $tournament = amiga_tournament_load($con, $tournamentId);
    if ($tournament === null) {
        return null;
    }

    $leagueLabeledScopes = amiga_tournament_list_league_labeled_scopes($con, $tournamentId);
    $knockoutScopes = amiga_tournament_list_scopes($con, $tournamentId, 'knockout');
    $implicitLeagueRows = amiga_tournament_standings_rows($con, $tournamentId, 'league', '');

    return [
        'is_world_cup' => amiga_tournament_is_world_cup($tournament),
        'league_labeled_scopes' => $leagueLabeledScopes,
        'knockout_scopes' => $knockoutScopes,
        'has_implicit_league' => $implicitLeagueRows !== [],
        'has_bracket' => $knockoutScopes !== [],
        'has_games' => amiga_tournament_game_count($con, $tournamentId) > 0,
        'has_videos' => amiga_tournament_has_videos($tournamentId),
    ];
}

/**
 * @param array{
 *   view: string,
 *   scope_type: string,
 *   scope_key: string,
 *   videos_mode: string
 * } $intent
 * @param array{
 *   is_world_cup: bool,
 *   league_labeled_scopes: list<string>,
 *   knockout_scopes: list<string>,
 *   has_implicit_league: bool,
 *   has_bracket: bool,
 *   has_games: bool,
 *   has_videos: bool
 * } $caps
 */
function amiga_tournament_step_resolve_standings_url(
    int $targetId,
    array $intent,
    array $caps,
): string {
    $isWc = $caps['is_world_cup'];

    if ($intent['scope_type'] === 'knockout' && $caps['has_bracket']) {
        $scopeKey = $intent['scope_key'];
        if ($scopeKey === '' || !in_array($scopeKey, $caps['knockout_scopes'], true)) {
            $scopeKey = (string) ($caps['knockout_scopes'][0] ?? '');
        }

        return amiga_tournament_standings_nav_url($targetId, 'knockout', $scopeKey, $isWc);
    }

    if ($intent['scope_type'] === 'league') {
        if ($intent['scope_key'] === '' && $caps['has_implicit_league']) {
            return amiga_tournament_standings_nav_url($targetId, 'league', '', $isWc);
        }
        if ($intent['scope_key'] !== ''
            && in_array($intent['scope_key'], $caps['league_labeled_scopes'], true)) {
            return amiga_tournament_standings_nav_url($targetId, 'league', $intent['scope_key'], $isWc);
        }
    }

    return amiga_tournament_stages_entry_url(
        $targetId,
        $caps['has_implicit_league'],
        $caps['league_labeled_scopes'],
        $caps['has_bracket'],
    );
}

/**
 * @param array{
 *   view: string,
 *   scope_type: string,
 *   scope_key: string,
 *   videos_mode: string
 * } $intent
 */
function amiga_tournament_step_target_url(mysqli $con, int $targetId, array $intent): string
{
    $caps = amiga_tournament_step_target_caps($con, $targetId);
    if ($caps === null) {
        return amiga_tournament_event_stats_url($targetId);
    }

    return match ($intent['view']) {
        'games' => $caps['has_games']
            ? amiga_tournament_games_url($targetId)
            : amiga_tournament_event_stats_url($targetId),
        'videos' => amiga_tournament_step_resolve_videos_url($con, $targetId, $intent, $caps),
        'stages', 'standings' => amiga_tournament_step_resolve_standings_url($targetId, $intent, $caps),
        default => amiga_tournament_event_stats_url($targetId),
    };
}

/**
 * @param array{
 *   view: string,
 *   scope_type: string,
 *   scope_key: string,
 *   videos_mode: string
 * } $intent
 * @param array{
 *   is_world_cup: bool,
 *   league_labeled_scopes: list<string>,
 *   knockout_scopes: list<string>,
 *   has_implicit_league: bool,
 *   has_bracket: bool,
 *   has_games: bool,
 *   has_videos: bool
 * } $caps
 */
function amiga_tournament_step_resolve_videos_url(
    mysqli $con,
    int $targetId,
    array $intent,
    array $caps,
): string {
    if (!$caps['has_videos']) {
        return amiga_tournament_event_stats_url($targetId);
    }

    $wings = amiga_tournament_videos_wings_for_id($con, $targetId);
    $hasGamesWing = $wings['has_games_wing'];
    $hasAtmosphereWing = $wings['has_atmosphere_wing'];
    $mode = amiga_tournament_videos_resolve_mode($intent['videos_mode'], $hasAtmosphereWing, $hasGamesWing);

    return amiga_tournament_videos_url($targetId, $mode);
}

/**
 * @param array{
 *   view: string,
 *   scope_type: string,
 *   scope_key: string,
 *   videos_mode: string
 * } $intent
 */
function amiga_tournament_step_target_href(mysqli $con, int $targetId, array $intent): string
{
    require_once __DIR__ . '/amiga_snapshot_url.php';

    return amiga_tournament_href(amiga_tournament_step_target_url($con, $targetId, $intent));
}

/**
 * 302 to nearest eligible tournament when step filters are active but current id is off-filter.
 * Prefers previous eligible neighbor, else next (same as chevron nearest-neighbor rule).
 *
 * @param array{
 *   view: string,
 *   scope_type: string,
 *   scope_key: string,
 *   videos_mode: string
 * } $intent
 */
function amiga_tournament_apply_step_filter_snap_redirect(
    mysqli $con,
    int $currentId,
    array $intent,
): void {
    if ($currentId < 1) {
        return;
    }

    $filterBag = amiga_tournament_step_filter_bag_from_request($con);
    $catalog = amiga_tournament_step_catalog($con);
    $targetKey = amiga_tournament_step_snap_target_key($con, $catalog, $currentId, $filterBag);
    if ($targetKey === null) {
        return;
    }

    $targetId = (int) $targetKey;
    if ($targetId < 1 || $targetId === $currentId) {
        return;
    }

    header('Location: ' . amiga_tournament_step_target_href($con, $targetId, $intent), true, 302);
    exit;
}