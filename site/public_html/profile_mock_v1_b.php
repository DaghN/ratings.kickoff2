<!DOCTYPE html>
<html lang="en" data-realm="online">
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex, nofollow" />
<title>Profile mock B — The Wire</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/profile-mock.css" rel="stylesheet" type="text/css" />
<script src="js/chart.umd.min.js"></script>
<script src="js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script src="js/player-games-month-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-games-month-chart.js'); ?>" defer></script>
</head>
<body class="k2-site pm-mock pm-mock--b">

<?php
include $_SERVER['DOCUMENT_ROOT'] . '/includes/profile_mock_load.php';
$pmMockVariant = 'B';
$pmMockTitle = 'The Wire';
$pmMockThesis = 'Live pulse — recency, form, and rivals. The ladder feels active tonight.';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php';
?>

<div class="k2-page-nav">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/profile_mock_lab_banner.php'; ?>

<?php
$k2PlayerTabActive = 'profile';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_nav.php';
?>

<header class="pm-b__pulse-header">
	<div class="pm-b__avatar" aria-hidden="true"><?php echo pm_h($pm['initial']); ?></div>
	<div>
		<p class="pm-b__live">Active on the ladder</p>
		<h2 class="pm-b__name"><?php echo pm_h($pm['name']); ?></h2>
		<p class="pm-b__sub">
			#<?php echo (int) $pm['rank']; ?> ·
			<?php echo (int) $pm['games_this_month']; ?> games this month ·
			last match <?php echo pm_h($pm['last_game']); ?>
		</p>
	</div>
	<?php if ($pm['rating'] !== null) { ?>
	<div class="pm-b__rating-block">
		<strong><?php echo (int) $pm['rating']; ?></strong>
		<span class="pm-muted">Dagh rating</span>
	</div>
	<?php } ?>
</header>

<div class="pm-b__ticker" role="list">
	<div class="pm-b__tick" role="listitem">
		<strong><?php echo number_format($pm['games']); ?></strong>
		<span>Career games</span>
	</div>
	<div class="pm-b__tick" role="listitem">
		<strong><?php echo $pm['win_pct']; ?>%</strong>
		<span>Win rate</span>
	</div>
	<div class="pm-b__tick" role="listitem">
		<strong><?php echo $pm['peak'] !== null ? (int) $pm['peak'] : '—'; ?></strong>
		<span>Peak</span>
	</div>
	<div class="pm-b__tick" role="listitem">
		<strong><?php echo (int) $pm['longest_win_streak']; ?></strong>
		<span>Best streak</span>
	</div>
</div>

<div class="pm-b__form">
	<span class="pm-b__form-label">Last 10</span>
	<?php foreach ($pm['form'] as $letter) {
	    $cls = 'pm-b__form-pill--' . $letter;
	    ?>
	<span class="pm-b__form-pill <?php echo pm_h($cls); ?>" title="<?php
	    echo $letter === 'W' ? 'Win' : ($letter === 'L' ? 'Loss' : 'Draw');
	    ?>"><?php echo pm_h($letter); ?></span>
	<?php } ?>
</div>

<h3 class="pm-chart-title">Recent rated matches</h3>
<p class="pm-muted pm-section-gap" style="margin-top:-8px;">Newest first — the signal that <?php echo pm_h($pm['name']); ?> is still in the mix.</p>

<div class="pm-b__timeline">
	<?php foreach (array_slice($pm['recent'], 0, 8) as $m) {
	    $adjClass = $m['adjustment'] >= 0 ? 'pm-outcome--win' : 'pm-outcome--loss';
	    ?>
	<article class="pm-b__match">
		<span class="pm-b__match-date"><?php echo pm_h($m['date']); ?></span>
		<span class="pm-b__match-opp">
			<a href="individual1.php?id=<?php echo (int) $m['opponent_id']; ?>"><?php echo pm_h($m['opponent_name']); ?></a>
		</span>
		<span class="pm-b__match-score <?php echo pm_h($m['outcome_class']); ?>"><?php echo pm_h($m['score']); ?></span>
		<span class="pm-b__match-adj <?php echo pm_h($adjClass); ?>"><?php echo pm_h(pm_adj_text($m['adjustment'])); ?></span>
		<span class="<?php echo pm_h($m['outcome_class']); ?>" style="font-size:12px;font-weight:700;"><?php echo pm_h($m['outcome']); ?></span>
	</article>
	<?php } ?>
</div>

<h3 class="pm-chart-title">Rival orbit</h3>
<p class="pm-muted" style="margin:-6px 0 12px;">Most rated games against — click through to their profiles.</p>
<div class="pm-b__rivals">
	<?php foreach ($pm['rivals'] as $i => $r) {
	    $feat = $i === 0 ? ' pm-b__rival--featured' : '';
	    ?>
	<div class="pm-b__rival<?php echo $feat; ?>">
		<strong><a href="individual1.php?id=<?php echo (int) $r['id']; ?>"><?php echo pm_h($r['name']); ?></a></strong>
		<span><?php echo number_format($r['games']); ?> rated games</span>
	</div>
	<?php } ?>
</div>

<section class="pm-b__chart-panel">
	<h3 class="pm-chart-title">Activity rhythm</h3>
	<p class="pm-muted">Games per calendar month — busy months jump out.</p>
	<div class="player-games-month-chart" data-player-id="<?php echo (int) $pm['id']; ?>">
		<p class="player-games-month-chart-status pm-muted">Loading games per month…</p>
		<canvas width="960" height="260" aria-label="Games per month"></canvas>
	</div>
</section>

<p class="pm-muted">
	<a href="individual3.php?id=<?php echo (int) $pm['id']; ?>">Full game ledger</a> lives on the Games tab.
	Analyst charts (H2H, compare, win-rate buckets) stay in a depth layer on the final Profile — not on the wire.
</p>

</div>

<?php mysqli_close($con); ?>
</body>
</html>
