<?php
declare(strict_types=1);

const PCCT_ROOT = __DIR__ . '/..';
$configFile = PCCT_ROOT . '/config/local.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>PCCT: configuração local ausente</h2><p>Copie <code>config/local.example.php</code> para <code>config/local.php</code> e configure o MySQL.</p>';
    exit;
}
$config = require $configFile;
if (!is_array($config)) {
    http_response_code(503);
    exit('Configuração inválida.');
}
$GLOBALS['pcct_config'] = $config;
date_default_timezone_set('America/Manaus');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('PCCTSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = $GLOBALS['pcct_config'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $c['db_host'], (int)$c['db_port'], $c['db_name']);
    $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function site_base(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    return $dir === '/' || $dir === '.' ? '' : rtrim($dir, '/');
}

function url(string $path = '/'): string
{
    return site_base() . '/' . ltrim($path, '/');
}

function route_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = site_base();
    if ($base && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = substr($path, strlen($base)) ?: '/';
    }
    return '/' . trim($path, '/');
}

function h(?string $text): string
{
    return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function input(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

function input_array(string $key): array
{
    return is_array($_POST[$key] ?? null) ? $_POST[$key] : [];
}

function field_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(token()) . '">';
}

function verify_csrf(): void
{
    if (!hash_equals(token(), (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(419);
        exit('Sessão expirada. Volte e tente novamente.');
    }
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function fail(string $message, string $redirect): never
{
    flash($message, 'error');
    $_SESSION['old'] = array_filter($_POST, fn($k) => !in_array($k,
        ['password', 'password_confirmation', '_csrf'], true), ARRAY_FILTER_USE_KEY);
    go($redirect);
}

function old(string $key, string $default = ''): string
{
    return (string)($_SESSION['old'][$key] ?? $default);
}

function go(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function me(): ?array
{
    static $cache = null;
    $id = (int)($_SESSION['user_id'] ?? 0);
    if (!$id) {
        return null;
    }
    if ($cache !== null && (int)$cache['id'] === $id) {
        return $cache;
    }
    $st = db()->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $cache = $st->fetch() ?: null;
    if (!$cache) {
        unset($_SESSION['user_id']);
    }
    return $cache;
}

function need_login(): array
{
    $user = me();
    if (!$user) {
        go('/entrar');
    }
    return $user;
}

function need_role(string $role): array
{
    $user = need_login();
    if ($user['role'] !== $role) {
        abort_page(403, 'Você não possui permissão para esta área.');
    }
    return $user;
}

function abort_page(int $status, string $message = 'Página não encontrada.'): never
{
    http_response_code($status);
    view('error', ['status' => $status, 'message' => $message]);
    exit;
}

function view(string $name, array $data = []): void
{
    $file = __DIR__ . '/views/' . $name . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Tela não encontrada: ' . $name);
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    $content = (string)ob_get_clean();
    require __DIR__ . '/views/layout.php';
}
