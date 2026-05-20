<?php
/**
 * Player hero scaffold — expects $Name, optional $Rating, $PeakRating, $NumberGames, $Display
 */
if (empty($Name)) {
	return;
}
$heroInitial = strtoupper(substr((string) $Name, 0, 1));
$heroRating = (isset($Display) && (int) $Display === 1 && isset($Rating)) ? round($Rating) : '—';
$heroPeak = (isset($Display) && (int) $Display === 1 && isset($PeakRating) && (float) $PeakRating != 0) ? round($PeakRating) : '—';
$heroGames = isset($NumberGames) ? (int) $NumberGames : 0;
$nameEsc = htmlspecialchars((string) $Name, ENT_QUOTES, 'UTF-8');
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
					<span class="k2-player-hero__stat-label">Rating</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php echo $heroRating; ?></span>
				</div>
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">Peak</span>
					<span class="k2-player-hero__stat-value"><?php echo $heroPeak; ?></span>
				</div>
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">Games</span>
					<span class="k2-player-hero__stat-value"><?php echo $heroGames; ?></span>
				</div>
			</div>
		</div>
	</div>
</article>
