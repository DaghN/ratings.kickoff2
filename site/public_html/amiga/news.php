<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga 500 — News</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script src="/js/chart.umd.min.js"></script>
<script src="/js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="/js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script type="text/javascript" src="/js/amiga-top10-rating-race.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/amiga-top10-rating-race.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/amiga-top10-rating-race-by-time.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/amiga-top10-rating-race-by-time.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site k2-amiga-news">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'news';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';
?>

<header class="k2-hub-chapter">
  <h1 class="k2-hub-chapter__title">News</h1>
  <p class="k2-hub-chapter__lede">Realm highlights and experiments — two views of the top-10 Elo race.</p>
</header>

<section class="k2-amiga-rating-race" data-amiga-rating-race aria-label="Top 10 Elo line race by tournament">
  <h2 class="k2-panel-heading">Top 10 Elo race — by tournament</h2>
  <p class="k2-amiga-rating-race__hint">Playhead advances event by event. Lines move smoothly between consecutive tournaments.</p>

  <div class="k2-amiga-rating-race__controls">
    <button type="button" class="k2-amiga-rating-race__play" data-amiga-race-play aria-pressed="false">Play</button>
    <label class="k2-amiga-rating-race__speed">
      <span class="k2-amiga-rating-race__speed-label">Speed</span>
      <select class="k2-amiga-rating-race__speed-select" data-amiga-race-speed>
        <option value="0.5">0.5×</option>
        <option value="1" selected="selected">1×</option>
        <option value="2">2×</option>
        <option value="4">4×</option>
      </select>
    </label>
    <input type="range" class="k2-amiga-rating-race__slider" data-amiga-race-slider min="0" max="0" value="0" aria-label="Tournament timeline" />
  </div>

  <p class="k2-amiga-rating-race__meta" data-amiga-race-meta></p>

  <div class="k2-chart-frame k2-chart-frame--tall">
    <canvas data-amiga-race-chart role="img" aria-label="Top ten Elo race stepping by tournament"></canvas>
  </div>

  <p class="k2-amiga-rating-race__footnote" hidden="hidden" data-amiga-race-empty>Browse <a href="/amiga/history.php">Historical ladder</a> when rating data is available.</p>
  <p class="k2-amiga-rating-race__footnote" hidden="hidden" data-amiga-race-error>Could not load race data.</p>
</section>

<section class="k2-amiga-rating-race k2-amiga-rating-race--by-time" data-amiga-rating-race-time aria-label="Top 10 Elo line race by calendar time">
  <h2 class="k2-panel-heading">Top 10 Elo race — by time</h2>
  <p class="k2-amiga-rating-race__hint">Playhead advances on the calendar. Each line is straight segments between that player&rsquo;s rating events (no steps).</p>

  <div class="k2-amiga-rating-race__controls">
    <button type="button" class="k2-amiga-rating-race__play" data-amiga-race-play aria-pressed="false">Play</button>
    <label class="k2-amiga-rating-race__speed">
      <span class="k2-amiga-rating-race__speed-label">Speed</span>
      <select class="k2-amiga-rating-race__speed-select" data-amiga-race-speed>
        <option value="0.5">0.5×</option>
        <option value="1" selected="selected">1×</option>
        <option value="2">2×</option>
        <option value="4">4×</option>
      </select>
    </label>
    <input type="range" class="k2-amiga-rating-race__slider" data-amiga-race-slider min="0" max="1000" value="1000" aria-label="Calendar timeline" />
  </div>

  <p class="k2-amiga-rating-race__meta" data-amiga-race-meta></p>

  <div class="k2-chart-frame k2-chart-frame--tall">
    <canvas data-amiga-race-chart role="img" aria-label="Top ten Elo race advancing by calendar time"></canvas>
  </div>

  <p class="k2-amiga-rating-race__footnote" hidden="hidden" data-amiga-race-empty>Browse <a href="/amiga/history.php">Historical ladder</a> when rating data is available.</p>
  <p class="k2-amiga-rating-race__footnote" hidden="hidden" data-amiga-race-error>Could not load race data.</p>
</section>

</body>
</html>
