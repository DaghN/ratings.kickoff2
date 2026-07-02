<?php
/**
 * Hub section chapter — title + lede above sub-nav or page body.
 *
 * Set before include:
 *   $k2HubChapterTitle (required)
 *   $k2HubChapterLede (optional; default placeholder)
 *   $k2HubChapterList (optional raw HTML, e.g. <ul class="k2-hub-chapter__list">…</ul>)
 *   $k2HubChapterSupplement (optional raw HTML after lede, e.g. a second intro paragraph)
 *   $k2HubChapterNav (optional raw HTML for sub-nav inside header, e.g. filter pills)
 */
declare(strict_types=1);

if (!isset($k2HubChapterTitle) || $k2HubChapterTitle === '') {
    return;
}

$k2HubChapterLede = $k2HubChapterLede ?? 'Section intro copy to follow.';
$k2HubChapterList = $k2HubChapterList ?? '';
$k2HubChapterSupplement = $k2HubChapterSupplement ?? '';
$k2HubChapterNav = $k2HubChapterNav ?? '';
?>
<header class="k2-hub-chapter">
	<h1 class="k2-hub-chapter__title"><?php echo htmlspecialchars($k2HubChapterTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
<?php if ($k2HubChapterLede !== '') { ?>
	<p class="k2-hub-chapter__lede"><?php echo $k2HubChapterLede; ?></p>
<?php } ?>
<?php if ($k2HubChapterSupplement !== '') { ?>
	<?php echo $k2HubChapterSupplement; ?>
<?php } ?>
<?php if ($k2HubChapterList !== '') { ?>
	<?php echo $k2HubChapterList; ?>
<?php } ?>
<?php if ($k2HubChapterNav !== '') { ?>
	<?php echo $k2HubChapterNav; ?>
<?php } ?>
</header>
