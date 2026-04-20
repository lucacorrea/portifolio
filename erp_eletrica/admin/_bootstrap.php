<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}



// seu código abaixo

date_default_timezone_set('America/Manaus');

require_once __DIR__ . '/../src/App/Config/Database.php';
require_once __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    throw new RuntimeException('Conexão PDO inválida.');
}



function db(): PDO
{
    global $pdo;
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    $msg = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return is_string($msg) ? $msg : null;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_check(?string $token): bool
{
    return hash_equals($_SESSION['_csrf'] ?? '', (string)$token);
}

function is_admin_level(string $nivel): bool
{
    return in_array($nivel, ['admin', 'master'], true);
}

function expire_temp_users(): void
{
    db()->exec("
        UPDATE usuarios
           SET ativo = 0
         WHERE is_temp_admin = 1
           AND temp_admin_expires_at IS NOT NULL
           AND temp_admin_expires_at < NOW()
           AND ativo = 1
    ");
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare("SELECT * FROM usuarios WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function find_user_by_id(int $id): ?array
{
    $stmt = db()->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function update_last_login(int $userId): void
{
    $stmt = db()->prepare("UPDATE usuarios SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
}

function login_user(array $user, bool $realAdmin = false): void
{
    session_regenerate_id(true);

    $_SESSION['auth_user'] = [
        'id'            => (int)$user['id'],
        'filial_id'     => isset($user['filial_id']) ? (int)$user['filial_id'] : null,
        'nome'          => (string)$user['nome'],
        'email'         => (string)$user['email'],
        'nivel'         => (string)$user['nivel'],
        'avatar'        => (string)($user['avatar'] ?? 'default_avatar.png'),
        'is_temp_admin' => (int)($user['is_temp_admin'] ?? 0),
    ];

    $_SESSION['is_real_admin'] = $realAdmin ? 1 : 0;
}

function current_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function logout_user(string $redirectTo = 'login_admin.php'): never
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
    redirect($redirectTo);
}

function require_admin_area(): array
{
    expire_temp_users();

    $user = current_user();
    if (!$user || !is_admin_level((string)$user['nivel'])) {
        redirect('login_admin.php');
    }

    $fresh = find_user_by_id((int)$user['id']);
    if (!$fresh || (int)$fresh['ativo'] !== 1 || !is_admin_level((string)$fresh['nivel'])) {
        logout_user('login_admin.php');
    }

    if ((int)$fresh['is_temp_admin'] === 1) {
        $expiresAt = $fresh['temp_admin_expires_at'] ?? null;

        if (!$expiresAt || strtotime((string)$expiresAt) < time()) {
            $stmt = db()->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?");
            $stmt->execute([(int)$fresh['id']]);
            logout_user('login_admin.php');
        }
    }

    return $fresh;
}

function require_real_admin(): array
{
    $user = require_admin_area();

    if ((int)$user['is_temp_admin'] === 1 || empty($_SESSION['is_real_admin'])) {
        redirect('login_admin.php');
    }

    return $user;
}

function random_temp_code(int $digits = 8): string
{
    $min = 10 ** ($digits - 1);
    $max = (10 ** $digits) - 1;
    return (string)random_int($min, $max);
}

function host_rp_id(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return preg_replace('/:\d+$/', '', $host);
}

function webauthn_server(): \lbuchs\WebAuthn\WebAuthn
{
    return new \lbuchs\WebAuthn\WebAuthn('ERP Elétrica', host_rp_id());
}

function ensure_passkey_handle(int $userId): string
{
    $stmt = db()->prepare("SELECT passkey_user_handle FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $handle = (string)($stmt->fetchColumn() ?: '');

    if ($handle !== '') {
        return $handle;
    }

    $handle = bin2hex(random_bytes(16));
    $stmt = db()->prepare("UPDATE usuarios SET passkey_user_handle = ? WHERE id = ?");
    $stmt->execute([$handle, $userId]);

    return $handle;
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

?>