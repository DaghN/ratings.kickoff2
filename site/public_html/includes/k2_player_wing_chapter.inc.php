<?php
/**
 * Player inner-wing chapter — primary title + muted lede (+ optional meta strip).
 *
 * Set before include:
 *   $k2PlayerWingChapterTitle (required)
 *   $k2PlayerWingChapterLede (optional HTML)
 *   $k2PlayerWingChapterMeta (optional HTML fact strip below lede)
 */
declare(strict_types=1);

if (!isset($k2PlayerWingChapterTitle) || $k2PlayerWingChapterTitle === '') {
    return;
}

$k2PlayerWingChapterLede = $k2PlayerWingChapterLede ?? '';
$k2PlayerWingChapterMeta = $k2PlayerWingChapterMeta ?? '';
?>
<header class="k2-player-wing-chapter">
	<h2 class="k2-player-wing-chapter__title"><?php echo htmlspecialchars($k2PlayerWingChapterTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
<?php if ($k2PlayerWingChapterLede !== '') { ?>
	<p class="k2-player-wing-chapter__lede"><?php echo $k2PlayerWingChapterLede; ?></p>
<?php } ?>
<?php if ($k2PlayerWingChapterMeta !== '') { ?>
	<p class="k2-player-wing-chapter__meta"><?php echo $k2PlayerWingChapterMeta; ?></p>
<?php } ?>
</header>
