<?php
// /lib/auth_guard.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Redireciona SEM alert (rápido e silencioso)
 */
function _redirect_to_login(): void {
    // Redireciona sempre para o login público do projeto
    header('Location: .././index.php');
    exit;
}

/**
 * Verifica sessão e autorização:
 * - exige login (user_id na sessão)
 * - prefeito/secretario entram mesmo com autorizado='nao'
 * - admin só entra se autorizado='sim'
 */
function auth_guard(): void {
    // 1) Precisa estar logado
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        _redirect_to_login();
    }

    $role       = (string)($_SESSION['user_role'] ?? '');
    $autorizado = (string)($_SESSION['autorizado'] ?? '');

    // 2) Regras por perfil
    if ($role === 'admin') {
        // Admin SÓ entra se autorizado = 'sim'
        if ($autorizado !== 'sim') {
            _redirect_to_login();
        }
        // admin autorizado -> ok
        return;
    }

    if ($role === 'prefeito' || $role === 'secretario') {
        // Prefeito e Secretário entram mesmo com autorizado = 'nao'
        return;
    }

    // Qualquer outro papel desconhecido -> nega acesso
    _redirect_to_login();
}

?>