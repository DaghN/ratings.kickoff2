<?php
/**
 * About page body (about.php leaf).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/site_footer_links.php';

$links = k2_site_footer_links();
$email = $links['contact_email'];
$emailEsc = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$mailto = htmlspecialchars('mailto:' . rawurlencode($email), ENT_QUOTES, 'UTF-8');
?>
<section class="k2-about-page" aria-labelledby="k2-about-title">
	<header class="k2-about-page__hero">
		<h1 id="k2-about-title" class="k2-about-page__title">About</h1>
	</header>

	<article class="k2-card k2-about-page__card" id="what-this-is">
		<h2 class="k2-panel-heading">What this is</h2>
		<p class="k2-about-page__prose">
			Kick Off 2 ratings is a community content site for people who play and follow Kick Off 2, rooted in statistics, results, and tournament history, and growing into news, lore, and media.
		</p>
		<p class="k2-about-page__prose">
			It covers two related worlds under one shell: the live online ladder, and the Amiga 500 tournament tradition. It is not the homepage for everything KO2; forums, Discord, the play client, and the rest of the scene are older siblings. This site aims to fit next to them. Dense where stats matter, warmer where the story does, and honest about it all.
		</p>
		<p class="k2-about-page__prose">
			<?php echo htmlspecialchars($links['copyright_name'], ENT_QUOTES, 'UTF-8'); ?> builds and maintains the website. The source is on
			<a href="https://github.com/DaghN/ratings.kickoff2" rel="noopener noreferrer">GitHub</a>.
			It is not an official product of any game publisher.
		</p>
	</article>

	<article class="k2-card k2-about-page__card" id="acknowledgements">
		<h2 class="k2-panel-heading">Acknowledgements</h2>
		<p class="k2-about-page__prose">
			We are all responsible for this thing still being alive and thriving. Every single person who has played a game or posted in the forum is part of the history. Some people, though, I would like to give a special mention here:
		</p>

		<p class="k2-about-page__prose">
			<strong class="k2-about-page__person">Robert Swift</strong>
			and <strong class="k2-about-page__person">Glenn Loite</strong>
			who, in the dawn of time, took charge of organizing tournament records into a spreadsheet archive and rating system, and who were always ready to take the reins again when exhaustion set in and the work was about to be left by the wayside. Of course, this is not to overlook that Robert was also absolutely crucial in kickstarting the English Kick Off 2 scene and keeping it alive and strong through the years.
		</p>

		<p class="k2-about-page__prose">
			<strong class="k2-about-page__person">Alkis Polyrakis</strong>
			who created the Access database where he has, for more than 20 years, entered more than 27,000 results from 607 official Amiga KOA tournaments. In addition to this, Alkis has on a monthly basis published the KOA ratings and given the KOA The Lob write-up, even in periods when it seemed everybody else was about to forget about our glorious game. Last but not least, we cannot omit to mention his fantastic
			<a href="https://www.alkis.org/ecups.html" rel="noopener noreferrer">World Cup pages</a>
			with tons of generous editorial about all the participants and rich statistics from all the World Cups.
		</p>

		<p class="k2-about-page__prose">
			<strong class="k2-about-page__person">Spyros Paraschis</strong>
			who created the
			<a href="https://ko-gathering.com/forum/viewtopic.php?t=14608" rel="noopener noreferrer">KOA Stats Analyser</a>
			and the
			<a href="https://www.ko-gathering.com/koasi/about.php" rel="noopener noreferrer">KOA Statistics Institute</a>,
			the spiritual predecessor to this website, and a hot favorite among all us number aficionados in the KOA for many years. Thanks a lot Spyros, we have probably all spent a bit too much time staring at numbers solely because of you!
		</p>

		<p class="k2-about-page__prose">
			<strong class="k2-about-page__person">Mark Williams</strong>
			(also known as <strong class="k2-about-page__person">Durban</strong>)
			who has maintained the social backbone of the KOA, our infamous
			<a href="https://ko-gathering.com/forum/index.php" rel="noopener noreferrer">KOA forum</a>,
			quietly and solidly for many years, steering the ship when the storms of heated forum wars were threatening to tear it all apart time and time again. Being the forum admin is a bit like being the bass player: people never notice until he is not there. But we certainly notice, and we are very grateful for your effort over the years! Keeping the KOA forum alive and well is another crucial pillar for ensuring that we still have an ongoing core of competitive and social lore.
		</p>

		<p class="k2-about-page__prose">
			<strong class="k2-about-page__person">Steve Camber</strong>
			who has been the fundamental technical backbone of most of the KOA proceedings, with the KO2 Competition Version being one crucial item, and the
			<a href="https://kickoff2.net/" rel="noopener noreferrer">online Kick Off 2 app</a>
			created by Steve being another crucial pillar for the KOA community. In addition, Steve has helped to preserve videos from pretty much every World Cup in his
			<a href="https://www.youtube.com/@KO2CV_TV" rel="noopener noreferrer">YouTube vault</a>,
			and has taken the reins in recording many of these videos. He is now helping to host this website and hook up the online app to it so we can have fun with public live results and whatever other fun the future might bring, like a Kick Off 2 Player Manager maybe some day soon!
		</p>

		<p class="k2-about-page__prose k2-about-page__close">
			We can be proud and thankful for all these contributions which allow us all to have a thriving Kick Off 2 community even to this day. Long may it live!
		</p>
	</article>

	<article class="k2-card k2-about-page__card" id="contact">
		<h2 class="k2-panel-heading">Contact</h2>
		<p class="k2-about-page__prose">
			For questions about the website, wrong data on the site, or other maintainer matters:
			<a href="<?php echo $mailto; ?>"><?php echo $emailEsc; ?></a>.
		</p>
		<p class="k2-about-page__prose">
			To join online play, find the lobby, or ask about joysticks and setup, use
			<a href="/join.php">Play &amp; Setup</a> (Discord and community links live there).
		</p>
	</article>
</section>
