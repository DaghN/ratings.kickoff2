<?php
/**
 * Perf. rating LB inner segments — Best · Top 100 · Perfect.
 *
 * Set $k2AmigaLbPerfRatingView before include: best | top | perfect
 *
 * @see docs/amiga-performance-rating-leaderboard-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_snapshot_url.php';

$k2AmigaLbPerfRatingView = $k2AmigaLbPerfRatingView ?? 'best';

$k2AmigaLbPerfRatingTabs = [
    'best' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-lb-performance-rating-best')),
        'label' => 'Best',
    ],
    'top' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-lb-performance-rating-top')),
        'label' => 'Top 100',
    ],
    'perfect' => [
        'href' => amiga_url_with_context(k2_amiga_route('amiga-lb-performance-rating-perfect')),
        'label' => 'Perfect',
    ],
];
?>
<div class="k2-chrome-tabs k2-lb-perf-rating-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Performance rating views">
<?php foreach ($k2AmigaLbPerfRatingTabs as $viewId => $tab) {
    $isActive = $k2AmigaLbPerfRatingView === $viewId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>