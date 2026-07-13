<?php
/**
 * Amiga News page body — roll + pulse rail.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_news_lib.php';
?>
<section class="k2-amiga-news-room" aria-label="Amiga News">
	<div class="k2-amiga-news-room__layout">
		<div class="k2-amiga-news-room__roll">
<?php k2_amiga_news_render_roll(); ?>
		</div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_news_pulse_rail.inc.php'; ?>
	</div>
</section>