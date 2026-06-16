<?php
/**
 * Legacy URL — Activity wing Peaks segment (bookmarks / HoF peak links).
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/k2_routes.php';

header('Location: ' . k2_route('lb-activity-peaks'), true, 301);
exit;
