<?php
/**
 * Connexion en 2 étapes :
 *   1) email + mot de passe   2) code à 6 chiffres reçu par email.
 * Les codes sont stockés hachés, expirent vite et ont un nombre d'essais limité.
 */

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = empty($_SESSION['user_id']) ? null
            : fetch_one('SELECT id, email, display_name, last_login_at FROM users WHERE id = ?', [$_SESSION['user_id']]);
    }
    return $user;
}

/** À appeler en haut de chaque page privée. */
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

/** Trop d'échecs récents depuis cette adresse IP ou pour cet email ? */
function is_locked_out(string $email): bool
{
    $since = date('Y-m-d H:i:s', time() - config('security.lockout_minutes', 15) * 60);
    $max = config('security.max_login_failures', 5);
    $fails = (int)fetch_value(
        'SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at > ? AND (ip = ? OR email = ?)',
        [$since, client_ip(), mb_strtolower($email)]
    );
    return $fails >= $max;
}

function record_attempt(string $email, string $kind, bool $success): void
{
    q('INSERT INTO login_attempts (ip, email, kind, success, created_at) VALUES (?, ?, ?, ?, ?)',
        [client_ip(), mb_strtolower(mb_substr($email, 0, 190)), $kind, $success ? 1 : 0, now()]);
    // Nettoyage des vieilles tentatives (> 30 jours)
    q('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', strtotime('-30 days'))]);
}

/** Étape 1. Retourne null si OK, sinon un message d'erreur. */
function login_step_password(string $email, string $password): ?string
{
    $email = mb_strtolower(trim($email));
    if (is_locked_out($email)) {
        return 'Trop de tentatives. Réessayez dans ' . config('security.lockout_minutes', 15) . ' minutes.';
    }
    $user = fetch_one('SELECT * FROM users WHERE email = ?', [$email]);
    // password_verify est appelé même si l'email n'existe pas (temps de réponse identique).
    $hash = $user['password_hash'] ?? '$2y$12$wD/wZ3swocQjzh1TO2RU2e4K9X/jHzfCmETsTkHo6bTglTdxPyut.';
    $ok = password_verify($password, $hash) && $user;
    record_attempt($email, 'password', (bool)$ok);
    if (!$ok) {
        audit('login_failed', $email, null);
        return 'Email ou mot de passe incorrect.';
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['pending_2fa'] = ['user_id' => (int)$user['id'], 'time' => time()];
    return send_login_code($user) ? null : "L'email contenant le code n'a pas pu être envoyé. Vérifiez la configuration.";
}

function send_login_code(array $user): bool
{
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $minutes = config('security.code_valid_minutes', 10);
    q('DELETE FROM auth_codes WHERE user_id = ?', [$user['id']]); // un seul code valide à la fois
    q('INSERT INTO auth_codes (user_id, code_hash, expires_at, attempts, created_at) VALUES (?, ?, ?, 0, ?)',
        [$user['id'], hash_code($code), date('Y-m-d H:i:s', time() + $minutes * 60), now()]);
    $_SESSION['pending_2fa']['sent_at'] = time();

    $app = config('app_name', 'Journal de classe');
    $body = "Bonjour {$user['display_name']},\n\n"
        . "Votre code de connexion à $app : $code\n\n"
        . "Ce code est valable $minutes minutes.\n"
        . "Si vous n'êtes pas à l'origine de cette demande, changez votre mot de passe.\n\n"
        . 'Demande effectuée le ' . date('d/m/Y à H:i') . ' depuis l\'adresse IP ' . client_ip() . ".\n";
    return send_mail($user['email'], "$code — votre code de connexion", $body);
}

function hash_code(string $code): string
{
    return hash_hmac('sha256', $code, (string)config('secret_key'));
}

function pending_2fa_user(): ?array
{
    $p = $_SESSION['pending_2fa'] ?? null;
    if (!$p || time() - $p['time'] > 20 * 60) {
        return null;
    }
    return fetch_one('SELECT * FROM users WHERE id = ?', [$p['user_id']]);
}

/** Étape 2. Retourne null si OK, sinon un message d'erreur. */
function login_step_code(string $code): ?string
{
    $user = pending_2fa_user();
    if (!$user) {
        return 'Session expirée, recommencez la connexion.';
    }
    $code = preg_replace('/\D/', '', $code);
    $row = fetch_one('SELECT * FROM auth_codes WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1', [$user['id']]);
    if (!$row || strtotime($row['expires_at']) < time()) {
        return 'Ce code a expiré. Demandez-en un nouveau.';
    }
    if ($row['attempts'] >= config('security.max_code_attempts', 5)) {
        return 'Trop d\'essais pour ce code. Demandez-en un nouveau.';
    }
    if (!hash_equals($row['code_hash'], hash_code($code))) {
        q('UPDATE auth_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
        record_attempt($user['email'], 'code', false);
        return 'Code incorrect.';
    }

    // Succès : on ouvre une session neuve (protection contre le vol de session).
    q('UPDATE auth_codes SET used_at = ? WHERE id = ?', [now(), $row['id']]);
    record_attempt($user['email'], 'code', true);
    session_regenerate_id(true);
    unset($_SESSION['pending_2fa']);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['auth_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['ua'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $_SESSION['previous_login'] = $user['last_login_at'];
    q('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $user['id']]);
    audit('login_success', '', (int)$user['id']);
    return null;
}

function logout(): void
{
    audit('logout');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Règle de mot de passe : 12 caractères minimum. */
function password_problem(string $password): ?string
{
    if (mb_strlen($password) < 12) {
        return 'Le mot de passe doit contenir au moins 12 caractères.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Le mot de passe doit contenir des lettres et des chiffres.';
    }
    return null;
}
