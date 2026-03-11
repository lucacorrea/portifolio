<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Carrega o helper global
|--------------------------------------------------------------------------
*/
$globalHelper = __DIR__ . '/../_helpers.php';
if (is_file($globalHelper)) {
    require_once $globalHelper;
}

/*
|--------------------------------------------------------------------------
| Fallbacks mínimos
|--------------------------------------------------------------------------
| Só cria se ainda não existirem.
*/

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('require_db_or_die')) {
    function require_db_or_die(): PDO
    {
        if (!function_exists('db')) {
            http_response_code(500);
            exit('Função db(): PDO não encontrada.');
        }

        $pdo = db();

        if (!$pdo instanceof PDO) {
            http_response_code(500);
            exit('Conexão inválida.');
        }

        return $pdo;
    }
}

/*
|--------------------------------------------------------------------------
| Helpers específicos do módulo de usuários
|--------------------------------------------------------------------------
*/

if (!function_exists('users_set_flash')) {
    function users_set_flash(string $key, string $msg): void
    {
        $_SESSION[$key] = $msg;
    }
}

if (!function_exists('users_take_flash')) {
    function users_take_flash(string $key): ?string
    {
        if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            return null;
        }

        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);

        return $msg;
    }
}

if (!function_exists('users_get_str')) {
    function users_get_str(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }
}

if (!function_exists('users_post_str')) {
    function users_post_str(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }
}

if (!function_exists('users_post_int')) {
    function users_post_int(string $key, int $default = 0): int
    {
        $value = $_POST[$key] ?? $default;
        return is_numeric($value) ? (int)$value : $default;
    }
}

if (!function_exists('users_get_int')) {
    function users_get_int(string $key, int $default = 1): int
    {
        $value = $_GET[$key] ?? $default;
        return is_numeric($value) ? max(1, (int)$value) : $default;
    }
}

if (!function_exists('users_csrf_validate')) {
    function users_csrf_validate(string $field = 'csrf', string $sessionKey = '_csrf'): bool
    {
        $posted = (string)($_POST[$field] ?? '');
        $sess   = (string)($_SESSION[$sessionKey] ?? '');

        return $posted !== '' && $sess !== '' && hash_equals($sess, $posted);
    }
}

if (!function_exists('users_hash_password')) {
    function users_hash_password(string $senha): array
    {
        $salt = bin2hex(random_bytes(32));
        $hash = hash('sha256', $salt . '|' . $senha);

        return [
            'salt' => $salt,
            'hash' => $hash,
        ];
    }
}

if (!function_exists('users_verify_password')) {
    function users_verify_password(string $senha, string $salt, string $hash): bool
    {
        $calc = hash('sha256', $salt . '|' . $senha);
        return hash_equals($hash, $calc);
    }
}

if (!function_exists('users_fmt_date')) {
    function users_fmt_date(?string $date): string
    {
        $date = trim((string)$date);

        if ($date === '') {
            return '-';
        }

        $ts = strtotime($date);
        if ($ts === false) {
            return '-';
        }

        return date('d/m/Y H:i', $ts);
    }
}

if (!function_exists('users_json_out')) {
    function users_json_out(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
}

?>