<!DOCTYPE html>
<html lang="en" data-realm="online">
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex, nofollow" />
<title>Profile mock C — The Vault</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/profile-mock.css" rel="stylesheet" type="text/css" />
<script src="js/chart.umd.min.js"></script>
<script src="js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script src="js/chart-date-range.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-date-range.js'); ?>"></script>
<script src="js/player-rating-game-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rating-game-chart.js'); ?>" defer></script>
</head>
<body class="k2-site pm-mock pm-mock--c">

<?php
include $_SERVER['DOCUMENT_ROOT'] . '/includes/profile_mock_load.php';
$pmMockVariant = 'C';
$pmMockTitle = 'The Vault';
$pmMockThesis = 'Hall of fame — peak, volume, and legendary games as monuments.';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php';
?>

<div class="k2-page-nav">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/profile_mock_lab_banner.php'; ?>

<?php
$k2PlayerTabActive = 'profile';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_nav.php';
?>

<section class="pm-c__cinema" aria-label="Player monument">
	<p class="pm-c__name"><?php echo pm_h($pm['name']); ?></p>
	<p class="pm-c__plate" aria-label="Peak rating"><?php echo $pm['peak'] !== null ? (int) $pm['peak'] : '—'; ?></p>
	<p class="pm-c__tagline">
		Peak rating · #<?php echo (int) $pm['rank']; ?> today at <?php echo $pm['rating'] !== null ? (int) $pm['rating'] : '—'; ?>
		· <?php echo number_format($pm['games']); ?> rated games since <?php echo pm_h($pm['join_date']); ?>
	</p>
</section>

<div class="pm-c__monuments">
	<div class="pm-c__monument">
		<span class="pm-c__monument-value"><?php echo number_format($pm['games']); ?></span>
		<span class="pm-c__monument-label">Rated games played</span>
	</div>
	<div class="pm-c__monument">
		<span class="pm-c__monument-value"><?php echo (int) $pm['longest_win_streak']; ?></span>
		<span class="pm-c__monument-label">Longest win streak</span>
	</div>
	<div class="pm-c__monument">
		<span class="pm-c__monument-value"><?php echo (int) $pm['most_goals_scored']; ?></span>
		<span class="pm-c__monument-label">Most goals in one match</span>
	</div>
</div>

<h3 class="pm-chart-title">Exhibits</h3>
<p class="pm-muted pm-section-gap" style="margin-top:-8px;">Curated extremes — each links to the real match on the ladder.</p>

<div class="pm-c__exhibits">
	<?php foreach (array_slice($pm['trophies'], 0, 3) as $t) { ?>
	<article class="pm-c__exhibit">
		<div class="pm-c__exhibit-plinth">
			<span>Exhibit · <?php echo pm_h($t['year']); ?></span>
			<h3><?php echo pm_h($t['label']); ?></h3>
		</div>
		<div class="pm-c__exhibit-body">
			<p class="pm-c__exhibit-score">
				<a href="game.php?id=<?php echo (int) $t['game_id']; ?>"><?php echo pm_h($t['score']); ?></a>
			</p>
			<p class="pm-c__exhibit-vs">
				<span class="<?php echo pm_h($t['outcome_class']); ?>"><?php echo pm_h($t['outcome']); ?></span>
				· vs <a href="individual1.php?id=<?php echo (int) $t['opponent_id']; ?>"><?php echo pm_h($t['opponent_name']); ?></a>
			</p>
			<p class="pm-c__exhibit-year">On the ladder since <?php echo pm_h($t['year']); ?></p>
		</div>
	</article>
	<?php } ?>
</div>

<section class="pm-c__chart-ribbon">
	<h2>Career arc (by game number)</h2>
	<p>Every rated match in order — the long climb and the chapters after peak <?php echo $pm['peak'] !== null ? (int) $pm['peak'] : ''; ?>.</p>
	<div class="player-rating-game-chart" data-player-id="<?php echo (int) $pm['id']; ?>">
		<p class="player-rating-game-chart-status pm-muted">Loading rating by game number…</p>
		<p class="player-rating-game-peak-current-summary" style="display:none;margin:0 0 8px;font-size:1.05em;color:var(--k2-text-primary);"></p>
		<canvas width="960" height="300" aria-label="Rating after each game"></canvas>
	</div>
</section>

<h3 class="pm-chart-title">Plaque details</h3>
<dl class="pm-c__ledger">
	<dt>Current rating</dt>
	<dd><?php echo $pm['rating'] !== null ? (int) $pm['rating'] : '—'; ?></dd>
	<dt>Ladder rank</dt>
	<dd>#<?php echo (int) $pm['rank']; ?></dd>
	<dt>Win rate</dt>
	<dd><?php echo $pm['win_pct']; ?>% (<?php echo number_format($pm['wins']); ?> W · <?php echo number_format($pm['draws']); ?> D · <?php echo number_format($pm['losses']); ?> L)</dd>
	<dt>Last match</dt>
	<dd><?php echo pm_h($pm['last_game']); ?></dd>
	<?php if (!empty($pm['rivals'][0])) { ?>
	<dt>Signature rivalry</dt>
	<dd><a href="individual1.php?id=<?php echo (int) $pm['rivals'][0]['id']; ?>"><?php echo pm_h($pm['rivals'][0]['name']); ?></a> — <?php echo number_format($pm['rivals'][0]['games']); ?> games</dd>
	<?php } ?>
	<dt>Biggest win margin</dt>
	<dd><?php echo (int) $pm['biggest_win_margin']; ?> goals</dd>
	<dt>Busiest scoreline total</dt>
	<dd><?php echo (int) $pm['biggest_sum_goals']; ?> goals in one match</dd>
</dl>

<p class="pm-muted" style="margin-top:20px;">
	Matchup lab (H2H, opponent charts) and the full stat encyclopedia stay available — tucked below the vault in production, or on sibling feast tabs.
</p>

</div>

<?php mysqli_close($con); ?>
</body>
</html>
