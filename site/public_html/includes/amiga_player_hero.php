<?php
/**
 * Amiga player hero — same feast shell as online; links stay in Amiga realm.
 * Expects $Name, $Rating, $NumberGames, $rank, $id (optional $Country stat column).
 */
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_lb_lib.php';

if (empty($Name)) {
    return;
}

$heroInitial = strtoupper(substr((string) $Name, 0, 1));
$heroPreDebut = !empty($k2AmigaPlayerPreDebut);
$heroDisplay = !$heroPreDebut && isset($Display) && (int) $Display === 1;
$heroRating = $heroPreDebut
    ? '—'
    : ($heroDisplay && isset($Rating) && !k2_db_is_null($Rating) ? k2_fmt_int($Rating, '—') : '—');
$heroGames = $heroPreDebut
    ? '—'
    : (isset($NumberGames) && !k2_db_is_null($NumberGames) ? (int) $NumberGames : 0);
$heroRank = $heroPreDebut ? '—' : ($heroDisplay && isset($rank) && !k2_db_is_null($rank) ? '#' . (int) $rank : '—');
$nameEsc = htmlspecialchars((string) $Name, ENT_QUOTES, 'UTF-8');
$heroPlayerId = isset($id) ? (int) $id : (isset($playerId) ? (int) $playerId : 0);
$heroProfileHref = $heroPlayerId > 0 ? k2_amiga_route('amiga-player-profile', ['id' => $heroPlayerId]) : '';
$heroGamesHref = $heroPlayerId > 0 ? k2_amiga_route('amiga-player-games', ['id' => $heroPlayerId]) : '';
$heroLbRatingHref = amiga_lb_table_href('/amiga/leaderboards/rating.php');
$heroRankLinked = !$heroPreDebut && $heroDisplay && isset($rank) && !k2_db_is_null($rank);
$heroRatingLinked = !$heroPreDebut && $heroDisplay && isset($Rating) && !k2_db_is_null($Rating);
$heroGamesLinked = !$heroPreDebut && $heroGamesHref !== '';
$heroCountry = isset($Country) ? trim((string) $Country) : '';
$heroFlagMeta = $heroCountry !== '' ? k2_amiga_country_flag_meta($heroCountry) : null;
?>
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
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php
						if ($heroGamesLinked) {
							?><a class="k2-player-hero__stat-link" href="<?php echo htmlspecialchars($heroGamesHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $heroGames; ?></a><?php
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
<?php if ($heroPreDebut) { ?>
<p class="k2-amiga-time-travel__unwired k2-amiga-player-pre-debut-note">Not on the ladder at this cutoff.</p>
<?php } ?>
