<?php
/**
 * Amiga ops password loader (committed).
 *
 * Credentials: amiga_ops_password.local.php (gitignored).
 * Used by import/export one-shots and amiga/ops/fixtures.php.
 *
 * Setup: copy amiga_ops_password.local.php.example → amiga_ops_password.local.php
 * Staging: place the same local file on the server under site/config/ (not in git / not public_html).
 */
declare(strict_types=1);

/**
 * @return non-empty-string
 */
function amiga_ops_require_password(): string
{
    static $cached = null;
    if (is_string($cached) && $cached !== '') {
        return $cached;
    }

    $configFile = __DIR__ . '/amiga_ops_password.local.php';
    if (!is_file($configFile)) {
        $hint = 'Copy amiga_ops_password.local.php.example → amiga_ops_password.local.php (gitignored).';
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Missing Amiga ops password config: {$configFile}\n{$hint}\n");
            exit(1);
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Amiga ops password config missing.\n{$hint}\n";
        exit;
    }

    $password = '';
    require $configFile;
    if (!is_string($password) || $password === '') {
        $msg = 'amiga_ops_password.local.php must set non-empty $password.';
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $msg . "\n");
            exit(1);
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $msg . "\n";
        exit;
    }

    $cached = $password;
    return $cached;
}