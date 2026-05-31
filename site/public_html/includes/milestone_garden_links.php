<?php
/**
 * Milestone unlock event UI register (link + context per milestone_key).
 *
 * Spec: docs/milestones-unlock-event-ui.md
 * Data: data/milestone_garden_links.json · docs/milestones-garden-links.md
 * Regenerate: python scripts/oneoff/build_milestone_garden_links.py
 */
declare(strict_types=1);

/** Server Recent + garden date line — link only, no context prose. */
const K2_MILESTONE_EVENT_SURFACE_COMPACT = 'compact';

/** milestone.php achievers table — full context rules. */
const K2_MILESTONE_EVENT_SURFACE_DETAIL = 'detail';

/** @var array<string, array<string, string>>|null */
$GLOBALS['_k2_milestone_garden_links'] = null;

function k2_milestone_garden_links_json_path(): ?string
{
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $candidates = [
        dirname(__DIR__, 3) . '/data/milestone_garden_links.json',
        $docRoot . '/staging-data/milestone_garden_links.json',
        dirname($docRoot) . '/data/milestone_garden_links.json',
    ];
    foreach ($candidates as $path) {
        if ($path !== '' && is_readable($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * @return array{event_link: string, event_context: string, garden_link: string, notes: string, event_context_label: string}
 */
function k2_milestone_unlock_event_entry(string $milestoneKey): array
{
    if ($GLOBALS['_k2_milestone_garden_links'] === null) {
        $path = k2_milestone_garden_links_json_path();
        if ($path === null) {
            $GLOBALS['_k2_milestone_garden_links'] = [];
        } else {
            $raw = json_decode((string) file_get_contents($path), true);
            $GLOBALS['_k2_milestone_garden_links'] = is_array($raw['keys'] ?? null) ? $raw['keys'] : [];
        }
    }
    $map = $GLOBALS['_k2_milestone_garden_links'];
    if (isset($map[$milestoneKey]) && is_array($map[$milestoneKey])) {
        $row = $map[$milestoneKey];
        $eventLink = (string) ($row['event_link'] ?? $row['garden_link'] ?? 'game');

        return [
            'event_link' => $eventLink,
            'event_context' => (string) ($row['event_context'] ?? 'match_line'),
            'garden_link' => $eventLink,
            'notes' => (string) ($row['notes'] ?? ''),
            'event_context_label' => (string) ($row['event_context_label'] ?? ''),
        ];
    }

    return [
        'event_link' => 'game',
        'event_context' => 'match_line',
        'garden_link' => 'game',
        'notes' => '',
        'event_context_label' => '',
    ];
}

/**
 * @return array{garden_link: string, event_link: string, event_context: string, notes: string}
 */
function k2_milestone_garden_link_entry(string $milestoneKey): array
{
    return k2_milestone_unlock_event_entry($milestoneKey);
}

/**
 * Qualifying UTC calendar day for player_day_games (achieved_at = start of next UTC day).
 */
function k2_milestone_qualifying_utc_day_from_close(?string $achievedAt): ?string
{
    if ($achievedAt === null || $achievedAt === '') {
        return null;
    }
    $ts = strtotime($achievedAt . ' UTC');
    if ($ts === false) {
        return null;
    }

    return gmdate('Y-m-d', $ts - 86400);
}

/**
 * Event link HTML for one unlock row (all surfaces).
 *
 * @param array<string, mixed> $row player_milestones fields + milestone_key
 */
function k2_milestone_unlock_event_link_html(int $playerId, array $row): ?string
{
    $mKey = (string) ($row['milestone_key'] ?? '');
    $profile = k2_milestone_unlock_event_entry($mKey);
    $kind = $profile['event_link'];

    if ($kind === 'none') {
        return null;
    }

    if ($kind === 'player_day_games') {
        $day = k2_milestone_qualifying_utc_day_from_close(
            isset($row['achieved_at']) ? (string) $row['achieved_at'] : null
        );
        if ($day === null || $playerId < 1) {
            return null;
        }
        $href = 'individual3.php?id=' . $playerId . '&day=' . rawurlencode($day);

        return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">Games</a>';
    }

    if ($kind === 'league') {
        return k2_milestone_source_link_html($row);
    }

    return k2_milestone_source_link_html($row);
}

/**
 * Garden card — alias for unlock event link.
 *
 * @param array<string, mixed> $row
 */
function k2_milestone_garden_link_html(int $playerId, array $row): ?string
{
    return k2_milestone_unlock_event_link_html($playerId, $row);
}

/**
 * One-line day context for player_day_games keys.
 */
function k2_milestone_day_games_context_label(string $milestoneKey): string
{
    $label = k2_milestone_unlock_event_entry($milestoneKey)['event_context_label'];
    if ($label !== '') {
        return $label;
    }

    return 'All rated games that UTC day';
}
