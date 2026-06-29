<?php
/**
 * Amiga jukebox — standalone popup-window player page.
 *
 * Opened in its own window by js/k2-jukebox-launcher.js so playback survives full-page
 * navigation in the main tab. Reuses the jukebox cockpit markup + styles; player logic
 * lives in js/k2-jukebox-player.js. See docs/k2-jukebox-popup.md.
 */
declare(strict_types=1);

$docRoot = $_SERVER['DOCUMENT_ROOT'];

function k2_jukebox_asset_ver(string $relPath): int
{
    $full = $_SERVER['DOCUMENT_ROOT'] . $relPath;
    return is_file($full) ? (int) filemtime($full) : 0;
}
?>
<!doctype html>
<html lang="en" data-realm="amiga" data-k2-accent="amber" style="background:#0b0f14;color-scheme:dark">
<head>
	<meta charset="utf-8" />
	<meta name="color-scheme" content="dark" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<style>
		/* Pre-stylesheet paint — matches :root --k2-bg-page / --k2-text-primary (theme.css). */
		html,
		body {
			background-color: #0b0f14;
			color: #d0d7de;
		}
		body.k2-jukebox-window .k2-jukebox__panel {
			background-color: #131922;
		}
	</style>
	<title>Amiga Jukebox</title>
	<?php include $docRoot . '/includes/k2_fonts_head.php'; ?>
	<link href="/stylesheets/k2-fonts.css?v=<?php echo k2_jukebox_asset_ver('/stylesheets/k2-fonts.css'); ?>" rel="stylesheet" type="text/css" />
	<link href="/stylesheets/theme.css?v=<?php echo k2_jukebox_asset_ver('/stylesheets/theme.css'); ?>" rel="stylesheet" type="text/css" />
	<link href="/stylesheets/k2-jukebox.css?v=<?php echo k2_jukebox_asset_ver('/stylesheets/k2-jukebox.css'); ?>" rel="stylesheet" type="text/css" />
	<?php include $docRoot . '/includes/theme_boot_head.php'; ?>
	<style>
		html {
			/* No reserved gutter and no window scroll — only the playlist scrolls. */
			scrollbar-gutter: auto;
			overflow: hidden;
		}
		html,
		body.k2-jukebox-window {
			margin: 0;
			padding: 0;
			height: 100%;
			background: var(--k2-bg-page);
		}
		body.k2-jukebox-window {
			overflow: hidden;
		}
		body.k2-jukebox-window .k2-jukebox--window {
			position: static;
			inset: auto;
			width: 100%;
			height: 100vh;
			margin: 0;
			padding: 0;
			gap: 0;
			z-index: auto;
			align-items: stretch;
			justify-content: flex-start;
			pointer-events: auto;
			overflow: hidden;
		}
		body.k2-jukebox-window .k2-jukebox--window .k2-jukebox__panel {
			width: 100%;
			height: 100vh;
			max-height: 100vh;
			min-height: 0;
			flex: 1 1 auto;
			border: 0;
			border-radius: 0;
			box-shadow: none;
			backdrop-filter: none;
			background: var(--k2-bg-surface);
			overflow: hidden;
		}
	</style>
</head>
<body class="k2-site k2-jukebox-window">
	<div class="k2-jukebox k2-jukebox--window is-open" id="k2-jukebox-root" data-k2-jukebox aria-live="polite">
		<div class="k2-jukebox__panel">
			<div class="k2-jukebox__head">
				<div class="k2-jukebox__brand">
					<span class="k2-jukebox__kicker">Amiga Jukebox</span>
					<div class="k2-jukebox__now-title">Choose a track</div>
					<div class="k2-jukebox__now-game">Amiga classics</div>
				</div>
				<button type="button" class="k2-jukebox__hide k2-jukebox__close" aria-label="Close jukebox window">Close</button>
			</div>
			<div class="k2-jukebox__vu" aria-hidden="true">
				<span class="k2-jukebox__vu-bar"></span>
				<span class="k2-jukebox__vu-bar"></span>
				<span class="k2-jukebox__vu-bar"></span>
				<span class="k2-jukebox__vu-bar"></span>
				<span class="k2-jukebox__vu-bar"></span>
			</div>
			<div class="k2-jukebox__progress-wrap">
				<div
					class="k2-jukebox__progress"
					role="slider"
					tabindex="0"
					aria-label="Seek"
					aria-valuemin="0"
					aria-valuemax="100"
					aria-valuenow="0"
				>
					<span class="k2-jukebox__progress-fill"></span>
				</div>
				<div class="k2-jukebox__times">
					<span class="k2-jukebox__time-current">0:00</span>
					<span class="k2-jukebox__time-total">0:00</span>
				</div>
			</div>
			<div class="k2-jukebox__transport">
				<div class="k2-jukebox__controls">
					<button type="button" class="k2-jukebox__btn k2-jukebox__prev" aria-label="Previous track">
						<span class="k2-jukebox__btn-icon k2-jukebox__btn-icon--prev" aria-hidden="true"></span>
					</button>
					<button type="button" class="k2-jukebox__btn k2-jukebox__btn--play k2-jukebox__play" aria-label="Play" aria-pressed="false">
						<span class="k2-jukebox__btn-icon k2-jukebox__btn-icon--play" aria-hidden="true"></span>
					</button>
					<button type="button" class="k2-jukebox__btn k2-jukebox__next" aria-label="Next track">
						<span class="k2-jukebox__btn-icon k2-jukebox__btn-icon--next" aria-hidden="true"></span>
					</button>
				</div>
				<button type="button" class="k2-jukebox__shuffle" aria-label="Shuffle playback order" aria-pressed="false">
					Shuffle
				</button>
				<div class="k2-jukebox__volume-wrap">
					<label class="k2-jukebox__volume-label" for="k2-jukebox-volume">Vol</label>
					<input
						type="range"
						class="k2-jukebox__volume"
						id="k2-jukebox-volume"
						min="0"
						max="100"
						value="72"
						aria-label="Volume"
					/>
				</div>
			</div>
			<div class="k2-jukebox__tracks-wrap">
				<ul class="k2-jukebox__tracks" role="listbox" aria-label="Amiga jukebox playlist"></ul>
			</div>
		</div>
		<audio class="k2-jukebox__audio" preload="metadata"></audio>
	</div>
	<script type="text/javascript">window.__k2JukeboxPlaylistVer=<?php echo k2_jukebox_asset_ver('/audio/amiga/playlist.json'); ?>;</script>
	<script type="text/javascript" src="/js/k2-jukebox-player.js?v=<?php echo k2_jukebox_asset_ver('/js/k2-jukebox-player.js'); ?>"></script>
</body>
</html>