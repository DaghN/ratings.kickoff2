<?php
/**
 * Legacy URL — player games tab lives under amiga/player/ (Jun 2026 URL taxonomy).
 * Realm-wide match log will use this path in a future slice.
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';
k2_amiga_legacy_redirect(k2_amiga_route('amiga-player-games'));
