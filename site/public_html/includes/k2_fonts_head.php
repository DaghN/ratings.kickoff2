<?php
/**
 * Self-hosted web fonts: preload every face the first paint needs (wordmark, menus, body).
 * The address must be exactly the one in stylesheets/k2-fonts.css, without ?v=: otherwise the preload is
 * wasted and the page is first drawn in the fallback font (menus jump). A new font file needs a new name.
 */
foreach ([
	'exo-2-latin-500-normal',
	'exo-2-latin-600-normal',
	'ibm-plex-sans-latin-400-normal',
	'ibm-plex-sans-latin-500-normal',
	'ibm-plex-sans-latin-600-normal',
	'ibm-plex-sans-latin-700-normal',
] as $k2FontFile) {
	echo '<link rel="preload" href="/fonts/' . $k2FontFile . '.woff2" as="font" type="font/woff2" crossorigin="anonymous" />' . "\n";
}
