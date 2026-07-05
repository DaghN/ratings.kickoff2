<?php
/**
 * Close .k2-page-nav (opened in site_header.php) and render global site footer.
 */
?>
</div><!-- .k2-page-nav -->
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/site_footer.php';
k2_site_footer_render();
?>
