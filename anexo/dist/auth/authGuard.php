<?php

// /dist/auth/authGuard.php

declare(strict_types=1);


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


require_once __DIR__ . '/accessPolicy.php';


/* ============================================================
 * REDIRECIONAMENTO
 * ============================================================ */

/**
 * Limpa a sessão e envia para o login.
 */
function _redirect_to_login(): void
{
    /*
     * Limpa dados da sessão.
     */
    $_SESSION = [];


    /*
     * Remove cookie da sessão.
     */
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


    /*
     * Finaliza sessão.
     */
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }


    /*
     * Login público.
     *
     * Mantido de acordo com sua estrutura atual.
     */
    header('Location: .././index.php');

    exit;
}


/* ============================================================
 * AUTH GUARD
 * ============================================================ */

function auth_guard(): void
{
    /* ========================================================
     * 1. PRECISA ESTAR LOGADO
     * ======================================================== */

    if (
        empty($_SESSION['user_id']) ||
        empty($_SESSION['user_role'])
    ) {

        _redirect_to_login();
    }


    /* ========================================================
     * DADOS DA SESSÃO
     * ======================================================== */

    $role = strtolower(
        trim(
            (string)(
                $_SESSION['user_role'] ?? ''
            )
        )
    );


    $autorizado = strtolower(
        trim(
            (string)(
                $_SESSION['autorizado'] ?? ''
            )
        )
    );


    /* ========================================================
     * 2. PREFEITO
     * ======================================================== */

    if ($role === 'prefeito') {

        /*
         * Mantém comportamento existente.
         */
        return;
    }


    /* ========================================================
     * 3. SECRETÁRIO
     * ======================================================== */

    if ($role === 'secretario') {

        /*
         * Mantém comportamento existente.
         */
        return;
    }


    /* ========================================================
     * 4. ADMIN
     * ======================================================== */

    if ($role === 'admin') {

        /*
         * Admin só entra se autorizado = sim.
         */
        if ($autorizado !== 'sim') {
            _redirect_to_login();
        }

        return;
    }


    /* ========================================================
     * 5. USUÁRIO COMUM
     * ======================================================== */

    if ($role === 'comum') {

        /*
         * Usuário comum precisa estar autorizado.
         */
        if ($autorizado !== 'sim') {
            _redirect_to_login();
        }


        /*
         * Revalida a política em CADA página:
         *
         * - dia
         * - horário
         * - rede/IP
         */
        $policy = access_check_common();


        if (!$policy['allowed']) {

            _redirect_to_login();
        }


        /*
         * Tudo certo.
         */
        return;
    }


    /* ========================================================
     * 6. OUTROS PERFIS
     * ======================================================== */

    /*
     * suporte ou qualquer papel desconhecido
     * continua sem autorização.
     */
    _redirect_to_login();
}

?>