<?php
/**
 * Amiga ops passwords (import / export / fixtures).
 *
 * Looks for amiga_ops_password.local.php in order:
 * 1) public_html/amiga/_ops/  — WinSCP-deployable (staging /config is root-owned)
 * 2) site/config/             — local Laragon sibling layout
 *
 * Config keys (preferred):
 *   $admin_password     — import / export (DB dump & reload)
 *   $organizer_password — tournament organizer (fixtures)
 *
 * Legacy: single $password is used for both roles if the new keys are empty.
 * Never commit the local password file.
 */
declare(strict_types=1);

/**
 * @return array{admin: non-empty-string, organizer: non-empty-string}
 */
function amiga_ops_load_passwords(): array
{
    static $cached = null;
    if (is_array($cached)) {
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
        $hint = 'Create amiga/_ops/amiga_ops_password.local.php (copy from .example) and WinSCP-sync it.';
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Missing Amiga ops password config.\n{$hint}\n");
            exit(1);
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Amiga ops password config missing.\n{$hint}\n";
        exit;
    }

    $admin_password = '';
    $organizer_password = '';
    $password = '';
    require $configFile;

    $admin = is_string($admin_password) ? $admin_password : '';
    $organizer = is_string($organizer_password) ? $organizer_password : '';
    $legacy = is_string($password) ? $password : '';

    if ($admin === '' && $legacy !== '') {
        $admin = $legacy;
    }
    if ($organizer === '' && $legacy !== '') {
        $organizer = $legacy;
    }

    if ($admin === '' || $organizer === '') {
        $msg = 'amiga_ops_password.local.php must set non-empty $admin_password and $organizer_password (or legacy $password).';
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $msg . "\n");
            exit(1);
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo $msg . "\n";
        exit;
    }

    $cached = ['admin' => $admin, 'organizer' => $organizer];
    return $cached;
}

/**
 * @deprecated Use amiga_ops_load_passwords() / amiga_ops_gate()
 * @return non-empty-string admin password (legacy callers)
 */
function amiga_ops_require_password(): string
{
    return amiga_ops_load_passwords()['admin'];
}

function amiga_ops_auth_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * @param 'admin'|'organizer' $role
 */
function amiga_ops_password_matches(string $provided, string $role): bool
{
    if ($provided === '') {
        return false;
    }
    $passwords = amiga_ops_load_passwords();
    if ($role === 'admin') {
        return hash_equals($passwords['admin'], $provided);
    }
    // Organizer gate: organizer password or admin (admins can always organize).
    return hash_equals($passwords['organizer'], $provided)
        || hash_equals($passwords['admin'], $provided);
}

/**
 * @param 'admin'|'organizer' $role
 */
function amiga_ops_auth_grant(string $role): void
{
    amiga_ops_auth_session_start();
    $rank = $role === 'admin' ? 2 : 1;
    $current = (int) ($_SESSION['amiga_ops_auth_rank'] ?? 0);
    if ($rank > $current) {
        $_SESSION['amiga_ops_auth_rank'] = $rank;
        $_SESSION['amiga_ops_auth_role'] = $role === 'admin' ? 'admin' : 'organizer';
    }
}

/**
 * @param 'admin'|'organizer' $requiredRole
 */
function amiga_ops_auth_has(string $requiredRole): bool
{
    amiga_ops_auth_session_start();
    $rank = (int) ($_SESSION['amiga_ops_auth_rank'] ?? 0);
    if ($requiredRole === 'organizer') {
        return $rank >= 1;
    }
    return $rank >= 2;
}

/**
 * Read password from POST (preferred) or GET (legacy bookmarks / scripts).
 */
function amiga_ops_request_password(): string
{
    if (isset($_POST['pwd']) && is_string($_POST['pwd']) && $_POST['pwd'] !== '') {
        return $_POST['pwd'];
    }
    if (isset($_GET['pwd']) && is_string($_GET['pwd']) && $_GET['pwd'] !== '') {
        return $_GET['pwd'];
    }
    return '';
}

