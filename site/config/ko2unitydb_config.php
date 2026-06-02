<?php
/**
 * Host-based DB config router (committed).
 *
 *   http://ratingskickoff.test/       → ko2unitydb_config.local.php  (ko2unity_db)
 *   http://work.ratingskickoff.test/  → ko2unitydb_config_work.local.php (ko2unity_work)
 *
 * Credentials live in gitignored *.local.php — copy from *.example files.
 */
declare(strict_types=1);

$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$isWorkSite = (bool) preg_match('/^work\.ratingskickoff\.test$/', $host);

$configFile = __DIR__ . ($isWorkSite
    ? '/ko2unitydb_config_work.local.php'
    : '/ko2unitydb_config.local.php');

if (!is_file($configFile)) {
    $hint = $isWorkSite
        ? 'Copy ko2unitydb_config_work.local.php.example → ko2unitydb_config_work.local.php'
        : 'Copy ko2unitydb_config.local.php.example → ko2unitydb_config.local.php';

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Missing DB config: {$configFile}\n{$hint}\n");
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Database config missing.\n{$hint}\n";
    exit;
}

require $configFile;
