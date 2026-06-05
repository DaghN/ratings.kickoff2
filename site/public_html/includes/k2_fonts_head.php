<?php
/**
 * Self-hosted web fonts — preload critical faces before CSS (wordmark + body).
 * @font-face rules: stylesheets/k2-fonts.css. Files: fonts/*.woff2
 */
$k2DocRoot = $_SERVER['DOCUMENT_ROOT'];
$k2FontsPreloadVer = (int) max(
	@filemtime($k2DocRoot . '/fonts/exo-2-latin-600-normal.woff2'),
	@filemtime($k2DocRoot . '/fonts/ibm-plex-sans-latin-400-normal.woff2')
);
?>
<link rel="preload" href="/fonts/exo-2-latin-600-normal.woff2?v=<?php echo $k2FontsPreloadVer; ?>" as="font" type="font/woff2" crossorigin="anonymous" />
<link rel="preload" href="/fonts/ibm-plex-sans-latin-400-normal.woff2?v=<?php echo $k2FontsPreloadVer; ?>" as="font" type="font/woff2" crossorigin="anonymous" />
