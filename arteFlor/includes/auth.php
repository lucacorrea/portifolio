<?php
require_once __DIR__ . '/helpers.php';

const ADMIN_SESSION_KEY = 'arteflor_admin_user';
const ADMIN_ACTIVITY_KEY = 'arteflor_admin_last_activity';
const ADMIN_CSRF_KEY = 'arteflor_admin_csrf';
const ADMIN_SESSION_TTL = 7200;
const ADMIN_LOGIN_MAX_ATTEMPTS = 5;
const ADMIN_LOGIN_WINDOW_MINUTES = 15;

function admin_request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_name('arteflor_admin_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_url(),
        'domain' => '',
        'secure' => admin_request_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    if (empty($_SESSION[ADMIN_CSRF_KEY])) {
        $_SESSION[ADMIN_CSRF_KEY] = bin2hex(random_bytes(32));
    }
}

function admin_csrf_token(): string
{
    admin_session_start();

    return (string) $_SESSION[ADMIN_CSRF_KEY];
}

function admin_csrf_is_valid(?string $token): bool
{
    admin_session_start();

    return is_string($token)
        && $token !== ''
        && hash_equals((string) $_SESSION[ADMIN_CSRF_KEY], $token);
}

function admin_current_user(): ?array
{
    admin_session_start();
    $user = $_SESSION[ADMIN_SESSION_KEY] ?? null;

    return is_array($user) ? $user : null;
}

function admin_is_authenticated(): bool
{
    admin_session_start();

    if (empty($_SESSION[ADMIN_SESSION_KEY]) || empty($_SESSION[ADMIN_ACTIVITY_KEY])) {
        return false;
    }

    $lastActivity = (int) $_SESSION[ADMIN_ACTIVITY_KEY];
    if ($lastActivity > 0 && (time() - $lastActivity) > ADMIN_SESSION_TTL) {
        admin_logout();
        return false;
    }

    return true;
}

function admin_touch_session(): void
{
    admin_session_start();
    $_SESSION[ADMIN_ACTIVITY_KEY] = time();
}

function admin_sanitize_next(?string $next): string
{
    $fallback = site_url('admin/dashboard.php');
    $next = trim((string) $next);

    if ($next === '') {
        return $fallback;
    }

    $path = parse_url($next, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return $fallback;
    }

    $base = rtrim(base_url(), '/');
    $adminPrefix = ($base === '' ? '' : $base) . '/admin/';
    if (!str_starts_with($path, $adminPrefix)) {
        return $fallback;
    }

    if (str_ends_with($path, '/login.php') || str_ends_with($path, '/logout.php')) {
        return $fallback;
    }

    $query = parse_url($next, PHP_URL_QUERY);

    return $path . ($query ? '?' . $query : '');
}

function admin_client_ip_hash(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

    return hash('sha256', $ip);
}

function admin_normalize_login(string $login): string
{
    return trim(strtolower($login));
}

function admin_login_is_rate_limited(string $login): bool
{
    $login = admin_normalize_login($login);
    if ($login === '') {
        return false;
    }

    try {
        $statement = db()->prepare(
            'SELECT COUNT(*) AS total
             FROM admin_login_tentativas
             WHERE email = :login
               AND ip_hash = :ip_hash
               AND sucesso = 0
               AND criado_em >= (CURRENT_TIMESTAMP - INTERVAL ' . ADMIN_LOGIN_WINDOW_MINUTES . ' MINUTE)'
        );
        $statement->execute([
            'login' => $login,
            'ip_hash' => admin_client_ip_hash(),
        ]);

        return (int) ($statement->fetch()['total'] ?? 0) >= ADMIN_LOGIN_MAX_ATTEMPTS;
    } catch (Throwable $error) {
        error_log('[ArteFlor][auth-rate-limit] ' . $error->getMessage());
        return false;
    }
}

function admin_log_login_attempt(string $login, bool $success): void
{
    $login = admin_normalize_login($login);
    if ($login === '') {
        return;
    }

    try {
        db()->prepare(
            'INSERT INTO admin_login_tentativas (email, ip_hash, sucesso, user_agent)
             VALUES (:email, :ip_hash, :sucesso, :user_agent)'
        )->execute([
            'email' => $login,
            'ip_hash' => admin_client_ip_hash(),
            'sucesso' => $success ? 1 : 0,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $error) {
        error_log('[ArteFlor][auth-attempt-log] ' . $error->getMessage());
    }
}

function admin_login(string $login, string $password): bool
{
    admin_session_start();

    $login = admin_normalize_login($login);
    if ($login === '' || $password === '') {
        usleep(300000);
        return false;
    }

    try {
        $where = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? 'email = :login'
            : 'LOWER(nome) = :login';
        $statement = db()->prepare(
            "SELECT id, nome, email, senha_hash, perfil
             FROM usuarios_admin
             WHERE {$where} AND ativo = 1
             LIMIT 1"
        );
        $statement->execute(['login' => $login]);
        $user = $statement->fetch();
    } catch (Throwable $error) {
        error_log('[ArteFlor][auth-login] ' . $error->getMessage());
        admin_log_login_attempt($login, false);
        usleep(300000);
        return false;
    }

    if (!$user || !password_verify($password, (string) $user['senha_hash'])) {
        admin_log_login_attempt($login, false);
        usleep(300000);
        return false;
    }

    session_regenerate_id(true);

    $_SESSION[ADMIN_SESSION_KEY] = [
        'id' => (int) $user['id'],
        'nome' => (string) $user['nome'],
        'email' => (string) $user['email'],
        'perfil' => (string) $user['perfil'],
    ];
    $_SESSION[ADMIN_ACTIVITY_KEY] = time();
    $_SESSION[ADMIN_CSRF_KEY] = bin2hex(random_bytes(32));

    try {
        db()->prepare('UPDATE usuarios_admin SET ultimo_acesso_em = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute(['id' => (int) $user['id']]);

        if (password_needs_rehash((string) $user['senha_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE usuarios_admin SET senha_hash = :senha_hash WHERE id = :id')
                ->execute([
                    'senha_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => (int) $user['id'],
                ]);
        }
    } catch (Throwable $error) {
        error_log('[ArteFlor][auth-login-update] ' . $error->getMessage());
    }

    admin_log_login_attempt($login, true);

    return true;
}

function admin_logout(): void
{
    admin_session_start();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function require_admin(): array
{
    if (!admin_is_authenticated()) {
        $next = urlencode((string) ($_SERVER['REQUEST_URI'] ?? site_url('admin/dashboard.php')));
        header('Location: ' . site_url('admin/login.php') . '?next=' . $next);
        exit;
    }

    admin_touch_session();

    return admin_current_user() ?? [];
}
