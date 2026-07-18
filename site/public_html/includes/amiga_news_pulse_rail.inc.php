<?php
/**
 * Amiga News pulse rail — sidebar widgets (empty-panel invites until live widgets).
 */
declare(strict_types=1);
?>
<aside class="k2-amiga-news-room__pulse" aria-label="Pulse">
	<section class="k2-status-panel k2-status-panel--tight k2-amiga-news-pulse__panel k2-amiga-news-pulse__panel--art" aria-label="Kick Off 2 neon heritage art">
		<?php
		$k2NewsPulseArtSrc = '/images/amiga/news/hugo-kick-off-2-neon-v2.png';
		$k2NewsPulseArtVer = (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . $k2NewsPulseArtSrc);
		?>
		<a class="k2-status-heritage-inset k2-status-heritage-inset--link k2-status-heritage-inset--scene" href="/boxart.php#k2-boxart-hugo">
			<img
				class="k2-heritage-box__art"
				src="<?php echo htmlspecialchars($k2NewsPulseArtSrc . '?v=' . $k2NewsPulseArtVer, ENT_QUOTES, 'UTF-8'); ?>"
				width="1024"
				height="768"
				alt="Neon Kick Off 2 billboard behind a player celebrating on his knees in the rain. Read the box art story."
				loading="lazy"
				decoding="async"
			/>
		</a>
	</section>

	<section class="k2-status-panel k2-status-panel--tight k2-amiga-news-pulse__panel" aria-labelledby="k2-amiga-news-pulse-upcoming">
		<h2 id="k2-amiga-news-pulse-upcoming" class="k2-panel-heading">Upcoming tournament</h2>
		<p class="k2-status-panel__empty">24th Kick Off 2 World Cup &mdash; Oslo, November 14&ndash;15, 2026.</p>
		<p class="k2-amiga-news-pulse__link"><a class="k2-link-star" href="/amiga/news.php#2026-07-oslo-wc">World Cup &rarr;</a></p>
	</section>

	<section class="k2-status-panel k2-status-panel--tight k2-amiga-news-pulse__panel" aria-labelledby="k2-amiga-news-pulse-online">
		<h2 id="k2-amiga-news-pulse-online" class="k2-panel-heading">Online now</h2>
		<p class="k2-status-panel__empty">See who&rsquo;s in the KOOL online room and how the ladder is moving.</p>
		<p class="k2-amiga-news-pulse__link"><a class="k2-link-star" href="/status.php">Status &rarr;</a></p>
	</section>

	<section class="k2-status-panel k2-status-panel--tight k2-amiga-news-pulse__panel" aria-labelledby="k2-amiga-news-pulse-lb">
		<h2 id="k2-amiga-news-pulse-lb" class="k2-panel-heading">Leaderboards</h2>
		<p class="k2-status-panel__empty">Who&rsquo;s on top of the Amiga world right now &mdash; ratings, goals, streaks, and more.</p>
		<p class="k2-amiga-news-pulse__link"><a class="k2-link-star" href="/amiga/leaderboards/rating.php">Leaderboards &rarr;</a></p>
	</section>

	<section class="k2-status-panel k2-status-panel--tight k2-amiga-news-pulse__panel" aria-labelledby="k2-amiga-news-pulse-hof">
		<h2 id="k2-amiga-news-pulse-hof" class="k2-panel-heading">Hall of Fame</h2>
		<p class="k2-status-panel__empty">Browse the record book &mdash; peaks, streaks, and the names that own them.</p>
		<p class="k2-amiga-news-pulse__link"><a class="k2-link-star" href="/amiga/hall-of-fame.php">Hall of Fame &rarr;</a></p>
	</section>

	<section class="k2-status-panel k2-status-panel--tight k2-amiga-news-pulse__panel" aria-labelledby="k2-amiga-news-pulse-involved">
		<h2 id="k2-amiga-news-pulse-involved" class="k2-panel-heading">Get involved</h2>
		<p class="k2-status-panel__empty">Play online, find the lobby, or ask about setup.</p>
		<p class="k2-amiga-news-pulse__link"><a class="k2-link-star" href="/join.php">Play &amp; Setup &rarr;</a></p>
	</section>

	<section class="k2-status-panel k2-status-panel--tight k2-amiga-news-pulse__panel" aria-labelledby="k2-amiga-news-pulse-shelf">
		<h2 id="k2-amiga-news-pulse-shelf" class="k2-panel-heading">From the shelf</h2>
		<ul class="k2-amiga-news-pulse__links">
			<li><a href="/boxart.php#k2-boxart-story">The box art mystery</a></li>
			<li><a href="/about.php">About this site</a></li>
		</ul>
	</section>
</aside>