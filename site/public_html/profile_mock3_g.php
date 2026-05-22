<?php
/** @deprecated Use profile_feast.php — lab mock URL kept for bookmarks. */
$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';
header('Location: profile_feast.php' . $query, true, 302);
exit;
