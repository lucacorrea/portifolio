<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =========================
   BASICS
========================= */

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}

if (!function_exists('get')) {
    function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}

if (!function_exists('post')) {
    function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }
}

/* =========================
   FLASH
========================= */

if (!function_exists('flash_set')) {
    function flash_set(string $type, string $msg): void
    {
        $_SESSION['_flash'] = [
            'type' => $type,
            'msg'  => $msg,
            'at'   => time(),
        ];
    }
}

if (!function_exists('flash_pop')) {
    function flash_pop(): ?array
    {
        if (!isset($_SESSION['_flash'])) return null;

        $f = $_SESSION['_flash'];
        unset($_SESSION['_flash']);

        if (!is_array($f) || !isset($f['type'], $f['msg'])) {
            return null;
        }

        return $f;
    }
}

/* =========================
   CSRF
========================= */

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(string $name = 'csrf'): string
    {
        $t = csrf_token();
        return '<input type="hidden" name="' . e($name) . '" value="' . e($t) . '">';
    }
}

if (!function_exists('csrf_validate')) {
    function csrf_validate(string $token, string $sessionKey = '_csrf'): bool
    {
        $sess = $_SESSION[$sessionKey] ?? '';
        if (!is_string($sess) || $sess === '') return false;

        return hash_equals($sess, $token);
    }
}

if (!function_exists('csrf_validate_or_die')) {
    function csrf_validate_or_die(string $field = 'csrf'): void
    {
        $token = (string)($_POST[$field] ?? '');
        if (!csrf_validate($token)) {
            http_response_code(419);
            die('CSRF inválido. Atualize a página e tente novamente.');
        }
    }
}

/* =========================
   NÚMEROS / MOEDA (BRL)
========================= */

if (!function_exists('brl_to_float')) {
    function brl_to_float(string $value): float
    {
        $s = trim($value);
        if ($s === '') return 0.0;

        $s = str_replace(['R$', ' ', "\u{00A0}"], '', $s);
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);

        $n = (float)$s;
        if (!is_finite($n)) return 0.0;

        return $n;
    }
}

if (!function_exists('float_to_brl')) {
    function float_to_brl(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}

/* =========================
   HELPERS DE RESPOSTA
========================= */

if (!function_exists('redirect')) {
    function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('to_int')) {
    function to_int(mixed $v, int $default = 0): int
    {
        if (is_int($v)) return $v;
        if (is_numeric($v)) return (int)$v;
        return $default;
    }
}

if (!function_exists('to_str')) {
    function to_str(mixed $v, string $default = ''): string
    {
        if (is_string($v)) return trim($v);
        if (is_numeric($v)) return (string)$v;
        return $default;
    }
}