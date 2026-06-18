<?php
/**
 * Milestones inner sub-tabs — Garden · Chronology.
 * Set $k2PlayerMilestonesView and $id before include.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once __DIR__ . '/player_milestones_lib.php';

$k2PlayerMilestonesView = player_milestones_parse_view($k2PlayerMilestonesView ?? null);
$id = isset($id) ? (int) $id : 0;

$k2PlayerMilestonesTabs = [
    'garden' => 'Garden',
    'chronology' => 'Chronology',
];
?>
<div class="k2-chrome-tabs k2-player-milestones">
	<nav class="k2-player-milestones__nav" data-k2-carry-scroll aria-label="Milestone views">
		<div class="k2-chrome-tabs__bar k2-player-milestones__bar" role="tablist">
<?php foreach ($k2PlayerMilestonesTabs as $viewId => $label) {
    $active = $k2PlayerMilestonesView === $viewId;
    ?>
			<a
				href="<?php echo k2_h(player_milestones_href($id, $viewId)); ?>"
				class="k2-chrome-tabs__tab<?php echo $active ? ' is-active' : ''; ?>"
				role="tab"
				aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
			><?php echo k2_h($label); ?></a>
<?php } ?>
		</div>
	</nav>
