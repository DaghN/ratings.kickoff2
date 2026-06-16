<?php
/**
 * Activity wing inner segments — Peaks · Participation · In a row.
 * Set $k2LbActivityView before include: peaks | participation | in-a-row
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';

$k2LbActivityView = $k2LbActivityView ?? 'peaks';
$k2LbActivityTabs = [
    'participation' => ['href' => k2_route('lb-activity'), 'label' => 'Participation'],
    'in-a-row' => ['href' => k2_route('lb-activity-in-a-row'), 'label' => 'In a row'],
    'peaks' => ['href' => k2_route('lb-activity-peaks'), 'label' => 'Peaks'],
];
?>
<div class="k2-chrome-tabs k2-lb-activity-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Activity views">
<?php foreach ($k2LbActivityTabs as $viewId => $tab) {
    $isActive = $k2LbActivityView === $viewId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo $tab['label']; ?></a>
<?php } ?>
	</nav>
</div>
