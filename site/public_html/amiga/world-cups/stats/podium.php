<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';

$query = $_SERVER['QUERY_STRING'] ?? '';
$target = k2_amiga_route('amiga-world-cups-chronology');
if ($query !== '') {
    $target .= (str_contains($target, '?') ? '&' : '?') . $query;
}
header('Location: ' . $target, true, 302);
exit;
