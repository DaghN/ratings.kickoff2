<?php
declare(strict_types=1);

/**
 * Legacy entry — redirect to Garden wing (query preserved).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$params = $id > 0 ? ['id' => $id] : [];
$target = k2_route('player-milestones-garden', $params);

header('Location: ' . $target, true, 302);
exit;
