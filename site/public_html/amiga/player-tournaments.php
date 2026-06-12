<?php
/**
 * Legacy URL — player tournament history lives under amiga/player/ (Jun 2026 URL taxonomy).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';
k2_amiga_legacy_redirect(k2_amiga_route('amiga-player-tournaments'));