/**
 * Gate a page for $requiredRole. On success may redirect to strip ?pwd= from the URL.
 *
 * @param 'admin'|'organizer' $requiredRole
 * @return array{ok: bool, provided: bool, via: 'session'|'password'|''}
 */
function amiga_ops_gate(string $requiredRole): array
{
    amiga_ops_auth_session_start();

    if (amiga_ops_auth_has($requiredRole)) {
        return ['ok' => true, 'provided' => false, 'via' => 'session'];
    }

    $provided = amiga_ops_request_password();
    if ($provided === '') {
        return ['ok' => false, 'provided' => false, 'via' => ''];
    }

    if (!amiga_ops_password_matches($provided, $requiredRole)) {
        return ['ok' => false, 'provided' => true, 'via' => ''];
    }

    $grantRole = 'organizer';
    if ($requiredRole === 'admin' || hash_equals(amiga_ops_load_passwords()['admin'], $provided)) {
        $grantRole = 'admin';
    }
    amiga_ops_auth_grant($grantRole);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    // Browser login forms: redirect to a clean URL (no pwd). Script/action POSTs continue
    // in-place so generate/apply/download keep working without a cookie jar.
    $actionPost = $method === 'POST' && (
        isset($_POST['generate'])
        || isset($_POST['apply'])
        || isset($_POST['download'])
        || isset($_POST['status'])
        || isset($_POST['format'])
        || isset($_POST['action'])
    );

    if (!$actionPost && !empty($_SERVER['SCRIPT_NAME'])) {
        $src = $method === 'POST' ? $_POST : $_GET;
        if (!is_array($src)) {
            $src = [];
        }
        $qs = [];
        foreach ($src as $k => $v) {
            if (!is_string($k) || is_array($v)) {
                continue;
            }
            if ($k === 'pwd') {
                continue;
            }
            $qs[$k] = (string) $v;
        }
        $shouldRedirect = ($method === 'POST') || isset($_GET['pwd']);
        if ($shouldRedirect) {
            $target = (string) $_SERVER['SCRIPT_NAME'];
            if ($qs !== []) {
                $target .= '?' . http_build_query($qs);
            }
            header('Location: ' . $target);
            exit;
        }
    }

    return ['ok' => true, 'provided' => true, 'via' => 'password'];
}

/**
 * HTML password gate form (POST). Keeps non-secret query flags as hidden fields.
 *
 * @param array<string, string> $hidden extra hidden fields (e.g. once, apply, generate)
 */
function amiga_ops_render_password_form(
    string $action,
    string $title,
    string $blurb,
    array $hidden,
    bool $pwdProvidedFail,
    string $roleLabel = 'Password'
): void {
    header('Content-Type: text/html; charset=utf-8');
    $actionEsc = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $blurbEsc = htmlspecialchars($blurb, ENT_QUOTES, 'UTF-8');
    $labelEsc = htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . $titleEsc . '</title>';
    echo '<style>body{font-family:system-ui,sans-serif;max-width:32rem;margin:2rem auto;line-height:1.5}';
    echo 'input[type=password]{width:100%;padding:.5rem;font-size:1rem;box-sizing:border-box}';
    echo 'button{margin-top:.75rem;padding:.5rem 1rem;font-size:1rem}';
    echo '.fail{color:#c0392b;font-weight:600}</style></head><body>';
    echo '<h1>' . $titleEsc . '</h1>';
    if ($pwdProvidedFail) {
        echo '<p class="fail">Incorrect password.</p>';
    } else {
        echo '<p>' . $blurbEsc . '</p>';
    }
    echo '<form method="post" action="' . $actionEsc . '">';
    foreach ($hidden as $name => $value) {
        echo '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="'
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">';
    }
    echo '<p><label for="pwd">' . $labelEsc . '</label><br>';
    echo '<input type="password" id="pwd" name="pwd" autocomplete="current-password" required autofocus></p>';
    echo '<button type="submit">Continue</button></form></body></html>';
}