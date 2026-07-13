<?php
/**
 * Amiga leaderboard wing tabs (Tier A career stats + tournament honours).
 *
 * Set $k2AmigaLbWingActive before include:
 * rating | goals | double-digits | victims | tournament-honours | calendar-geo | peak-rating | performance-rating
 *
 * No streaks wing (unknown within-day play order; amiga-data-contract.md).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/amiga_lb_lib.php';

$k2AmigaLbWingActive = $k2AmigaLbWingActive ?? 'rating';

$k2AmigaLbWingTabs = [
    'rating' => ['href' => '/amiga/leaderboards/rating.php', 'label' => 'Rating'],
    'goals' => ['href' => '/amiga/leaderboards/goals.php', 'label' => 'Goals'],
    'double-digits' => ['href' => '/amiga/leaderboards/double-digits.php', 'label' => 'DDs &amp; CSs'],
    'victims' => ['href' => '/amiga/leaderboards/victims.php', 'label' => 'Victims &amp; Culprits'],
    'tournament-honours' => [
        'href' => '/amiga/leaderboards/tournament-honours.php',
        'label' => 'Tournament honours',
    ],
    'calendar-geo' => [
        'href' => '/amiga/leaderboards/calendar-geo.php',
        'label' => 'Calendar &amp; geography',
    ],
    'peak-rating' => ['href' => '/amiga/leaderboards/peak-rating.php', 'label' => 'Peak rating'],
    'performance-rating' => [
        'href' => '/amiga/leaderboards/performance-rating/best.php',
        'label' => 'Perf. rating',
    ],
];

$k2HubChapterTitle = 'Leaderboards';
$k2HubChapterLede = amiga_lb_chapter_lede_html_for_request();
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
?>
<div class="k2-chrome-tabs k2-amiga-lb-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Amiga leaderboard view">
<?php foreach ($k2AmigaLbWingTabs as $wingId => $tab) {
    $hrefEsc = htmlspecialchars(amiga_url_with_context($tab['href']), ENT_QUOTES, 'UTF-8');
    $activeClass = $k2AmigaLbWingActive === $wingId ? ' is-active' : '';
    ?>
		<a href="<?php echo $hrefEsc; ?>" class="k2-chrome-tabs__tab<?php echo $activeClass; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
<?php
require_once __DIR__ . '/lb_player_filters.php';
echo k2_lb_table_anchor_markup();
?>
