<!DOCTYPE html>
<html lang="en" data-realm="online">
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex, nofollow" />
<title>Profile mock A — The Chronicle</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/profile-mock.css" rel="stylesheet" type="text/css" />
<script src="js/chart.umd.min.js"></script>
<script src="js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script src="js/chart-date-range.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-date-range.js'); ?>"></script>
<script src="js/player-rating-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rating-chart.js'); ?>" defer></script>
</head>
<body class="k2-site pm-mock pm-mock--a">

<?php
include $_SERVER['DOCUMENT_ROOT'] . '/includes/profile_mock_load.php';
$pmMockVariant = 'A';
$pmMockTitle = 'The Chronicle';
$pmMockThesis = 'Magazine cover — editorial narrative and trophy moments first; rating chart supports the story.';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php';
?>

<div class="k2-page-nav">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/profile_mock_lab_banner.php'; ?>

<?php
$k2PlayerTabActive = 'profile';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_nav.php';

$topRival = $pm['rivals'][0] ?? null;
$chronicle = $pm['name'] . ' has played '
    . number_format($pm['games'])
    . ' rated online matches since joining the ladder in '
    . $pm['join_date']
    . '. ';
if ($pm['peak'] !== null && $pm['rating'] !== null && $pm['peak_gap'] > 0) {
    $chronicle .= 'Peak rating '
        . $pm['peak']
        . ' tells one chapter; today’s '
        . $pm['rating']
        . ' is another — both true, neither the whole person. ';
}
if ($topRival !== null) {
    $chronicle .= 'The rivalry with '
        . $topRival['name']
        . ' alone spans '
        . number_format($topRival['games'])
        . ' games — a universe inside the ladder.';
}
?>

<section class="pm-a__masthead">
	<div class="pm-a__cover">
		<p class="pm-a__edition">Online ladder · Profile</p>
		<h2 class="pm-a__headline"><?php echo pm_h($pm['name']); ?></h2>
		<p class="pm-a__deck">
			<?php echo number_format($pm['games']); ?> rated games ·
			<?php echo $pm['win_pct']; ?>% wins ·
			last seen <?php echo pm_h($pm['last_game']); ?>
		</p>
		<p class="pm-a__byline">
			<span>Joined <?php echo pm_h($pm['join_date']); ?></span>
			<span><?php echo (int) $pm['games_this_month']; ?> games this month</span>
			<span>Peak <?php echo $pm['peak'] !== null ? (int) $pm['peak'] : '—'; ?></span>
		</p>
	</div>
	<?php if ($pm['display']) { ?>
	<div class="pm-a__rank-badge" aria-label="Ladder rank">
		<span>Rank</span>
		<strong>#<?php echo (int) $pm['rank']; ?></strong>
	</div>
	<?php } ?>
</section>

<div class="pm-a__stats-row">
	<div class="pm-a__stat-tile">
		<strong><?php echo $pm['rating'] !== null ? (int) $pm['rating'] : '—'; ?></strong>
		<span>Rating now</span>
	</div>
	<div class="pm-a__stat-tile">
		<strong><?php echo number_format($pm['games']); ?></strong>
		<span>Rated games</span>
	</div>
	<div class="pm-a__stat-tile">
		<strong><?php echo (int) $pm['longest_win_streak']; ?></strong>
		<span>Best win streak</span>
	</div>
	<div class="pm-a__stat-tile">
		<strong><?php echo (int) $pm['years_on_ladder']; ?>+</strong>
		<span>Years on ladder</span>
	</div>
</div>

<blockquote class="pm-a__pullquote">
	<?php echo pm_h($chronicle); ?>
</blockquote>

<h3 class="pm-a__section-title">Moments worth framing</h3>
<div class="pm-a__trophy-grid">
	<?php foreach ($pm['trophies'] as $t) { ?>
	<article class="pm-a__trophy">
		<div class="pm-a__trophy-icon" aria-hidden="true"><?php echo $t['icon']; ?></div>
		<p class="pm-muted" style="margin:0 0 4px;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;"><?php echo pm_h($t['tag']); ?></p>
		<h3><?php echo pm_h($t['label']); ?></h3>
		<p class="pm-a__trophy-score">
			<a href="game.php?id=<?php echo (int) $t['game_id']; ?>"><?php echo pm_h($t['score']); ?></a>
		</p>
		<p class="pm-a__trophy-meta">
			vs <a href="individual1.php?id=<?php echo (int) $t['opponent_id']; ?>"><?php echo pm_h($t['opponent_name']); ?></a>
			· <?php echo pm_h($t['year']); ?>
			· <span class="<?php echo pm_h($t['outcome_class']); ?>"><?php echo pm_h($t['outcome']); ?></span>
		</p>
	</article>
	<?php } ?>
</div>

<section class="pm-a__chart-panel pm-section-gap">
	<h3 class="pm-chart-title">Rating journey</h3>
	<p class="pm-muted">The arc behind the headline numbers — peak and current marked on the same timeline.</p>
	<div class="player-rating-chart" data-player-id="<?php echo (int) $pm['id']; ?>">
		<p class="player-rating-chart-status pm-muted">Loading rating history…</p>
		<p class="player-rating-peak-current-summary" style="display:none;margin:0 0 8px;font-size:1.05em;color:var(--k2-text-primary);"></p>
		<canvas width="960" height="320" aria-label="Rating over time"></canvas>
	</div>
</section>

<details class="pm-a__depth">
	<summary>Matchup lab &amp; analyst charts (collapsed in this mock)</summary>
	<div class="pm-a__depth-inner">
		<p>Production ships win-rate buckets, top opponents, H2H, and compare-rating here. In the chosen direction, this block stays available but <strong>below</strong> the story layer — never the first thing you see.</p>
		<div class="pm-a__depth-tags">
			<span>Win rate vs opponent rating</span>
			<span>Top 20 opponents</span>
			<span>Head-to-head cumulative</span>
			<span>Compare rating history</span>
			<span>Full playertable ledger → other tabs</span>
		</div>
	</div>
</details>

</div>

<?php mysqli_close($con); ?>
</body>
</html>
