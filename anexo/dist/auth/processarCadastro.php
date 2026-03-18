<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../assets/conexao.php';

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
function only_digits(string $v): string {
    return preg_replace('/\D+/', '', $v) ?? '';
}

/** Gera salt aleatório + hash SHA-256. */
function hash_password_sha256(string $plain, string $pepper = ''): array {
    $salt = random_bytes(16);
    $salt_hex = bin2hex($salt);
    $hash_hex = hash('sha256', $salt_hex . $plain . $pepper, false);
    return [$salt_hex, $hash_hex];
}

/* ============== Regras de execução ============== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    js_alert_error('Método inválido.');
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    js_alert_error('Erro de conexão com o banco.');
}

/* ============== Coleta do POST ============== */
$name             = isset($_POST['name']) ? trim((string)$_POST['name']) : '';
$email            = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
$cpf              = isset($_POST['cpf']) ? only_digits((string)$_POST['cpf']) : ''; // só números
$password         = isset($_POST['password']) ? (string)$_POST['password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

/* ============== Validações ============== */
if ($password !== $confirm_password) {
    js_alert_error('As senhas não coincidem.');
}

/* ============== Insert ============== */
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Checa duplicados (email ou cpf iguais)
    $stmt = $pdo->prepare('SELECT 1 FROM contas_acesso WHERE email = :email OR cpf = :cpf LIMIT 1');
    $stmt->execute([':email' => $email, ':cpf' => $cpf]);
    if ($stmt->fetchColumn()) {
        js_alert_error('E-mail ou CPF já cadastrado.');
    }

    [$salt_hex, $hash_hex] = hash_password_sha256($password);

    $role = 'admin';
    $autorizado = 'nao';

    $sql = 'INSERT INTO contas_acesso
            (nome, email, cpf, senha_hash, senha_salt, senha_algo, role, autorizado, created_at, updated_at)
            VALUES
            (:nome, :email, :cpf, :senha_hash, :senha_salt, :senha_algo, :role, :autorizado, NOW(), NOW())';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome'        => $name,
        ':email'       => $email,
        ':cpf'         => $cpf,     // só números
        ':senha_hash'  => $hash_hex,
        ':senha_salt'  => $salt_hex,
        ':senha_algo'  => 'sha256_salt',
        ':role'        => $role,
        ':autorizado'  => $autorizado,
    ]);

    js_alert_success('Cadastro realizado com sucesso!');

} catch (Throwable $e) {
    js_alert_error('Erro ao cadastrar. Tente novamente.');
}

?>