<!DOCTYPE html>
<html lang="en" data-realm="online">
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex, nofollow" />
<title>Profile lab — Kick Off 2 ratings</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/profile-mock.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/profile-mock-v2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/profile-mock-v3.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/profile-mock-v3d.css" rel="stylesheet" type="text/css" />
</head>
<body class="k2-site">

<?php
$portalId = isset($_GET['id']) ? (int) $_GET['id'] : 237;
if ($portalId < 1) {
    $portalId = 237;
}
include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php';
?>

<div class="k2-page-nav">
<div class="pm-portal">

	<header class="pm-portal__hero">
		<p class="pm-portal__eyebrow">Design exploration · not production</p>
		<h1 class="pm-portal__title">Profile page lab</h1>
		<p class="pm-portal__lead">
			<strong>3G</strong> is the composite to iterate on (At a glance band). <strong>3D</strong> is the vertical-section baseline for comparison.
			Pass 3 A/B/C are distinct theses; pass 2 and pass 1 are archived below.
			Feast contract: <code>docs/profile-data-audit.md</code> Part C.
		</p>
		<p class="pm-portal__anchor">
			Anchor player: <strong><?php echo (int) $portalId; ?></strong>
		</p>
	</header>

	<h2 class="pm2-portal-v1__title" style="margin:0 0 12px;font-family:var(--k2-font-display);font-size:18px;color:var(--k2-text-primary);">Pass 3 composites</h2>
	<div class="pm-mock-grid pm3-portal-grid">
		<article class="pm-mock-card pm-mock-card--prod">
			<p class="pm-mock-card__label">Baseline</p>
			<h2 class="pm-mock-card__name">Production</h2>
			<p class="pm-mock-card__thesis">Chart stack first, encyclopedic tables — the contrast.</p>
			<a class="pm-mock-card__cta" href="individual1.php?id=<?php echo $portalId; ?>">Open individual1.php</a>
		</article>

		<article class="pm-mock-card" style="border-color:var(--k2-realm-accent);">
			<p class="pm-mock-card__label">Pass 3 · G</p>
			<h2 class="pm-mock-card__name">Composite feast</h2>
			<p class="pm-mock-card__thesis">Iterate here — at-a-glance band, Presence | Career ranks, played days, personal-bests peak cards (3H), moments, charts (rivalry via top-opponents default).</p>
			<a class="pm-mock-card__cta" href="profile_mock3_g.php?id=<?php echo $portalId; ?>">Open mock 3G</a>
		</article>

		<article class="pm-mock-card">
			<p class="pm-mock-card__label">Pass 3 · D</p>
			<h2 class="pm-mock-card__name">Vertical sections</h2>
			<p class="pm-mock-card__thesis">Same feast — stacked Presence then Career (no duo band).</p>
			<a class="pm-mock-card__cta" href="profile_mock3_d.php?id=<?php echo $portalId; ?>">Open mock 3D</a>
		</article>
	</div>

	<h2 class="pm2-portal-v1__title" style="margin:16px 0 12px;font-family:var(--k2-font-display);font-size:18px;color:var(--k2-text-primary);">3H / 3I / 3J — rivalry experiment (3G feast + personal-bests peak)</h2>
	<div class="pm-mock-grid pm3-portal-grid">
		<article class="pm-mock-card">
			<p class="pm-mock-card__label">Pass 3 · H</p>
			<h2 class="pm-mock-card__name">Rival cards</h2>
			<p class="pm-mock-card__thesis">3G feast + personal-bests peak — three cards: games · your W–D–L · opponent name.</p>
			<a class="pm-mock-card__cta" href="profile_mock3_h.php?id=<?php echo $portalId; ?>">Open mock 3H</a>
		</article>

		<article class="pm-mock-card">
			<p class="pm-mock-card__label">Pass 3 · I</p>
			<h2 class="pm-mock-card__name">Rival spotlight</h2>
			<p class="pm-mock-card__thesis">3G feast + personal-bests peak — big opponent name + game count; record and chart note beside.</p>
			<a class="pm-mock-card__cta" href="profile_mock3_i.php?id=<?php echo $portalId; ?>">Open mock 3I</a>
		</article>

		<article class="pm-mock-card">
			<p class="pm-mock-card__label">Pass 3 · J</p>
			<h2 class="pm-mock-card__name">Chart bridge</h2>
			<p class="pm-mock-card__thesis">3G feast + rhythm band (calendar + peak chips) — slim rival strip right before charts, no duel box.</p>
			<a class="pm-mock-card__cta" href="profile_mock3_j.php?id=<?php echo $portalId; ?>">Open mock 3J</a>
		</article>
	</div>

	<h2 class="pm2-portal-v1__title" style="margin:24px 0 12px;font-family:var(--k2-font-display);font-size:18px;color:var(--k2-text-primary);">Pass 3 A/B/C — visual theses</h2>
	<div class="pm-mock-grid pm3-portal-grid">
		<article class="pm-mock-card">
			<p class="pm-mock-card__label">Pass 3 · A</p>
			<h2 class="pm-mock-card__name">Ember Monument</h2>
			<p class="pm-mock-card__thesis">Vault-inspired monument CORE (wide glowing rating + trio), duel rivalry, calendar map, editorial moments.</p>
			<a class="pm-mock-card__cta" href="profile_mock3_a.php?id=<?php echo $portalId; ?>">Open mock A</a>
		</article>

		<article class="pm-mock-card">
			<p class="pm-mock-card__label">Pass 3 · B</p>
			<h2 class="pm-mock-card__name">Glass Ledger</h2>
			<p class="pm-mock-card__thesis">Chronicle borders + prod focus — Steve (#rank), scoreboard rivalry, full-width charts up front, mosaic moments.</p>
			<a class="pm-mock-card__cta" href="profile_mock3_b.php?id=<?php echo $portalId; ?>">Open mock B</a>
		</article>

		<article class="pm-mock-card">
			<p class="pm-mock-card__label">Pass 3 · C</p>
			<h2 class="pm-mock-card__name">Pulse Sigil</h2>
			<p class="pm-mock-card__thesis">Sigil bar CORE, hero calendar year grid, horizontal moments reel, compact rivalry pin.</p>
			<a class="pm-mock-card__cta" href="profile_mock3_c.php?id=<?php echo $portalId; ?>">Open mock C</a>
		</article>
	</div>

	<section class="k2-card">
		<h2 class="k2-card__title">How to review</h2>
		<p class="k2-card__hint">
			Iterate on <strong>3D</strong> first. Switch mocks only from this portal. Compare with production (<code>?id=<?php echo $portalId; ?></code>).
			Note steals for <code>individual1.php</code>.
		</p>
	</section>

	<div class="pm-portal-v1 pm2-portal-v1" style="margin-top:28px;">
		<h2 class="pm2-portal-v1__title">Pass 2 — archived contrast</h2>
		<div class="pm-mock-grid">
			<article class="pm-mock-card" style="opacity:0.88;">
				<p class="pm-mock-card__label">Pass 2 · A</p>
				<h2 class="pm-mock-card__name">The Chronicle</h2>
				<p class="pm-mock-card__thesis">Same data, boxed reorder — superseded by pass 3.</p>
				<a class="pm-mock-card__cta" href="profile_mock_a.php?id=<?php echo $portalId; ?>">Open mock A</a>
			</article>
			<article class="pm-mock-card" style="opacity:0.88;">
				<p class="pm-mock-card__label">Pass 2 · B</p>
				<h2 class="pm-mock-card__name">The Arena</h2>
				<p class="pm-mock-card__thesis">Rivalry-forward pass 2 — superseded by pass 3.</p>
				<a class="pm-mock-card__cta" href="profile_mock_b.php?id=<?php echo $portalId; ?>">Open mock B</a>
			</article>
			<article class="pm-mock-card" style="opacity:0.88;">
				<p class="pm-mock-card__label">Pass 2 · C</p>
				<h2 class="pm-mock-card__name">The Atlas</h2>
				<p class="pm-mock-card__thesis">Linear heatmap pass 2 — superseded by pass 3 calendar.</p>
				<a class="pm-mock-card__cta" href="profile_mock_c.php?id=<?php echo $portalId; ?>">Open mock C</a>
			</article>
		</div>
	</div>

	<div class="pm-portal-v1 pm2-portal-v1">
		<h2 class="pm2-portal-v1__title">Pass 1 — historical reference</h2>
		<div class="pm-mock-grid">
			<article class="pm-mock-card" style="opacity:0.85;">
				<p class="pm-mock-card__label">Pass 1 · A</p>
				<h2 class="pm-mock-card__name">The Chronicle (v1)</h2>
				<p class="pm-mock-card__thesis">First exploration — superseded by pass 2.</p>
				<a class="pm-mock-card__cta" href="profile_mock_v1_a.php?id=<?php echo $portalId; ?>">Open v1 A</a>
			</article>
			<article class="pm-mock-card" style="opacity:0.85;">
				<p class="pm-mock-card__label">Pass 1 · B</p>
				<h2 class="pm-mock-card__name">The Wire (v1)</h2>
				<p class="pm-mock-card__thesis">First exploration — superseded by pass 2.</p>
				<a class="pm-mock-card__cta" href="profile_mock_v1_b.php?id=<?php echo $portalId; ?>">Open v1 B</a>
			</article>
			<article class="pm-mock-card" style="opacity:0.85;">
				<p class="pm-mock-card__label">Pass 1 · C</p>
				<h2 class="pm-mock-card__name">The Vault (v1)</h2>
				<p class="pm-mock-card__thesis">First exploration — superseded by pass 2.</p>
				<a class="pm-mock-card__cta" href="profile_mock_v1_c.php?id=<?php echo $portalId; ?>">Open v1 C</a>
			</article>
		</div>
	</div>

</div>
</div>

</body>
</html>
