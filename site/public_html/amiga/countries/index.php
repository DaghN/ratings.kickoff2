<?php
/**
 * Legacy Countries hub path. Canonical hub place is the plural leaf file
 * (same pattern as tournaments.php). 302 to /amiga/countries.php, preserving query.
 *
 * @see docs/amiga-countries-hub-policy.md CH3
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';

k2_amiga_legacy_redirect('/amiga/countries.php');