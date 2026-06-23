<?php
/**
 * Amiga realm hub tabs — present: News · World Cups · Leaderboards · Tournaments · Activity · HoF · Live tournaments (last).
 * Time travel: Leaderboards · World Cups · Activity · Hall of Fame (T13b + WCH12).
 *
 * Set $k2AmigaHubTabActive before include: news | world-cups | leaderboards | tournaments | activity | hall-of-fame | live-tournaments
 *
 * Leaderboards tab → rating wing under /amiga/leaderboards/ (tournament honours is a sub-wing only).
 * Wing nav on all leaderboard pages (includes/amiga_lb_nav.php). No streaks wing.
 *
 * Tint picker matches online hub (realm-neutral). Peer pill scroll: data-k2-carry-scroll.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/amiga_hub_nav_lib.php';

$k2AmigaHubTabActive = $k2AmigaHubTabActive ?? '';
$k2AmigaHubTabs = amiga_hub_tabs_for_nav(amiga_snapshot_time_travel_active_from_request());

include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_accent_pills.inc.php';
?>
<div class="k2-hub-bar">
	<nav class="k2-hub-tabs k2-nav-pills" data-k2-carry-scroll aria-label="Amiga 500 hub">
		<div class="k2-hub-tabs__links">
<?php foreach ($k2AmigaHubTabs as $hubTabId => $tab) {
	$hrefEsc = htmlspecialchars(amiga_url_with_context($tab['href']), ENT_QUOTES, 'UTF-8');
	$labelEsc = htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8');
	$activeClass = $k2AmigaHubTabActive === $hubTabId ? ' is-active' : '';
?>
			<a href="<?php echo $hrefEsc; ?>" class="k2-hub-tabs__btn<?php echo $activeClass; ?>"><?php echo $labelEsc; ?></a>
<?php } ?>
		</div>
		<div class="k2-hub-tabs__tune k2-nav-tune">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_tint_picker.php'; ?>
		</div>
	</nav>
</div>
<script type="text/javascript" src="/js/k2-tint-toggle.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-tint-toggle.js'); ?>" defer="defer"></script>
