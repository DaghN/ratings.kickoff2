<?php
/**
 * Milestones inner-wing chapter copy (Garden · Chronology).
 */
declare(strict_types=1);

require_once __DIR__ . '/player_milestones_lib.php';

function player_milestones_wing_chapter_title(string $view): string
{
    return match (player_milestones_parse_view($view)) {
        'chronology' => 'Milestone chronology',
        'garden' => 'Milestone garden',
        default => 'Milestone garden',
    };
}

function player_milestones_wing_chapter_lede_html(string $view, int $playerId, string $playerName): string
{
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';

    $view = player_milestones_parse_view($view);
    $nameHtml = $playerId > 0 && $playerName !== ''
        ? k2_player_link($playerId, $playerName)
        : htmlspecialchars($playerName !== '' ? $playerName : 'This player', ENT_QUOTES, 'UTF-8');

    if ($view === 'chronology') {
        return 'Every unlock has a moment. Chronology will walk '
            . $nameHtml
            . '\'s milestones in the order they arrived — a timeline beside the garden\'s map. Until that feed ships, unlocked cards in the garden still show when each feat landed.';
    }

    $catalogHref = htmlspecialchars(k2_route('milestones-catalog'), ENT_QUOTES, 'UTF-8');

    return 'Along '
        . $nameHtml
        . '\'s rated career, milestones mark the feats that stuck — streaks, medals, firsts, and the slow grinds. The garden lays out the full catalog tier by tier: lit cards you have unlocked, greyed goals still waiting. The site <a href="'
        . $catalogHref
        . '">Milestones catalog</a> walks the same set for everyone.';
}

function player_milestones_wing_chapter_meta_html(
    string $view,
    int $numberGames,
    ?array $counts,
    int $catalogTotal
): string {
    $view = player_milestones_parse_view($view);

    if ($view === 'chronology') {
        return 'Chronological unlock feed — coming soon.';
    }

    if ($numberGames < 1) {
        return 'No rated games yet — milestones unlock once you join the ladder.';
    }

    if ($counts === null) {
        return '';
    }

    $unlocked = (int) ($counts['total'] ?? 0);

    return '<span class="k2-ms-wing-chapter__unlocked">' . $unlocked . '</span> of '
        . max(0, $catalogTotal) . ' milestones unlocked.';
}

function player_milestones_render_wing_chapter(
    string $view,
    int $playerId,
    string $playerName,
    int $numberGames,
    ?array $counts,
    int $catalogTotal
): void {
    $k2PlayerWingChapterTitle = player_milestones_wing_chapter_title($view);
    $k2PlayerWingChapterLede = player_milestones_wing_chapter_lede_html($view, $playerId, $playerName);
    $k2PlayerWingChapterMeta = player_milestones_wing_chapter_meta_html($view, $numberGames, $counts, $catalogTotal);
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_wing_chapter.inc.php';
}
