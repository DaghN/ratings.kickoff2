<?php
/**
 * Amiga player hero — same feast shell as online; links stay in Amiga realm.
 * Expects $Name, $Rating, $NumberEvents, $NumberGames, $NumberWorldCups, $rank, $id (optional $Country for inline flag beside name).
 */
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_player_tournament_lib.php';
require_once __DIR__ . '/amiga_lb_lib.php';
require_once __DIR__ . '/amiga_wc_podium_th.php';

if (empty($Name)) {
    return;
}

$heroInitial = strtoupper(substr((string) $Name, 0, 1));
$heroPreDebut = !empty($k2AmigaPlayerPreDebut);
$heroDisplay = !$heroPreDebut && isset($NumberGames) && (int) $NumberGames >= 1;
$heroRating = $heroPreDebut
    ? '—'
    : ($heroDisplay && isset($Rating) && !k2_db_is_null($Rating) ? k2_fmt_int($Rating, '—') : '—');
$heroEvents = $heroPreDebut
    ? '—'
    : (isset($NumberEvents) && !k2_db_is_null($NumberEvents) ? (int) $NumberEvents : 0);
$heroGames = $heroPreDebut
    ? '—'
    : (isset($NumberGames) && !k2_db_is_null($NumberGames) ? (int) $NumberGames : 0);
$heroWorldCups = $heroPreDebut
    ? '—'
    : (isset($NumberWorldCups) && !k2_db_is_null($NumberWorldCups) ? (int) $NumberWorldCups : 0);
$heroRankNum = (!$heroPreDebut && isset($rank) && !k2_db_is_null($rank) && (int) $rank > 0)
    ? (int) $rank
    : null;
$heroRank = $heroRankNum !== null ? '#' . $heroRankNum : '—';
$nameEsc = htmlspecialchars((string) $Name, ENT_QUOTES, 'UTF-8');
$heroPlayerId = isset($id) ? (int) $id : (isset($playerId) ? (int) $playerId : 0);
$heroProfileHref = $heroPlayerId > 0 ? k2_amiga_player_profile_href($heroPlayerId) : '';
$heroEventsHref = $heroPlayerId > 0
    ? amiga_player_tournaments_table_url($heroPlayerId)
    : '';
$heroGamesHref = $heroPlayerId > 0
    ? k2_amiga_route('amiga-player-games', ['id' => $heroPlayerId]) . k2_player_matching_games_anchor_fragment()
    : '';
$heroWorldCupsHref = $heroPlayerId > 0
    ? amiga_player_tournaments_filter_url($heroPlayerId, 'world-cup') . amiga_player_tournaments_table_anchor_fragment()
    : '';
$heroLbRatingHref = $heroPlayerId > 0
    ? amiga_lb_rating_player_href($heroPlayerId)
    : amiga_lb_table_href('/amiga/leaderboards/rating.php');
$heroRankLinked = !$heroPreDebut && $heroDisplay && $heroRankNum !== null;
$heroRatingLinked = !$heroPreDebut && $heroDisplay && isset($Rating) && !k2_db_is_null($Rating);
$heroEventsLinked = !$heroPreDebut && $heroEventsHref !== '';
$heroGamesLinked = !$heroPreDebut && $heroGamesHref !== '';
$heroWorldCupsLinked = !$heroPreDebut && $heroWorldCupsHref !== '';
$heroCountry = isset($Country) ? trim((string) $Country) : '';
$heroNameInner = $nameEsc;
if ($heroProfileHref !== '') {
    $heroNameInner = '<a class="k2-player-hero__name-link" href="' . htmlspecialchars($heroProfileHref, ENT_QUOTES, 'UTF-8') . '">' . $nameEsc . '</a>';
}
$heroNameDisplay = $heroCountry !== ''
    ? k2_amiga_inline_flag_and_link($heroCountry, $heroNameInner)
    : $heroNameInner;
$heroWcMedals = [];
foreach ([1 => (int) ($k2AmigaPlayerHeroWcGold ?? 0), 2 => (int) ($k2AmigaPlayerHeroWcSilver ?? 0), 3 => (int) ($k2AmigaPlayerHeroWcBronze ?? 0)] as $place => $medalCount) {
    if ($medalCount > 0) {
        $heroWcMedals[$place] = $medalCount;
    }
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_hero_glow_session.php';
k2_player_hero_atomic_paint_open();
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
			<h2 class="k2-player-hero__name"><?php echo $heroNameDisplay; ?></h2>
			<div class="k2-player-hero__stats">
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">Rank</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--rank"><?php
						if ($heroRankLinked) {
							?><a class="k2-player-hero__stat-link" href="<?php echo htmlspecialchars($heroLbRatingHref, ENT_QUOTES, 'UTF-8'); ?>">#<?php echo $heroRankNum; ?></a><?php
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
					<span class="k2-player-hero__stat-label">Events</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php
						if ($heroEventsLinked) {
							?><a class="k2-player-hero__stat-link" href="<?php echo htmlspecialchars($heroEventsHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $heroEvents; ?></a><?php
						} else {
							echo $heroEvents;
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
				<div class="k2-player-hero__stat">
					<span class="k2-player-hero__stat-label">World Cups</span>
					<span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php
						if ($heroWorldCupsLinked) {
							?><a class="k2-player-hero__stat-link" href="<?php echo htmlspecialchars($heroWorldCupsHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $heroWorldCups; ?></a><?php
						} else {
							echo $heroWorldCups;
						}
					?></span>
				</div>
				<?php if ($heroWcMedals !== []) {
                    $heroWcMedalCount = count($heroWcMedals);
                    ?>
				<div class="k2-player-hero__medals" style="--k2-player-hero-medal-count: <?php echo $heroWcMedalCount; ?>">
				<?php foreach ($heroWcMedals as $place => $medalCount) {
                    $medalMeta = amiga_wc_podium_meta((int) $place);
                    if ($medalMeta === null) {
                        continue;
                    }
                    $medalHref = '';
                    if (!$heroPreDebut && $heroPlayerId > 0 && $medalCount > 0) {
                        $winnerFilter = (int) $place === 1 ? 'with-win' : '';
                        $finishFilter = (int) $place === 2 ? 2 : ((int) $place === 3 ? 3 : 0);
                        $medalHref = amiga_lb_player_tournaments_inventory_href(
                            $heroPlayerId,
                            'world-cup',
                            '',
                            $winnerFilter,
                            '',
                            $finishFilter,
                        );
                    }
                    $medalValueHtml = '<span class="k2-country-hero__medal-value k2-country-hero__medal-value--'
                        . k2_h($medalMeta['variant']) . '">' . k2_h((string) $medalCount) . '</span>';
                    if ($medalHref !== '') {
                        $medalValueHtml = '<a class="k2-player-hero__stat-link" href="'
                            . htmlspecialchars($medalHref, ENT_QUOTES, 'UTF-8') . '">' . $medalValueHtml . '</a>';
                    }
                    ?>
				<div class="k2-player-hero__stat k2-country-hero__stat--medal">
					<span class="k2-country-hero__medal-label"><?php echo amiga_wc_podium_metal_label_markup((int) $place); ?></span>
					<?php echo $medalValueHtml; ?>
				</div>
				<?php } ?>
				</div>
				<?php } ?>
			</div>
		</div>
	</div>
</article>
<?php
k2_player_hero_atomic_paint_close();
k2_player_hero_glow_session_mark();
?>
<?php if ($heroPreDebut) { ?>
<p class="k2-amiga-time-travel__unwired k2-amiga-player-pre-debut-note">Not on the ladder at this cutoff.</p>
<?php } ?>
