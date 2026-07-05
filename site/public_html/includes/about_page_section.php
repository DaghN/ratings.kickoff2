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
		<p class="k2-about-page__prose">
			Kick Off 2 ratings is a community statistics site for online ladder play and Amiga tournament &mdash; built and maintained by fans who play the game.
		</p>
	</header>

	<article class="k2-card k2-about-page__card">
		<h2 class="k2-panel-heading">Who maintains this</h2>
		<p class="k2-about-page__prose">
			<?php echo htmlspecialchars($links['copyright_name'], ENT_QUOTES, 'UTF-8'); ?> builds and maintains this site. It is a fan project, not an official product of any game publisher.
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
