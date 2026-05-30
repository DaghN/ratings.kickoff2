<?php
/**
 * Play & setup page body (join.php).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/join_page_links.php';

$links = k2_join_page_links();
$promoEmbedId = $links['youtube_promo_embed_id'];
$promoEmbedSrc = 'https://www.youtube.com/embed/' . rawurlencode($promoEmbedId);
?>
<section class="k2-join-page" aria-labelledby="k2-join-title">
	<header class="k2-join-page__hero">
		<h1 id="k2-join-title" class="k2-join-page__title">Play &amp; Setup</h1>
		<p class="k2-join-page__prose">
			Remember those endless evenings hammering Kick Off 2 on the Amiga 500 with your mates or brother? The one where every goal felt like pure magic and you&rsquo;d play until the sun came up? Yeah&hellip; we never really stopped either.
		</p>
		<p class="k2-join-page__prose">
			Most nights you&rsquo;ll still find us online. We&rsquo;ve been running a proper online scene for years now (rollback + fast-forward netcode that somehow makes this frantic old game feel almost lag-free &mdash; it&rsquo;s borderline witchcraft). And every year since 2001 we still crown a World Champion on the original Amiga hardware. Same game. Same stupidly addictive feel. Just with a few more grey hairs and a lot more laughs.
		</p>
	</header>

	<article class="k2-card k2-join-page__card">
		<h2 class="k2-panel-heading">What&rsquo;s inside the app</h2>
		<p class="k2-join-page__prose">
			A proper lobby where we hang out and chat. Jump into quick matchmaking when you fancy a game, or just spectate live matches. There&rsquo;s practice against the CPU, an empty pitch if you want to re-find your old touch, and custom graphics if that&rsquo;s your thing. Every single match gets recorded and archived, so your profile slowly builds into a proper little history book of your goals, rivalries and ridiculous comebacks.
		</p>
	</article>

	<article class="k2-card k2-join-page__card" id="start">
		<h2 class="k2-panel-heading">Start here</h2>
		<ol class="k2-join-page__steps">
			<li>
				<strong>Download the game</strong> &mdash; everyone can grab the installer straight from
				<a href="<?php echo k2_h($links['kickoff2_net']); ?>" rel="noopener noreferrer">kickoff2.net</a>.
			</li>
			<li>
				<strong>Grab a beta key</strong> &mdash; pop into our
				<a href="<?php echo k2_h($links['discord']); ?>" rel="noopener noreferrer">Discord</a>
				and just ask. Someone will sort you out in minutes. That key lets you register inside the app and reach the lobby (you don&rsquo;t need it for the download itself).
			</li>
			<li>
				<strong>Sort your joystick</strong> &mdash; you <em>can</em> poke around with keyboard at first, but we all know you&rsquo;ll want the real thing soon. Good news: your old Competition Pro or similar still works perfectly with a cheap 9-pin-to-USB adapter.
				<ul class="k2-join-page__link-list">
<?php foreach ($links['adapters'] as $adapter) { ?>
					<li><a href="<?php echo k2_h($adapter['href']); ?>" rel="noopener noreferrer"><?php echo k2_h($adapter['label']); ?></a></li>
<?php } ?>
				</ul>
				Original sticks still turn up on eBay, and there are good modern replicas. Some players even build their own joysticks. If wiring or setup gets confusing, ask in
				<a href="<?php echo k2_h($links['discord']); ?>" rel="noopener noreferrer">Discord</a>; people there are glad to help.
			</li>
			<li>
				<strong>Jump in and play.</strong> First few matches might feel rusty. That&rsquo;s normal. Everyone&rsquo;s been there.
			</li>
		</ol>
	</article>

	<article class="k2-card k2-join-page__card" id="watch">
		<h2 class="k2-panel-heading">See it in action</h2>
		<p class="k2-join-page__prose">Want proof it still feels brilliant?</p>
		<ul class="k2-join-page__link-list">
			<li>
				<strong>Goal-scoring tutorial</strong> (great for shaking off the cobwebs):
				<a href="<?php echo k2_h($links['youtube_tutorial']); ?>" rel="noopener noreferrer">Watch here</a>
			</li>
			<li>
				<strong>World Cup finals playlist</strong> (big-screen madness):
				<a href="<?php echo k2_h($links['youtube_wc_playlist']); ?>" rel="noopener noreferrer">Watch the highlights</a>
			</li>
			<li>
				<strong>Steve&rsquo;s KO2CV channel</strong> &mdash; the absolute vault of epic games:
				<a href="<?php echo k2_h($links['youtube_channel']); ?>" rel="noopener noreferrer">@KO2CV_TV</a>
			</li>
		</ul>
	</article>

	<article class="k2-card k2-join-page__card">
		<h2 class="k2-panel-heading">The bigger picture</h2>
		<p class="k2-join-page__prose">
			Online is our main thing these days &mdash; quick games, proper rivals, and a growing bunch of regulars. But the Amiga side is still alive too: kitchen tournaments, national cups, and the yearly World Cup (around 40 players from the wider pool, with 27,000+ official Amiga results logged in the database). Same sport, same stubborn love of the game.
			<a href="<?php echo k2_h($links['kickoff2_com']); ?>" rel="noopener noreferrer">kickoff2.com</a> and the
			<a href="<?php echo k2_h($links['koa_forum']); ?>" rel="noopener noreferrer">KOA</a> forum have all the old tournament history and KO2CV stuff.
		</p>
		<p class="k2-join-page__prose">
			Whether you&rsquo;re coming back after 30 years or you&rsquo;re brand new to it, you&rsquo;re very welcome. No one&rsquo;s expecting you to be a god on day one. Just grab a joystick (or not), say hi in the lobby, and come have a kickabout. We&rsquo;re a small, friendly crew who still think this daft old game is one of the best ever made.
		</p>
		<p class="k2-join-page__prose k2-join-page__signoff">See you on the pitch! &#9917;</p>
	</article>

	<article class="k2-card k2-join-page__card k2-join-page__video-wrap">
		<div class="k2-join-page__video">
			<iframe
				class="k2-join-page__video-iframe"
				src="<?php echo k2_h($promoEmbedSrc); ?>"
				title="Kick Off 2 Online"
				allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
				referrerpolicy="strict-origin-when-cross-origin"
				allowfullscreen
			></iframe>
		</div>
	</article>
</section>
