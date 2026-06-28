<?php
/**
 * Amiga leaderboard wing tabs (Tier A career stats + tournament honours).
 *
 * Set $k2AmigaLbWingActive before include:
 * rating | world-cups | tournament-honours | calendar-geo | goals | double-digits | victims | peak-rating | performance-rating
 *
 * No streaks wing (unknown within-day play order; amiga-data-contract.md).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_snapshot_url.php';

$k2AmigaLbWingActive = $k2AmigaLbWingActive ?? 'rating';

$k2AmigaLbWingTabs = [
    'rating' => ['href' => '/amiga/leaderboards/rating.php', 'label' => 'Rating'],
    'world-cups' => [
        'href' => '/amiga/leaderboards/world-cups/honours.php',
        'label' => 'World Cups',
    ],
    'tournament-honours' => [
        'href' => '/amiga/leaderboards/tournament-honours.php',
        'label' => 'Tournament honours',
    ],
    'calendar-geo' => [
        'href' => '/amiga/leaderboards/calendar-geo.php',
        'label' => 'Calendar &amp; geography',
    ],
    'goals' => ['href' => '/amiga/leaderboards/goals.php', 'label' => 'Goals'],
    'double-digits' => ['href' => '/amiga/leaderboards/double-digits.php', 'label' => 'DDs &amp; CSs'],
    'victims' => ['href' => '/amiga/leaderboards/victims.php', 'label' => 'Victims &amp; Culprits'],
    'peak-rating' => ['href' => '/amiga/leaderboards/peak-rating.php', 'label' => 'Peak rating'],
    'performance-rating' => [
        'href' => '/amiga/leaderboards/performance-rating/best.php',
        'label' => 'Perf. rating',
    ],
];
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
