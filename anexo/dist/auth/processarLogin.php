<?php

// /dist/auth/processarLogin.php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../assets/conexao.php';
require_once __DIR__ . '/accessPolicy.php';


/* ============================================================
 * CONFIGURAÇÃO
 * ============================================================ */

const LOGIN_PAGE_URL = '/semas/index.php';


/* ============================================================
 * HELPERS
 * ============================================================ */

function js_alert_error(string $msg): void
{
    $msg = addslashes($msg);

    echo "<script>
        alert('{$msg}');
        window.location.href = '" . LOGIN_PAGE_URL . "';
    </script>";

    exit;
}


function redirect_login_silent(): void
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

    header('Location: ' . LOGIN_PAGE_URL);
    exit;
}


function go_success(string $to): void
{
    header('Location: ' . $to);
    exit;
}


function only_digits(string $v): string
{
    return preg_replace('/\D+/', '', $v) ?? '';
}


/* ============================================================
 * SOMENTE POST
 * ============================================================ */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_login_silent();
}


/* ============================================================
 * CONEXÃO
 * ============================================================ */

if (!isset($pdo) || !($pdo instanceof PDO)) {
    js_alert_error('Erro de conexão com o banco.');
}


/* ============================================================
 * RECEBE LOGIN
 * ============================================================ */

$loginIn = '';

if (isset($_POST['login'])) {
    $loginIn = (string)$_POST['login'];
} elseif (isset($_POST['email'])) {
    $loginIn = (string)$_POST['email'];
}

$loginIn = trim($loginIn);

$password = isset($_POST['password'])
    ? (string)$_POST['password']
    : '';


if ($loginIn === '' || $password === '') {
    js_alert_error('Preencha todos os campos.');
}


/* ============================================================
 * NORMALIZA LOGIN
 * ============================================================ */

$emailNorm = mb_strtolower($loginIn, 'UTF-8');
$cpfDigits = only_digits($loginIn);


/* ============================================================
 * LOGIN
 * ============================================================ */

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT
            id,
            nome,
            email,
            cpf,
            senha_hash,
            senha_salt,
            senha_algo,
            role,
            autorizado
        FROM contas_acesso
        WHERE email = :email
           OR cpf = :cpf
        LIMIT 1
    ");

    $stmt->execute([
        ':email' => $emailNorm,
        ':cpf'   => $cpfDigits
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
     * Mensagem genérica:
     * evita revelar se CPF/e-mail existe no sistema.
     */
    if (!$user) {
        js_alert_error('Usuário ou senha inválidos.');
    }


    /* ========================================================
     * VERIFICA SENHA
     * ======================================================== */

    $saltHex = (string)$user['senha_salt'];

    $calcHashHex = hash(
        'sha256',
        $saltHex . $password,
        false
    );

    if (
        !hash_equals(
            (string)$user['senha_hash'],
            $calcHashHex
        )
    ) {
        js_alert_error('Usuário ou senha inválidos.');
    }


    /* ========================================================
     * PERFIL / AUTORIZAÇÃO
     * ======================================================== */

    $role = strtolower(
        trim((string)$user['role'])
    );

    $autorizado = strtolower(
        trim((string)$user['autorizado'])
    );

    $podeEntrar = false;


    /* ========================================================
     * PREFEITO
     * ======================================================== */

    if ($role === 'prefeito') {
        $podeEntrar = true;
    }


    /* ========================================================
     * SECRETÁRIO
     * ======================================================== */

    elseif ($role === 'secretario') {
        $podeEntrar = true;
    }


    /* ========================================================
     * ADMIN
     * ======================================================== */

    elseif ($role === 'admin') {
        if ($autorizado === 'sim') {
            $podeEntrar = true;
        }
    }


    /* ========================================================
     * COMUM
     * ======================================================== */

    elseif ($role === 'comum') {
        /*
         * O comum precisa primeiro estar autorizado.
         */
        if ($autorizado !== 'sim') {
            redirect_login_silent();
        }

        /*
         * Depois precisa estar usando a rede/IP permitido.
         *
         * Se estiver em outra rede:
         * - não cria sessão de login;
         * - volta diretamente para /semas/index.php.
         */
        $policy = access_check_common();

        if (!$policy['allowed']) {
            redirect_login_silent();
        }

        $podeEntrar = true;
    }


    /* ========================================================
     * SUPORTE / OUTROS
     * ======================================================== */

    if (!$podeEntrar) {
        js_alert_error('Usuário não autorizado.');
    }


    /* ========================================================
     * SEGURANÇA DA SESSÃO
     * ======================================================== */

    session_regenerate_id(true);


    /* ========================================================
     * CRIA SESSÃO
     * ======================================================== */

    $_SESSION['user_id'] =
        (int)$user['id'];

    $_SESSION['user_nome'] =
        (string)$user['nome'];

    $_SESSION['user_email'] =
        (string)$user['email'];

    $_SESSION['cpf'] =
        (string)$user['cpf'];

    $_SESSION['user_role'] =
        $role;

    $_SESSION['autorizado'] =
        $autorizado;

    $_SESSION['login_ip'] =
        access_client_ip();

    $_SESSION['login_at'] =
        date('Y-m-d H:i:s');


    /* ========================================================
     * LOGIN CONCLUÍDO
     * ======================================================== */

    go_success('/semas/dist/dashboard.php');

} catch (Throwable $e) {
    js_alert_error('Erro ao efetuar login. Tente novamente.');
}
