<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../dist/assets/conexao.php';

/* ================= Helpers ================= */
function js_alert_error(string $msg): void {
    $msg = addslashes($msg);
    echo "<script>alert('{$msg}'); history.back();</script>";
    exit;
}
function go_success(string $to): void {
    $to = addslashes($to);
    echo "<script>window.location.href = '{$to}';</script>";
    exit;
}
function only_digits(string $v): string {
    return preg_replace('/\D+/', '', $v) ?? '';
}

/* ========== Executa apenas POST ========== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    js_alert_error('Método inválido.');
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    js_alert_error('Erro de conexão com o banco.');
}

/* ========== Coleta POST ========== */
$loginIn  = isset($_POST['login']) ? (string)$_POST['login'] : (isset($_POST['email']) ? (string)$_POST['email'] : '');
$loginIn  = trim($loginIn);
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';

if ($loginIn === '' || $password === '') {
    js_alert_error('Preencha todos os campos.');
}

/* Normalizações */
$emailNorm = mb_strtolower($loginIn);
$cpfDigits = only_digits($loginIn);

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT * FROM contas_acesso_privado
        WHERE email = :email OR cpf = :cpf
        LIMIT 1
    ");
    $stmt->execute([
        ':email' => $emailNorm,
        ':cpf'   => $cpfDigits,
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        js_alert_error('Usuário não encontrado.');
    }

    /* Verificação de senha */
    $salt_hex      = (string)$user['senha_salt'];
    $calc_hash_hex = hash('sha256', $salt_hex . $password, false);

    if (!hash_equals((string)$user['senha_hash'], $calc_hash_hex)) {
        js_alert_error('Senha incorreta.');
    }

    /* Regras de acesso */
    $role       = (string)$user['role'];
    $autorizado = (string)$user['autorizado'];

    $podeEntrar = false;
    if ($role === 'prefeito' || $role === 'suporte' || $role === 'secretario') {
        $podeEntrar = true;
    } elseif ($role === 'admin' && $autorizado === 'sim') {
        $podeEntrar = true;
    }

    if (!$podeEntrar) {
        js_alert_error('Usuário não autorizado.');
    }

    /* Sessão */
    $_SESSION['user_id']     = (int)$user['id'];
    $_SESSION['user_nome']   = (string)$user['nome'];
    $_SESSION['user_email']  = (string)$user['email'];
    $_SESSION['cpf']         = (string)$user['cpf'];
    $_SESSION['user_role']   = $role;
    $_SESSION['autorizado']  = $autorizado;

    go_success('../dashboard.php');

} catch (Throwable $e) {
    js_alert_error('Erro ao efetuar login. Tente novamente.');
}

?>