<?php
/**
 * Amiga player hero — same feast shell as online; links stay in Amiga realm.
 * Expects $Name, $Rating, $NumberGames, $rank, $id (optional $Country subtitle).
 */
require_once __DIR__ . '/k2_safety.php';

if (empty($Name)) {
    return;
}

$heroInitial = strtoupper(substr((string) $Name, 0, 1));
$heroRating = isset($Rating) && !k2_db_is_null($Rating) ? k2_fmt_int($Rating, '—') : '—';
$heroGames = isset($NumberGames) ? (int) $NumberGames : 0;
$heroRank = isset($rank) ? '#' . (int) $rank : '—';
$nameEsc = htmlspecialchars((string) $Name, ENT_QUOTES, 'UTF-8');
$lbHref = '/amiga/rating.php';
$heroPlayerId = isset($id) ? (int) $id : (isset($playerId) ? (int) $playerId : 0);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';
$gamesHref = $heroPlayerId > 0 ? k2_amiga_route('amiga-player-games', ['id' => $heroPlayerId]) : '';
$heroCountry = isset($Country) ? trim((string) $Country) : '';
?>
<article class="k2-player-hero k2-player-hero--feast">
	<div class="k2-player-hero__inner">
		<div class="k2-player-hero__media">
			<div class="k2-player-hero__avatar" aria-hidden="true"><?php echo htmlspecialchars($heroInitial, ENT_QUOTES, 'UTF-8'); ?></div>
		</div>
		<div class="k2-player-hero__body">
			<h2 class="k2-player-hero__name"><?php echo $nameEsc; ?></h2>
			<?php if ($heroCountry !== '') { ?>
			<p class="k2-hub-intro" style="margin:0 0 0.5rem"><?php echo htmlspecialchars($heroCountry, ENT_QUOTES, 'UTF-8'); ?></p>
			<?php } ?>
			<div class="k2-player-hero__stats">
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">Rank</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--rank">
						<a class="k2-player-hero__stat-link" href="<?php echo htmlspecialchars($lbHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $heroRank; ?></a>
					</span>
				</div>
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">Rating</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent">
						<a class="k2-player-hero__stat-link" href="<?php echo htmlspecialchars($lbHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $heroRating; ?></a>
					</span>
				</div>
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">Games</span>
					<span class="k2-player-hero__stat-value"><?php
                    if ($gamesHref !== '') {
                        ?><a class="k2-player-hero__stat-link" href="<?php echo htmlspecialchars($gamesHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $heroGames; ?></a><?php
                    } else {
                        echo $heroGames;
                    }
                    ?></span>
				</div>
			</div>
		</div>
	</div>
</article>
