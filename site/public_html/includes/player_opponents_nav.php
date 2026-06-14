<?php
/**
 * Opponents inner sub-tabs — W/D/L · Goals · DDs · Head-to-head.
 * Set $k2PlayerOpponentsView and $id before include.
 */
declare(strict_types=1);

require_once __DIR__ . '/player_opponents_lib.php';

$k2PlayerOpponentsView = player_opponents_parse_view($k2PlayerOpponentsView ?? null);
$id = isset($id) ? (int) $id : 0;

$k2PlayerOpponentsTabs = [
    'wdl' => 'W/D/L',
    'goals' => 'Goals',
    'dds' => 'DDs',
    'h2h' => 'Head-to-head',
];
?>
<div class="k2-chrome-tabs k2-player-opponents">
	<nav class="k2-player-opponents__nav" data-k2-carry-scroll aria-label="Opponent views">
		<div class="k2-chrome-tabs__bar k2-player-opponents__bar" role="tablist">
<?php foreach ($k2PlayerOpponentsTabs as $viewId => $label) {
    $active = $k2PlayerOpponentsView === $viewId;
    ?>
			<a
				href="<?php echo htmlspecialchars(player_opponents_href($id, $viewId), ENT_QUOTES, 'UTF-8'); ?>"
				class="k2-chrome-tabs__tab<?php echo $active ? ' is-active' : ''; ?>"
				role="tab"
				aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
			><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
		</div>
	</nav>
