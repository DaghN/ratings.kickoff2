<?php
/**
 * Legacy country roster path. A single country is now an entity page
 * (docs/navigation-model.md NM3) under the singular `country/` namespace.
 * 302 to /amiga/country/roster.php, preserving ?country= and ?as=.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';

k2_amiga_legacy_redirect('/amiga/country/roster.php');