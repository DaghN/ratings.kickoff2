<?php
/**
 * Amiga Opponents inner sub-tabs — Head-to-head · W/D/L · Goals · DDs + grain segment.
 * Set $k2AmigaPlayerOpponentsView, $k2AmigaPlayerOpponentsGrain, and $id before include.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_player_opponents_lib.php';

$k2AmigaPlayerOpponentsView = amiga_player_opponents_parse_view($k2AmigaPlayerOpponentsView ?? null);
$k2AmigaPlayerOpponentsGrain = amiga_player_opponents_parse_grain($k2AmigaPlayerOpponentsGrain ?? null);
$id = isset($id) ? (int) $id : 0;

$k2AmigaPlayerOpponentsTabs = [
    'h2h' => 'Head-to-head',
    'wdl' => 'W/D/L',
    'goals' => 'Goals',
    'dds' => 'DDs',
];

$k2AmigaPlayerOpponentsGrainTabs = [
    'player' => 'vs Player',
    'country' => 'vs Country',
];
?>
<div class="k2-chrome-tabs k2-player-opponents">
	<nav class="k2-player-opponents__nav" data-k2-carry-scroll aria-label="Opponent views">
		<div class="k2-player-opponents__nav-row">
			<div class="k2-chrome-tabs k2-player-opponents__wings">
				<div class="k2-chrome-tabs__bar k2-player-opponents__bar" role="tablist" aria-label="Opponent wing">
<?php foreach ($k2AmigaPlayerOpponentsTabs as $viewId => $label) {
    $active = $k2AmigaPlayerOpponentsView === $viewId;
    ?>
					<a
						href="<?php echo htmlspecialchars(amiga_player_opponents_href($id, $viewId, $k2AmigaPlayerOpponentsGrain), ENT_QUOTES, 'UTF-8'); ?>"
						class="k2-chrome-tabs__tab<?php echo $active ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo $active ? 'true' : 'false'; ?>"
					><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
				</div>
			</div>
			<div class="k2-chrome-tabs k2-player-opponents__grain">
				<div class="k2-chrome-tabs__bar k2-player-opponents__grain-bar" role="tablist" aria-label="Opponent grouping">
<?php foreach ($k2AmigaPlayerOpponentsGrainTabs as $grainId => $label) {
    $grainActive = $k2AmigaPlayerOpponentsGrain === $grainId;
    ?>
					<a
						href="<?php echo htmlspecialchars(amiga_player_opponents_href($id, $k2AmigaPlayerOpponentsView, $grainId), ENT_QUOTES, 'UTF-8'); ?>"
						class="k2-chrome-tabs__tab<?php echo $grainActive ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo $grainActive ? 'true' : 'false'; ?>"
					><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
				</div>
			</div>
		</div>
	</nav>
