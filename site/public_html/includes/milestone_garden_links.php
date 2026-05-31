<?php
/**
 * Garden deep-link profiles per milestone_key (UX register).
 *
 * Source: data/milestone_garden_links.json · docs/milestones-garden-links.md
 * Regenerate JSON + doc: python scripts/oneoff/build_milestone_garden_links.php
 */
declare(strict_types=1);

/** @var array<string, array{garden_link: string, notes: string}>|null */
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
 * @return array{garden_link: string, notes: string}
 */
function k2_milestone_garden_link_entry(string $milestoneKey): array
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
        return [
            'garden_link' => (string) ($map[$milestoneKey]['garden_link'] ?? 'game'),
            'notes' => (string) ($map[$milestoneKey]['notes'] ?? ''),
        ];
    }

    return ['garden_link' => 'game', 'notes' => ''];
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
 * Garden card link HTML for one unlocked milestone (profile garden).
 *
 * @param array<string, mixed> $row player_milestones + definition join row
 */
function k2_milestone_garden_link_html(int $playerId, array $row): ?string
{
    $mKey = (string) ($row['milestone_key'] ?? '');
    $profile = k2_milestone_garden_link_entry($mKey);
    $kind = $profile['garden_link'];

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

    // game (default) — same as legacy source_kind=game
    return k2_milestone_source_link_html($row);
}
