<?php
/**
 * Legacy URL — player games tab lives under amiga/player/ when ?id= is set.
 * Realm Games hub default: Recent.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';

$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($playerId > 0) {
    k2_amiga_legacy_redirect(k2_amiga_route('amiga-player-games'));
}

header('Location: ' . k2_amiga_route('amiga-games-recent'), true, 302);
exit;
