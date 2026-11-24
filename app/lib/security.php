<?php
/** En-têtes de sécurité, session protégée et jetons anti-CSRF. */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function send_security_headers(): void
{
    // Le navigateur ne charge que les fichiers de ce site (pas de script externe).
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(self)');
    header('Cache-Control: no-store, private'); // pas de données élèves dans le cache
    if (is_https()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

function start_secure_session(): void
{
    $dir = ROOT . '/storage/sessions';
    if (is_dir($dir) && is_writable($dir)) {
        session_save_path($dir); // sessions stockées hors du dossier public
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string)(config('security.session_max_hours', 12) * 3600));
    session_name('jdc_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,     // inaccessible au JavaScript
        'samesite' => 'Lax',
    ]);
    session_start();

    // Déconnexion automatique après inactivité ou durée maximale.
    if (!empty($_SESSION['user_id'])) {
        $idle = config('security.session_idle_minutes', 30) * 60;
        $max = config('security.session_max_hours', 12) * 3600;
        $now = time();
        $expired = ($now - ($_SESSION['last_activity'] ?? 0) > $idle)
            || ($now - ($_SESSION['auth_time'] ?? 0) > $max)
            || (($_SESSION['ua'] ?? '') !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($expired) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['flash'][] = ['type' => 'info', 'message' => 'Votre session a expiré, reconnectez-vous.'];
        } else {
            $_SESSION['last_activity'] = $now;
        }
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** À placer dans chaque formulaire POST : <?= csrf_field() ?> */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(400);
        exit('Requête refusée (jeton de sécurité invalide). Rechargez la page et réessayez.');
    }
}

/** Trace les actions sensibles (connexions, changements de mot de passe…). */
function audit(string $action, string $details = '', ?int $userId = null): void
{
    try {
        q('INSERT INTO audit_log (user_id, action, details, ip, created_at) VALUES (?, ?, ?, ?, ?)',
            [$userId ?? ($_SESSION['user_id'] ?? null), $action, mb_substr($details, 0, 255), client_ip(), now()]);
    } catch (Throwable $e) {
        error_log('audit: ' . $e->getMessage());
    }
}
