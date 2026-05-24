<?php
// Configuração base do sistema Arte&Flor em PHP puro.

const SITE_NAME = 'Arte&Flor';
const SITE_DESCRIPTION = 'Floricultura premium com catálogo, carrinho, checkout, pedidos, PDV e painel administrativo.';
const WHATSAPP_NUMBER = '5597000000000';

function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
            continue;
        }

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $quote = $value[0] ?? '';
        if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function env_value(string $key, mixed $default = null): mixed
{
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env_value($key);

    if ($value === null) {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
}

function app_debug(): bool
{
    return env_bool('APP_DEBUG', false) && env_value('APP_ENV', 'production') !== 'production';
}

function app_is_cli(): bool
{
    return PHP_SAPI === 'cli';
}

load_env_file(__DIR__ . '/../.env');

$localConfig = __DIR__ . '/../config.local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

const APP_NAME = SITE_NAME;

function base_url(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/arteFlor/index.php');
    $dir = rtrim(dirname($scriptName), '/');

    foreach (['/admin/actions', '/admin', '/actions'] as $suffix) {
        if (str_ends_with($dir, $suffix)) {
            $dir = substr($dir, 0, -strlen($suffix));
            break;
        }
    }

    return ($dir === '' || $dir === '.') ? '/' : $dir . '/';
}

function asset(string $path): string
{
    return base_url() . 'assets/' . ltrim($path, '/');
}

function site_url(string $path = ''): string
{
    return base_url() . ltrim($path, '/');
}
