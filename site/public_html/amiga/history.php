<?php
/**
 * Legacy URL — History hub tab removed Jun 2026. Redirect to rating leaderboard.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_rating_history_lib.php';

$target = '/amiga/leaderboards/rating.php';
$query = [];

if (isset($_GET['as'])) {
    $as = trim((string) $_GET['as']);
    if ($as !== '' && amiga_snapshot_parse_as_param($as) !== null) {
        $query['as'] = $as;
    }
} elseif (isset($_GET['wing'], $_GET['at'])) {
    $at = trim((string) $_GET['at']);
    if ($at !== '') {
        $wing = amiga_rating_history_normalize_wing((string) $_GET['wing']);
        $query['as'] = amiga_snapshot_format_as_param($wing, $at);
    }
}

$location = $target;
if ($query !== []) {
    $location .= '?' . http_build_query($query);
}

header('Location: ' . $location, true, 301);
exit;
