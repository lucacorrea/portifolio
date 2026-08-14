<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../assets/conexao.php';
require_once __DIR__ . '/accessPolicy.php';


/* ============================================================
 * HELPERS
 * ============================================================ */

function js_alert_error(string $msg): void
{
    $msg = addslashes($msg);

    echo "<script>
        alert('{$msg}');
        history.back();
    </script>";

    exit;
}


function go_success(string $to): void
{
    $to = addslashes($to);

    echo "<script>
        window.location.href = '{$to}';
    </script>";

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
    js_alert_error('Método inválido.');
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


/* ============================================================
 * VALIDA CAMPOS
 * ============================================================ */

if ($loginIn === '' || $password === '') {
    js_alert_error('Preencha todos os campos.');
}


/* ============================================================
 * NORMALIZA LOGIN
 * ============================================================ */

$emailNorm = mb_strtolower(
    $loginIn,
    'UTF-8'
);

$cpfDigits = only_digits($loginIn);


/* ============================================================
 * LOGIN
 * ============================================================ */

try {

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );


    /* ========================================================
     * BUSCA USUÁRIO
     * ======================================================== */

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
     * Mensagem genérica é mais segura.
     *
     * Evita informar se determinado CPF/e-mail
     * existe no sistema.
     */
    if (!$user) {
        js_alert_error('Usuário ou senha inválidos.');
    }


    /* ========================================================
     * SENHA
     * ======================================================== */

    $salt_hex = (string)$user['senha_salt'];

    $calc_hash_hex = hash(
        'sha256',
        $salt_hex . $password,
        false
    );


    if (
        !hash_equals(
            (string)$user['senha_hash'],
            $calc_hash_hex
        )
    ) {

        js_alert_error(
            'Usuário ou senha inválidos.'
        );
    }


    /* ========================================================
     * PERFIL
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

        /*
         * Mantém sua regra atual.
         */
        $podeEntrar = true;
    }


    /* ========================================================
     * SECRETÁRIO
     * ======================================================== */

    elseif ($role === 'secretario') {

        /*
         * Mantém sua regra atual.
         */
        $podeEntrar = true;
    }


    /* ========================================================
     * ADMIN
     * ======================================================== */

    elseif ($role === 'admin') {

        /*
         * Admin precisa estar autorizado.
         */
        if ($autorizado === 'sim') {
            $podeEntrar = true;
        }
    }


    /* ========================================================
     * COMUM
     * ======================================================== */

    elseif ($role === 'comum') {

        /*
         * Primeiro:
         * precisa estar autorizado.
         */
        if ($autorizado !== 'sim') {

            js_alert_error(
                'Usuário não autorizado.'
            );
        }


        /*
         * Depois aplica:
         *
         * - dia
         * - horário
         * - rede
         */
        $policy = access_check_common();


        if (!$policy['allowed']) {

            js_alert_error(
                (string)$policy['reason']
            );
        }


        $podeEntrar = true;
    }


    /* ========================================================
     * NÃO AUTORIZADO
     * ======================================================== */

    if (!$podeEntrar) {

        js_alert_error(
            'Usuário não autorizado.'
        );
    }


    /* ========================================================
     * PROTEÇÃO CONTRA SESSION FIXATION
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


    /*
     * Informações extras úteis para segurança/auditoria.
     */
    $_SESSION['login_ip'] =
        access_client_ip();

    $_SESSION['login_at'] =
        date('Y-m-d H:i:s');


    /* ========================================================
     * LOGIN CONCLUÍDO
     * ======================================================== */

    go_success('../dashboard.php');


} catch (Throwable $e) {

    /*
     * Não mostramos erro do banco para o usuário.
     */
    js_alert_error(
        'Erro ao efetuar login. Tente novamente.'
    );
}

?>