<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_country_rivals_lib.php';

$country = amiga_countries_normalize_country_param((string) ($_GET['country'] ?? ''));
$params = $country !== '' ? ['country' => $country] : [];
header('Location: ' . k2_amiga_route('amiga-country-rivals-h2h', $params), true, 302);
exit;