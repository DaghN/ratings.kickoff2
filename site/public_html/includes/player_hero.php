<?php
/**
 * Player hero scaffold — expects $Name, optional $Rating, $NumberGames, $Display, $rank,
 * optional $heroMilestoneCounts (from player_hero_vars.php or individual1 feast load), $id for garden link.
 */
if (empty($Name)) {
	return;
}
$heroInitial = strtoupper(substr((string) $Name, 0, 1));
$heroDisplay = isset($Display) && (int) $Display === 1;
$heroRank = $heroDisplay && isset($rank) ? '#' . (int) $rank : '—';
$heroRating = $heroDisplay && isset($Rating) ? round($Rating) : '—';
$heroGames = isset($NumberGames) ? (int) $NumberGames : 0;
$nameEsc = htmlspecialchars((string) $Name, ENT_QUOTES, 'UTF-8');
$heroMs = isset($heroMilestoneCounts) && is_array($heroMilestoneCounts) ? $heroMilestoneCounts : null;
$heroMsPlayerId = isset($id) ? (int) $id : (isset($playerId) ? (int) $playerId : 0);
$heroMsCatalogTotal = isset($heroMsCatalogTotal) ? (int) $heroMsCatalogTotal : 0;
?>
<article class="k2-player-hero k2-player-hero--feast">
	<div class="k2-player-hero__inner">
		<div class="k2-player-hero__media">
			<div class="k2-player-hero__avatar" aria-hidden="true"><?php echo htmlspecialchars($heroInitial, ENT_QUOTES, 'UTF-8'); ?></div>
		</div>
		<div class="k2-player-hero__body">
			<h2 class="k2-player-hero__name"><?php echo $nameEsc; ?></h2>
			<div class="k2-player-hero__stats">
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">Rank</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--rank"><?php echo $heroRank; ?></span>
				</div>
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">Rating</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php echo $heroRating; ?></span>
				</div>
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">Games</span>
					<span class="k2-player-hero__stat-value"><?php echo $heroGames; ?></span>
				</div>
				<?php if ($heroMs !== null && $heroMsPlayerId > 0) {
					$gardenHref = 'individual_milestones.php?id=' . $heroMsPlayerId;
					$msTotal = (int) $heroMs['total'];
					?>
				<div class="k2-player-hero__stat k2-player-hero__stat--milestones">
					<span class="k2-player-hero__stat-label">Milestones</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--milestones">
						<a class="k2-player-hero__milestones" href="<?php echo htmlspecialchars($gardenHref, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo $msTotal; ?> of <?php echo $heroMsCatalogTotal; ?> milestones unlocked">
							<span class="k2-player-hero__milestones-row">
								<span class="k2-player-hero__milestones-total"><?php echo $msTotal; ?>/<?php echo $heroMsCatalogTotal; ?></span>
								<span class="k2-player-hero__milestones-sep" aria-hidden="true">·</span>
								<span class="k2-player-hero__milestones-tier k2-player-hero__milestones-tier--pitch" title="Aspirational"><?php echo (int) $heroMs['aspirational']; ?></span>
								<span class="k2-player-hero__milestones-sep" aria-hidden="true">·</span>
								<span class="k2-player-hero__milestones-tier k2-player-hero__milestones-tier--chrome" title="Dedicated"><?php echo (int) $heroMs['dedicated']; ?></span>
								<span class="k2-player-hero__milestones-sep" aria-hidden="true">·</span>
								<span class="k2-player-hero__milestones-tier k2-player-hero__milestones-tier--amber" title="Accomplished"><?php echo (int) $heroMs['accomplished']; ?></span>
								<span class="k2-player-hero__milestones-sep" aria-hidden="true">·</span>
								<span class="k2-player-hero__milestones-tier k2-player-hero__milestones-tier--holo" title="Legendary"><?php echo (int) $heroMs['legendary']; ?></span>
							</span>
						</a>
					</span>
				</div>
				<?php } ?>
			</div>
		</div>
	</div>
</article>
