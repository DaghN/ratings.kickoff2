<?php
/**
 * Site jukebox — floating opt-in player (all themed pages).
 */
declare(strict_types=1);

$k2JukeboxDocRoot = $_SERVER['DOCUMENT_ROOT'];
$k2JukeboxJsVer = (int) @filemtime($k2JukeboxDocRoot . '/js/k2-jukebox.js');
?>
<div class="k2-jukebox" data-k2-jukebox aria-live="polite">
	<div class="k2-jukebox__panel" id="k2-jukebox-panel" hidden>
		<div class="k2-jukebox__head">
			<div class="k2-jukebox__brand">
				<span class="k2-jukebox__kicker">Amiga Jukebox</span>
				<div class="k2-jukebox__now-title">Choose a track</div>
				<div class="k2-jukebox__now-game">Amiga classics</div>
			</div>
			<button type="button" class="k2-jukebox__hide" aria-label="Hide jukebox panel">Hide</button>
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
			<button type="button" class="k2-jukebox__shuffle" aria-label="Shuffle playback order" aria-pressed="false" title="Shuffle off">
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
	<button
		type="button"
		class="k2-jukebox__toggle"
		aria-expanded="false"
		aria-controls="k2-jukebox-panel"
		aria-label="Open Amiga jukebox"
		title="Amiga jukebox (Alt+M)"
	>
		<svg class="k2-jukebox__toggle-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
			<rect x="5" y="4" width="14" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.5"/>
			<rect x="8" y="7" width="8" height="5" rx="1" fill="currentColor" opacity="0.35"/>
			<line class="k2-jukebox__toggle-led" x1="8" y1="15" x2="16" y2="15"/>
			<line class="k2-jukebox__toggle-led" x1="8" y1="17.5" x2="13" y2="17.5"/>
		</svg>
	</button>
	<audio class="k2-jukebox__audio" preload="metadata"></audio>
</div>
<script type="text/javascript" src="/js/k2-jukebox.js?v=<?php echo $k2JukeboxJsVer; ?>" defer="defer"></script>