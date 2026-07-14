<?php
/**
 * Amiga player pills — Profile · Opponents · Tournaments · Games.
 * Set $k2AmigaPlayerTabActive and $id before include.
 * Segment bar: .k2-chrome-tabs.k2-player-wing-tabs (tint picker on hub bar only).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/amiga_player_opponents_lib.php';
require_once __DIR__ . '/amiga_player_videos_lib.php';

$k2AmigaPlayerTabActive = $k2AmigaPlayerTabActive ?? 'profile';
$id = isset($id) ? (int) $id : 0;
$k2AmigaPlayerTabs = [
    'profile' => ['href' => k2_amiga_route('amiga-player-profile', ['id' => $id]), 'label' => 'Profile'],
    'opponents' => ['href' => amiga_player_opponents_default_href($id), 'label' => 'Opponents'],
    'tournaments' => ['href' => k2_amiga_route('amiga-player-tournaments', ['id' => $id]), 'label' => 'Tournaments'],
    'games' => ['href' => k2_amiga_route('amiga-player-games', ['id' => $id]), 'label' => 'Games'],
];
$k2AmigaPlayerShowVideosTab = isset($k2AmigaPlayerHasVideos)
    ? (bool) $k2AmigaPlayerHasVideos
    : amiga_player_has_videos(
        $id,
        isset($con) && $con instanceof mysqli ? $con : null,
        amiga_snapshot_context_peek()
    );
if ($id > 0 && $k2AmigaPlayerShowVideosTab) {
    $k2AmigaPlayerTabs['videos'] = [
        'href' => amiga_player_videos_url($id),
        'label' => 'Videos',
    ];
}
?>
<div class="k2-chrome-tabs k2-player-wing-tabs">
	<nav class="k2-chrome-tabs__bar" data-k2-carry-scroll aria-label="Player sections">
<?php foreach ($k2AmigaPlayerTabs as $tabId => $tab) {
    $isActive = $k2AmigaPlayerTabActive === $tabId;
    ?>
		<a href="<?php echo htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8'); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8'); ?></a>
<?php } ?>
	</nav>
</div>
