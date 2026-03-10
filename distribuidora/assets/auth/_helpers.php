<?php
declare(strict_types=1);

/**
 * /assets/dados/_helpers.php
 * Helpers gerais do sistema
 */

@date_default_timezone_set('America/Manaus');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =========================================================
   HTML
========================================================= */
if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/* =========================================================
   REQUEST
========================================================= */
if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }
}

if (!function_exists('is_get')) {
    function is_get(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
    }
}

if (!function_exists('post_str')) {
    function post_str(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }
}

if (!function_exists('post_raw')) {
    function post_raw(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}

if (!function_exists('post_int')) {
    function post_int(string $key, int $default = 0): int
    {
        $value = $_POST[$key] ?? $default;
        if (is_numeric($value)) return (int)$value;
        return $default;
    }
}

if (!function_exists('get_str')) {
    function get_str(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }
}

if (!function_exists('get_int')) {
    function get_int(string $key, int $default = 0): int
    {
        $value = $_GET[$key] ?? $default;
        if (is_numeric($value)) return (int)$value;
        return $default;
    }
}

/* =========================================================
   REDIRECT
========================================================= */
if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('normalize_redirect_url')) {
    function normalize_redirect_url(string $url, string $fallback = 'index.php'): string
    {
        $url = trim($url);

        if ($url === '') {
            return $fallback;
        }

        if (preg_match('~[\r\n]~', $url)) {
            return $fallback;
        }

        if (preg_match('~^https?://~i', $url)) {
            return $fallback;
        }

        return $url;
    }
}

/* =========================================================
   JSON
========================================================= */
if (!function_exists('json_out')) {
    function json_out(array $payload, int $status = 200): void
    {
        if (function_exists('ob_get_length') && ob_get_length()) {
            @ob_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
}

/* =========================================================
   CSRF
========================================================= */
if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_validate')) {
    function csrf_validate(?string $token): bool
    {
        $sessionToken = $_SESSION['_csrf_token'] ?? '';

        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        if (!is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}

if (!function_exists('csrf_validate_or_die')) {
    function csrf_validate_or_die(?string $token, string $message = 'Token CSRF inválido.'): void
    {
        if (!csrf_validate($token)) {
            http_response_code(419);
            exit($message);
        }
    }
}

/* =========================================================
   FLASH MESSAGE
========================================================= */
if (!function_exists('flash_set')) {
    function flash_set(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }
}

if (!function_exists('flash_get')) {
    function flash_get(string $key, string $default = ''): string
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        return is_string($value) ? $value : $default;
    }
}

if (!function_exists('flash_pop')) {
    function flash_pop(string $key, string $default = ''): string
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return is_string($value) ? $value : $default;
    }
}

if (!function_exists('flash_clear')) {
    function flash_clear(?string $key = null): void
    {
        if ($key === null) {
            unset($_SESSION['_flash']);
            return;
        }

        unset($_SESSION['_flash'][$key]);
    }
}

/* =========================================================
   OLD INPUT
========================================================= */
if (!function_exists('old_set')) {
    function old_set(array $data): void
    {
        $_SESSION['_old'] = $data;
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        $data = $_SESSION['_old'] ?? [];
        if (!is_array($data)) {
            return $default;
        }

        $value = $data[$key] ?? $default;
        return is_scalar($value) ? (string)$value : $default;
    }
}

if (!function_exists('old_all')) {
    function old_all(): array
    {
        $data = $_SESSION['_old'] ?? [];
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('old_clear')) {
    function old_clear(): void
    {
        unset($_SESSION['_old']);
    }
}

/* =========================================================
   VALIDAÇÕES SIMPLES
========================================================= */
if (!function_exists('filled')) {
    function filled(?string $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}

if (!function_exists('email_valido')) {
    function email_valido(string $email): bool
    {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

/* =========================================================
   SENHA - SHA256 + SALT
========================================================= */
if (!function_exists('gerar_salt_senha')) {
    function gerar_salt_senha(): string
    {
        return bin2hex(random_bytes(32)); // 64 chars hex
    }
}

if (!function_exists('hash_senha_sha256')) {
    function hash_senha_sha256(string $senha, string $salt): string
    {
        return hash('sha256', $salt . '|' . $senha);
    }
}

if (!function_exists('verificar_senha_sha256')) {
    function verificar_senha_sha256(string $senhaDigitada, string $salt, string $hashSalvo): bool
    {
        $hashAtual = hash_senha_sha256($senhaDigitada, $salt);
        return hash_equals($hashSalvo, $hashAtual);
    }
}

/* =========================================================
   HELPERS DE AUTENTICAÇÃO
========================================================= */
if (!function_exists('auth_user_id')) {
    function auth_user_id(): int
    {
        return (int)($_SESSION['usuario_id'] ?? 0);
    }
}

if (!function_exists('auth_user_nome')) {
    function auth_user_nome(): string
    {
        $nome = $_SESSION['usuario_nome'] ?? '';
        return is_string($nome) ? $nome : '';
    }
}

if (!function_exists('auth_user_email')) {
    function auth_user_email(): string
    {
        $email = $_SESSION['usuario_email'] ?? '';
        return is_string($email) ? $email : '';
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return auth_user_id() > 0;
    }
}

if (!function_exists('auth_login')) {
    function auth_login(array $usuario): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['usuario_id']    = (int)($usuario['id'] ?? 0);
        $_SESSION['usuario_nome']  = (string)($usuario['nome'] ?? '');
        $_SESSION['usuario_email'] = (string)($usuario['email'] ?? '');
        $_SESSION['usuario_status'] = (string)($usuario['status'] ?? 'ATIVO');
        $_SESSION['usuario_logado'] = true;
    }
}

if (!function_exists('auth_logout')) {
    function auth_logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?? '/',
                $params['domain'] ?? '',
                (bool)($params['secure'] ?? false),
                (bool)($params['httponly'] ?? true)
            );
        }

        session_destroy();
    }
}

if (!function_exists('auth_require')) {
    function auth_require(string $redirectUrl = '../../../index.php'): void
    {
        if (!auth_check()) {
            redirect($redirectUrl);
        }
    }
}

/* =========================================================
   BANCO
========================================================= */
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
?>