<?php
/**
 * Legacy preview URL — permanent redirect to production Profile tab.
 */
$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';
header('Location: individual1.php' . $query, true, 301);
exit;
