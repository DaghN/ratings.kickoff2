<?php
/**
 * Amiga player hero — same feast shell as online; links stay in Amiga realm.
 * Expects $Name, $Rating, $NumberGames, $rank, $id (optional $Country stat column).
 */
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';

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
$heroFlagMeta = $heroCountry !== '' ? k2_amiga_country_flag_meta($heroCountry) : null;
$lbBackHref = '/amiga/leaderboards/rating.php';
?>
<p class="k2-page-nav__up k2-status-panel__meta">
	<a class="k2-link-star k2-status-panel__more" href="<?php echo htmlspecialchars($lbBackHref, ENT_QUOTES, 'UTF-8'); ?>">&larr; Leaderboards</a>
</p>
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
				<?php if ($heroCountry !== '') { ?>
				<div class="k2-player-hero__stat k2-player-hero__stat--country">
					<span class="k2-player-hero__stat-label">Country</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--country"><?php
                    if ($heroFlagMeta !== null) {
                        ?><span class="k2-player-hero__country-flag"><?php
                        echo k2_amiga_country_flag_img($heroCountry, [
                            'class' => 'k2-player-hero__country-flag-img',
                            'decorative' => false,
                        ]);
                        ?></span><?php
                    } else {
                        echo htmlspecialchars($heroCountry, ENT_QUOTES, 'UTF-8');
                    }
                    ?></span>
				</div>
				<?php } ?>
			</div>
		</div>
	</div>
</article>

