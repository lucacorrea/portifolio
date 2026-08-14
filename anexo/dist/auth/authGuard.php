<?php

// /dist/auth/authGuard.php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/accessPolicy.php';


/* ============================================================
 * ROTAS DO SISTEMA
 * ============================================================ */

/**
 * Caminhos absolutos do projeto.
 *
 * Como seu sistema está em /anexo/, usar caminho absoluto evita
 * redirecionamento errado dependendo da pasta da página atual.
 */
const AUTH_LOGIN_URL     = '/anexo/index.php';
const AUTH_DASHBOARD_URL = '/anexo/dist/dashboard.php';


/**
 * Páginas que o usuário COMUM pode abrir.
 *
 * Regra solicitada:
 * - Dashboard
 * - Cadastrar Pessoa
 * - Pessoas Cadastradas
 * - Logout
 *
 * IMPORTANTE:
 * caso cadastrarSolicitante.php envie o formulário para outro
 * arquivo PHP que também execute auth_guard(), inclua o nome
 * desse processador nesta lista.
 */
const COMMON_ALLOWED_PAGES = [
    'dashboard.php',
    'cadastrarSolicitante.php',
    'pessoasCadastradas.php',
    'logout.php'
];


/**
 * Páginas de gerenciamento de usuários.
 *
 * Admin NÃO pode acessar.
 * Somente prefeito e secretario.
 */
const USER_MANAGEMENT_PAGES = [
    'usuariosPermitidos.php',
    'usuariosNaoPermitidos.php'
];


/* ============================================================
 * HELPERS
 * ============================================================ */

/**
 * Retorna o arquivo PHP atual.
 */
function auth_current_page(): string
{
    $scriptName = isset($_SERVER['SCRIPT_NAME'])
        ? (string)$_SERVER['SCRIPT_NAME']
        : '';

    return basename($scriptName);
}


/**
 * Destrói completamente a sessão atual.
 */
function auth_destroy_session(): void
{
    $_SESSION = [];

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

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}


/**
 * Redireciona para o login e encerra a sessão.
 *
 * Usado especialmente quando o usuário comum sai da rede
 * autorizada.
 */
function _redirect_to_login(): void
{
    auth_destroy_session();

    header('Location: ' . AUTH_LOGIN_URL);
    exit;
}


/**
 * Redireciona para o Dashboard sem destruir a sessão.
 *
 * Usado quando o usuário está autenticado, mas tenta abrir
 * uma página que seu perfil não possui permissão para acessar.
 */
function _redirect_to_dashboard(): void
{
    header('Location: ' . AUTH_DASHBOARD_URL);
    exit;
}


/* ============================================================
 * AUTH GUARD
 * ============================================================ */

/**
 * Regras:
 *
 * PREFEITO
 * - acesso normal;
 * - pode gerenciar usuários.
 *
 * SECRETARIO
 * - acesso normal;
 * - pode gerenciar usuários.
 *
 * ADMIN
 * - exige autorizado = sim;
 * - NÃO pode acessar gerenciamento de usuários.
 *
 * COMUM
 * - exige autorizado = sim;
 * - precisa estar SEMPRE na rede/IP autorizado;
 * - se trocar de rede, sessão é destruída e volta ao index.php;
 * - só pode abrir Dashboard, Cadastrar Pessoa e Pessoas Cadastradas.
 *
 * SUPORTE / outros
 * - acesso negado.
 */
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
        trim((string)($_SESSION['user_role'] ?? ''))
    );

    $autorizado = strtolower(
        trim((string)($_SESSION['autorizado'] ?? ''))
    );

    $currentPage = auth_current_page();


    /* ========================================================
     * PREFEITO
     * ======================================================== */

    if ($role === 'prefeito') {
        return;
    }


    /* ========================================================
     * SECRETÁRIO
     * ======================================================== */

    if ($role === 'secretario') {
        return;
    }


    /* ========================================================
     * ADMIN
     * ======================================================== */

    if ($role === 'admin') {
        if ($autorizado !== 'sim') {
            _redirect_to_login();
        }

        /*
         * Segurança no backend:
         * esconder o menu não é suficiente.
         */
        if (in_array($currentPage, USER_MANAGEMENT_PAGES, true)) {
            _redirect_to_dashboard();
        }

        return;
    }


    /* ========================================================
     * USUÁRIO COMUM
     * ======================================================== */

    if ($role === 'comum') {
        if ($autorizado !== 'sim') {
            _redirect_to_login();
        }

        /*
         * A rede é revalidada em TODA requisição protegida.
         *
         * Se o usuário logou na rede autorizada e depois mudar
         * para outra rede/4G, a próxima página protegida encerra
         * a sessão e manda para index.php.
         */
        $policy = access_check_common();

        if (!$policy['allowed']) {
            _redirect_to_login();
        }

        /*
         * Além da rede, limita quais páginas o comum pode abrir.
         */
        if (!in_array($currentPage, COMMON_ALLOWED_PAGES, true)) {
            _redirect_to_dashboard();
        }

        return;
    }


    /* ========================================================
     * SUPORTE / PERFIL DESCONHECIDO
     * ======================================================== */

    _redirect_to_login();
}

?>