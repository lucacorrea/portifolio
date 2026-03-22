<?php
declare(strict_types=1);
session_start();

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../assets/conexao.php';
date_default_timezone_set('America/Manaus');

/* ================= Helpers ================= */
function js_alert_error(string $msg): void {
    $msg = addslashes($msg);
    echo "<script>alert('{$msg}'); history.back();</script>";
    exit;
}
function js_alert_success(string $msg): void {
    $msg = addslashes($msg);
    echo "<script>alert('{$msg}'); window.location.href = '../../index.php';</script>";
    exit;
}

/** Gera salt aleatório (16 bytes -> hex) + hash SHA-256: sha256(salt_hex . plain . pepper) */
function hash_password_sha256(string $plain, string $pepper = ''): array {
    $salt = random_bytes(16);
    $salt_hex = bin2hex($salt);
    $hash_hex = hash('sha256', $salt_hex . $plain . $pepper, false);
    return [$salt_hex, $hash_hex];
}

/* ============== Execução ============== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    js_alert_error('Método inválido.');
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    js_alert_error('Erro de conexão com o banco.');
}

/* ============== Coleta do POST ============== */
$email  = trim((string)($_POST['email']  ?? ''));
$senha  = (string)($_POST['senha']  ?? '');
$senha2 = (string)($_POST['senha2'] ?? '');

/* ============== Validações ============== */
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    js_alert_error('E-mail inválido.');
}
if ($senha === '' || $senha2 === '') {
    js_alert_error('Informe a nova senha e a confirmação.');
}
if ($senha !== $senha2) {
    js_alert_error('As senhas não conferem.');
}
if (strlen($senha) < 8) {
    js_alert_error('A senha precisa ter pelo menos 8 caracteres.');
}

/* ============== Update ============== */
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Confere conta
    $stmt = $pdo->prepare("SELECT id FROM contas_acesso WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$conta) {
        js_alert_error('Conta não encontrada.');
    }
    $contaId = (int)$conta['id'];

    // Opcional: PEPPER via ambiente
    $pepper = getenv('PASSWORD_PEPPER') ?: '';

    // Gera salt + hash no formato combinado
    [$salt_hex, $hash_hex] = hash_password_sha256($senha, $pepper);

    // Atualiza senha
    $upd = $pdo->prepare("
        UPDATE contas_acesso
           SET senha_hash = :hash,
               senha_salt = :salt,
               senha_algo = 'sha256_salt',
               updated_at = NOW()
         WHERE id = :id
         LIMIT 1
    ");
    $upd->execute([
        ':hash' => $hash_hex,
        ':salt' => $salt_hex,
        ':id'   => $contaId,
    ]);

    // Invalida tokens ainda ativos desse e-mail
    $pdo->prepare("UPDATE senha_tokens SET used = 1 WHERE email = :email AND used = 0")
        ->execute([':email' => $email]);

    // Limpa flags da sessão do fluxo (se usadas)
    unset($_SESSION['reset_ok'], $_SESSION['reset_email']);

    js_alert_success('Senha alterada com sucesso! Faça login com a nova senha.');

} catch (Throwable $e) {
    js_alert_error('Erro ao salvar a nova senha. Tente novamente.');
}

?>