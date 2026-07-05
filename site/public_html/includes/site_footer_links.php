<?php
/**
 * Canonical site footer metadata (copyright, about, maintainer contact).
 */
declare(strict_types=1);

/**
 * @return array{copyright_name: string, contact_email: string, about_href: string}
 */
function k2_site_footer_links(): array
{
    return [
        'copyright_name' => 'Dagh Nielsen',
        'contact_email' => 'daghnielsen@gmail.com',
        'about_href' => '/about.php',
    ];
}
