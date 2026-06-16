<?php
/**
 * Milestones hub sub-navigation — Recent · Catalog.
 * Set $k2MsHubView before include: recent | catalog | null | '' (detail — neither active).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';

$k2MsHubView = $k2MsHubView ?? 'recent';
$k2MsHubTabs = [
	'recent' => ['href' => k2_route('milestones-recent'), 'label' => 'Recent'],
	'catalog' => ['href' => k2_route('milestones-catalog'), 'label' => 'Catalog'],
];
?>
<div class="k2-chrome-tabs k2-ms-hub-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Milestones hub">
<?php foreach ($k2MsHubTabs as $viewId => $tab) {
	$isActive = $k2MsHubView !== null && $k2MsHubView !== '' && $k2MsHubView === $viewId;
	?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
