<?php
declare(strict_types=1);

/**
 * /assets/auth/auth.php
 * Controle central de autenticação e sessão persistente
 */

@date_default_timezone_set('America/Manaus');

/* =========================================================
   CONFIGURAÇÃO DE SESSÃO
   - sem logout por inatividade
   - sessão persistente por longo tempo
========================================================= */
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    );

    $lifetime = 60 * 60 * 24 * 30; // 30 dias

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.cookie_lifetime', (string)$lifetime);

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return !empty($_SESSION['usuario_logado']) && !empty($_SESSION['usuario_id']);
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): array
    {
        return [
            'id'     => (int)($_SESSION['usuario_id'] ?? 0),
            'nome'   => (string)($_SESSION['usuario_nome'] ?? ''),
            'email'  => (string)($_SESSION['usuario_email'] ?? ''),
            'status' => (string)($_SESSION['usuario_status'] ?? ''),
        ];
    }
}

if (!function_exists('auth_require')) {
    /**
     * Verifica se o usuário está autenticado.
     * Se não estiver, redireciona para o login.
     */
    function auth_require(string $redirectTo = '../../index.php'): void
    {
        if (!auth_check()) {
            redirect($redirectTo);
        }

        $status = (string)($_SESSION['usuario_status'] ?? 'ATIVO');
        if ($status !== 'ATIVO') {
            $_SESSION = [];
            session_destroy();
            redirect($redirectTo);
        }

        auth_refresh_session();
    }
}

if (!function_exists('auth_guest_only')) {
    /**
     * Se já estiver logado, manda para o dashboard.
     * Útil em tela de login/cadastro.
     */
    function auth_guest_only(string $redirectTo = '../../dashboard.php'): void
    {
        if (auth_check()) {
            auth_refresh_session();
            redirect($redirectTo);
        }
    }
}

if (!function_exists('auth_refresh_session')) {
    /**
     * Renova a sessão e o cookie para não expirar por inatividade.
     */
    function auth_refresh_session(): void
    {
        static $done = false;

        if ($done) {
            return;
        }
        $done = true;

        $secure = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        );

        $lifetime = 60 * 60 * 24 * 30; // 30 dias
        $now      = time();

        if (!isset($_SESSION['_auth_created_at'])) {
            $_SESSION['_auth_created_at'] = $now;
        }

        $_SESSION['_auth_last_seen'] = $now;

        if (!isset($_SESSION['_auth_regenerated_at'])) {
            $_SESSION['_auth_regenerated_at'] = $now;
        }

        // Regenera o ID só de tempos em tempos, sem derrubar login
        if (($now - (int)$_SESSION['_auth_regenerated_at']) > 300) {
            session_regenerate_id(true);
            $_SESSION['_auth_regenerated_at'] = $now;
        }

        // Renova o cookie da sessão
        setcookie(
            session_name(),
            session_id(),
            [
                'expires'  => $now + $lifetime,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }
}

if (!function_exists('auth_logout')) {
    /**
     * Logout manual.
     * Só sai se chamar esta função.
     */
    function auth_logout(string $redirectTo = '../../index.php'): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    [
                        'expires'  => time() - 42000,
                        'path'     => $params['path'] ?? '/',
                        'domain'   => $params['domain'] ?? '',
                        'secure'   => (bool)($params['secure'] ?? false),
                        'httponly' => (bool)($params['httponly'] ?? true),
                        'samesite' => 'Lax',
                    ]
                );
            }

            session_destroy();
        }

        redirect($redirectTo);
    }
}

?>