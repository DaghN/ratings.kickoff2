<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';

header('Location: ' . k2_amiga_route('amiga-world-cups-players-honours'), true, 302);
exit;
