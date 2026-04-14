<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Sessão sem expiração por inatividade do sistema
|--------------------------------------------------------------------------
| Aqui não existe mais controle de "idle timeout".
| A sessão do PHP fica com tempo alto e o cookie também.
| 30 dias = 60 * 60 * 24 * 30
*/
$sessionLifetime = 60 * 60 * 24 * 30;

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
    ini_set('session.cookie_lifetime', (string)$sessionLifetime);

    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * Redireciona e encerra.
 */
function auth_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Garante que o usuário esteja logado e, se informado,
 * que pertença a um dos perfis permitidos.
 */
function ensure_logged_in(array $allowedProfiles = []): void
{
    $uid    = (int)($_SESSION['user_id'] ?? 0);
    $perfil = (string)($_SESSION['user_perfil'] ?? '');

    if ($uid <= 0 || $perfil === '') {
        auth_redirect('/index.php?erro=1&msg=' . urlencode('Faça login.'));
    }

    if ($allowedProfiles && !in_array($perfil, $allowedProfiles, true)) {
        if ($perfil === 'super_admin') {
            auth_redirect('/admin/dashboard.php');
        }

        auth_redirect('/public/dashboard.php');
    }
}

/**
 * Acesso exclusivo de super admin.
 */
function guard_super_admin(): void
{
    ensure_logged_in(['super_admin']);
}

/**
 * Guarda para usuários vinculados à empresa.
 *
 * Perfis aceitos:
 * - dono
 * - administrativo
 * - funcionario
 *
 * Tipos permitidos por padrão:
 * - dono
 * - administrativo
 * - caixa
 * - funcionario
 */
function guard_empresa_user(array $allowedTypes = ['dono', 'administrativo', 'caixa', 'funcionario']): void
{
    ensure_logged_in(['dono', 'administrativo', 'funcionario']);

    $perfil = (string)($_SESSION['user_perfil'] ?? '');
    $tipo   = (string)($_SESSION['user_tipo_func'] ?? '');

    // dono sempre pode
    if ($perfil === 'dono') {
        // ok
    } elseif ($perfil === 'administrativo') {
        // administrativo sempre pode
        if (!in_array('administrativo', $allowedTypes, true)) {
            auth_redirect('/public/dashboard.php?erro=1&msg=' . urlencode('Permissão insuficiente.'));
        }
    } elseif ($perfil === 'funcionario') {
        // bloqueia lavador
        if ($tipo === 'lavajato') {
            auth_redirect('/index.php?erro=1&msg=' . urlencode('Acesso não permitido para lavador.'));
        }

        if (!in_array($tipo, $allowedTypes, true)) {
            auth_redirect('/public/dashboard.php?erro=1&msg=' . urlencode('Permissão insuficiente.'));
        }
    } else {
        auth_redirect('/index.php?erro=1&msg=' . urlencode('Perfil inválido.'));
    }

    // empresa exigida
    $cnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
    if (strlen($cnpj) !== 14) {
        auth_redirect('/index.php?erro=1&msg=' . urlencode('Empresa não vinculada.'));
    }

    // constantes de conveniência
    if (!defined('CURRENT_CNPJ')) {
        define('CURRENT_CNPJ', $cnpj);
    }

    if (!defined('CURRENT_CPF')) {
        define('CURRENT_CPF', preg_replace('/\D+/', '', (string)($_SESSION['user_cpf'] ?? '')));
    }
}