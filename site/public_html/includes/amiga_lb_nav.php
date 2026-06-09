<?php
/**
 * Amiga leaderboard wing tabs (subset of online lb_nav).
 *
 * Set $k2AmigaLbWingActive before include: rating | tournament-honours
 */
declare(strict_types=1);

$k2AmigaLbWingActive = $k2AmigaLbWingActive ?? 'tournament-honours';

$k2AmigaLbWingTabs = [
    'rating' => ['href' => '/amiga/rating.php', 'label' => 'Rating'],
    'tournament-honours' => [
        'href' => '/amiga/leaderboards/tournament-honours.php',
        'label' => 'Tournament honours',
    ],
];
?>
<div class="k2-chrome-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Amiga leaderboard view">
<?php foreach ($k2AmigaLbWingTabs as $wingId => $tab) {
    $hrefEsc = htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8');
    $labelEsc = $tab['label'];
    $activeClass = $k2AmigaLbWingActive === $wingId ? ' is-active' : '';
    ?>
		<a href="<?php echo $hrefEsc; ?>" class="k2-chrome-tabs__tab<?php echo $activeClass; ?>"><?php echo $labelEsc; ?></a>
<?php } ?>
	</nav>
</div>
