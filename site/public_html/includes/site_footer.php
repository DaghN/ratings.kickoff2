<?php
/**
 * Global site footer — provenance and contact only (not editorial discovery).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/site_footer_links.php';

function k2_site_footer_render(): void
{
    $links = k2_site_footer_links();
    $year = (int) date('Y');
    $name = htmlspecialchars($links['copyright_name'], ENT_QUOTES, 'UTF-8');
    $aboutHref = htmlspecialchars($links['about_href'], ENT_QUOTES, 'UTF-8');
    $email = $links['contact_email'];
    $emailEsc = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $mailto = 'mailto:' . rawurlencode($email);
    ?>
<footer class="k2-site-footer" aria-label="Site">
	<p class="k2-site-footer__line">
		<span class="k2-site-footer__copy">&copy; <?php echo $year; ?> <?php echo $name; ?></span>
		<span class="k2-site-footer__sep" aria-hidden="true">&middot;</span>
		<a href="<?php echo $aboutHref; ?>">About</a>
		<span class="k2-site-footer__sep" aria-hidden="true">&middot;</span>
		<span class="k2-site-footer__contact">Contact: <a href="<?php echo htmlspecialchars($mailto, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $emailEsc; ?></a></span>
	</p>
</footer>
    <?php
}
