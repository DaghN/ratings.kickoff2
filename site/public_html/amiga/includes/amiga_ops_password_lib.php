<?php
/**
 * Amiga ops password loader (import / export / fixtures).
 *
 * Looks for amiga_ops_password.local.php in order:
 * 1) public_html/amiga/_ops/  — WinSCP-deployable (staging /config is root-owned)
 * 2) site/config/             — local Laragon sibling layout
 *
 * Never commit the local password file.
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

    $candidates = [];
    $candidates[] = dirname(__DIR__) . '/_ops/amiga_ops_password.local.php';
    $candidates[] = dirname(__DIR__, 3) . '/config/amiga_ops_password.local.php';
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $doc = (string) $_SERVER['DOCUMENT_ROOT'];
        $candidates[] = $doc . '/amiga/_ops/amiga_ops_password.local.php';
        $candidates[] = dirname($doc) . '/config/amiga_ops_password.local.php';
    }

    $configFile = null;
    foreach ($candidates as $path) {
        if (is_file($path)) {
            $configFile = $path;
            break;
        }
    }

    if ($configFile === null) {
        $hint = "Create amiga/_ops/amiga_ops_password.local.php (copy from .example) and WinSCP-sync it.";
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Missing Amiga ops password config.\n{$hint}\n");
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