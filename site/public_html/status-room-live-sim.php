<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 — Status live sim</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="js/status-room-live-sim.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/status-room-live-sim.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>
<?php
$k2HubTabActive = 'status';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_room_live_sim.php';
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
$simAllowed = k2_status_room_sim_is_allowed();
?>
<main class="k2-page-main" id="k2-status-room-live-sim" data-k2-sim-allowed="<?php echo $simAllowed ? '1' : '0'; ?>">
  <h1 class="k2-page-title">Status live sim</h1>
  <p class="k2-lead">Work DB only — realistic lobby activity for Status live pulse. Open Status in another tab so pulse ticks drive the sim.</p>
<?php if (!$simAllowed): ?>
  <p class="k2-notice k2-notice--warn">Not available on this host. Use <strong>work.ratingskickoff.test</strong>.</p>
<?php else: ?>
  <fieldset class="k2-sim-options" id="k2-sim-options">
    <legend class="k2-sim-options__legend">Run options</legend>
    <p class="k2-sim-options__row">
      <label for="k2-sim-games">Games (L3)</label>
      <input type="number" id="k2-sim-games" min="0" max="40" value="20" />
    </p>
    <p class="k2-sim-options__row">
      <label for="k2-sim-registrations">Registrations (L2)</label>
      <input type="number" id="k2-sim-registrations" min="0" max="10" value="3" />
    </p>
    <p class="k2-sim-options__row">
      <label for="k2-sim-crash">Game crash %</label>
      <input type="number" id="k2-sim-crash" min="0" max="20" value="5" />
    </p>
    <p class="k2-sim-options__row k2-sim-options__checks">
      <label><input type="checkbox" id="k2-sim-l1" checked="checked" /> L1 lobby (login/logout)</label>
      <label><input type="checkbox" id="k2-sim-l2" checked="checked" /> L2 registration</label>
      <label><input type="checkbox" id="k2-sim-l3" checked="checked" /> L3 games</label>
    </p>
  </fieldset>
  <div class="k2-sim-controls">
    <button type="button" class="k2-btn k2-btn--primary" id="k2-sim-start">Start sim</button>
    <button type="button" class="k2-btn" id="k2-sim-stop">Stop</button>
    <a class="k2-btn k2-btn--link" href="/status.php">Open Status &rarr;</a>
  </div>
  <p id="k2-sim-message" class="k2-sim-message" aria-live="polite"></p>
  <dl class="k2-sim-status" id="k2-sim-status">
    <dt>State</dt><dd data-k2-sim-field="active">idle</dd>
    <dt>Progress</dt><dd data-k2-sim-field="progress">0 / 0</dd>
    <dt>Registrations</dt><dd data-k2-sim-field="registrations">0 / 0</dd>
    <dt>Online</dt><dd data-k2-sim-field="online_count">0</dd>
    <dt>Live now</dt><dd data-k2-sim-field="live_count">0</dd>
    <dt>Queued</dt><dd data-k2-sim-field="queued_count">0</dd>
    <dt>Last event</dt><dd data-k2-sim-field="last_event">—</dd>
  </dl>
<?php endif; ?>
</main>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>