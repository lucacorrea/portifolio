<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    if (preg_match('/[\r\n]/', $path) || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) || str_starts_with($path, '//')) {
        $path = '/';
    }

    header('Location: ' . public_url($path));
    exit;
}

function public_base_path(): string
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $configuredBase = trim((string) env('APP_BASE_PATH', ''));

    if ($configuredBase !== '') {
        $basePath = '/' . trim($configuredBase, '/');
        return $basePath === '/' ? '' : $basePath;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $publicPosition = strpos($scriptName, '/public/');

    if ($publicPosition !== false) {
        $basePath = substr($scriptName, 0, $publicPosition + 7);
        return rtrim($basePath, '/');
    }

    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    if (str_ends_with($scriptDir, '/public')) {
        $basePath = $scriptDir;
        return rtrim($basePath, '/');
    }

    $basePath = '';
    return $basePath;
}

function public_url(string $path = ''): string
{
    $basePath = public_base_path();
    $path = '/' . ltrim($path, '/');

    if ($path === '/') {
        return $basePath !== '' ? $basePath . '/' : '/';
    }

    return $basePath . $path;
}

function asset_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $publicFile = PUBLIC_PATH . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $version = is_file($publicFile) ? (string) filemtime($publicFile) : (string) time();

    return public_url($path) . '?v=' . rawurlencode($version);
}

function url(string $path = ''): string
{
    $base = rtrim((string) env('APP_URL', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return $msg;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf_token'] ?? '';

    if (!$token || !hash_equals($_SESSION['_csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit('Sessão expirada ou token inválido. Volte e tente novamente.');
    }
}

function current_user(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function current_empresa_id(): ?int
{
    $id = $_SESSION['usuario']['empresa_id'] ?? null;
    return $id !== null ? (int) $id : null;
}

function moeda_br(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}
