<?php
/**
 * Compatibility shim — prefer amiga/includes/amiga_ops_password_lib.php from PHP pages.
 * Kept so older requires of site/config/amiga_ops_password.php still work locally.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/public_html/amiga/includes/amiga_ops_password_lib.php';