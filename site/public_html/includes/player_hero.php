<?php
/**
 * Player hero scaffold — expects $Name, optional $Rating, $NumberGames, $Display, $rank,
 * optional $heroMilestoneCounts (from player_hero_vars.php or individual1 feast load), $id for profile link.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_player_filters.php';

if (empty($Name)) {
	return;
}
$heroInitial = strtoupper(substr((string) $Name, 0, 1));
$heroDisplay = isset($Display) && (int) $Display === 1;
$heroRank = $heroDisplay && isset($rank) ? '#' . (int) $rank : '—';
$heroRating = $heroDisplay && isset($Rating) && !k2_db_is_null($Rating) ? k2_fmt_int($Rating, '—') : '—';
$heroGames = isset($NumberGames) && !k2_db_is_null($NumberGames) ? (int) $NumberGames : 0;
$nameEsc = htmlspecialchars((string) $Name, ENT_QUOTES, 'UTF-8');
$heroMs = isset($heroMilestoneCounts) && is_array($heroMilestoneCounts) ? $heroMilestoneCounts : null;
$heroMsPlayerId = isset($id) ? (int) $id : (isset($playerId) ? (int) $playerId : 0);
$heroProfileHref = $heroMsPlayerId > 0 ? k2_player_profile_href($heroMsPlayerId) : '';
$heroLbRatingHref = k2_lb_table_href('lb-rating');
$heroLbGamesPeakHref = k2_lb_table_href('lb-activity-peaks', ['k2_sort' => '3', 'k2_dir' => 'desc']);
$heroLbMilestonesHref = k2_lb_table_href('lb-milestones');
$heroRankLinked = $heroDisplay && isset($rank);
$heroRatingLinked = $heroDisplay && isset($Rating) && !k2_db_is_null($Rating);
?>
<div id="<?php echo k2_h(K2_PLAYER_PAGE_FRAGMENT); ?>" class="k2-player-page-anchor" tabindex="-1"></div>
<article class="k2-player-hero k2-player-hero--feast">
	<div class="k2-player-hero__inner">
		<div class="k2-player-hero__media"><?php
			if ($heroProfileHref !== '') {
				?><a class="k2-player-hero__avatar k2-player-hero__avatar-link" href="<?php echo htmlspecialchars($heroProfileHref, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo $nameEsc; ?>"><?php echo htmlspecialchars($heroInitial, ENT_QUOTES, 'UTF-8'); ?></a><?php
			} else {
				?><div class="k2-player-hero__avatar" aria-hidden="true"><?php echo htmlspecialchars($heroInitial, ENT_QUOTES, 'UTF-8'); ?></div><?php
			}
		?></div>
		<div class="k2-player-hero__body">
			<h2 class="k2-player-hero__name"><?php
				if ($heroProfileHref !== '') {
					?><a class="k2-player-hero__name-link" href="<?php echo htmlspecialchars($heroProfileHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $nameEsc; ?></a><?php
				} else {
					echo $nameEsc;
				}
			?></h2>
			<div class="k2-player-hero__stats">
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">Rank</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--rank"><?php
						if ($heroRankLinked) {
							?><a class="k2-player-hero__stat-link" href="<?php echo htmlspecialchars($heroLbRatingHref, ENT_QUOTES, 'UTF-8'); ?>">#<?php echo (int) $rank; ?></a><?php
						} else {
							echo $heroRank;
						}
					?></span>
				</div>
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">Rating</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php
						if ($heroRatingLinked) {
							?><a class="k2-player-hero__stat-link" href="<?php echo htmlspecialchars($heroLbRatingHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_fmt_int($Rating, '—'); ?></a><?php
						} else {
							echo $heroRating;
						}
					?></span>
				</div>
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">Games</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><a class="k2-player-hero__stat-link" href="<?php echo htmlspecialchars($heroLbGamesPeakHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $heroGames; ?></a></span>
				</div>
				<?php if ($heroMs !== null && $heroMsPlayerId > 0) {
					$msTotal = (int) $heroMs['total'];
					?>
				<div class="k2-player-hero__stat k2-player-hero__stat--milestones">
					<span class="k2-player-hero__stat-label">Milestones</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--milestones">
						<a class="k2-player-hero__stat-link k2-player-hero__milestones" href="<?php echo htmlspecialchars($heroLbMilestonesHref, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo $msTotal; ?> milestones unlocked"><?php echo $msTotal; ?></a>
					</span>
				</div>
				<?php } ?>
			</div>
		</div>
	</div>
</article>
