<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_url.php';

header('Location: ' . amiga_url_with_context('/amiga/leaderboards/world-cups/honours.php'), true, 302);
exit;
