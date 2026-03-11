<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* =========================
   INCLUDES
========================= */
$helpers = __DIR__ . '/./_helpers.php';
if (is_file($helpers)) {
    require_once $helpers;
}

require_once __DIR__ . '/../conexao.php';

/* =========================
   FALLBACKS
========================= */
if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_validate')) {
    function csrf_validate(?string $token): bool
    {
        $sessionToken = (string)($_SESSION['_csrf_token'] ?? '');
        return $sessionToken !== '' && is_string($token) && hash_equals($sessionToken, $token);
    }
}

/* =========================
   HELPERS LOCAIS
========================= */
function post_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function post_raw(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? $value : $default;
}

function normalize_return_url(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        return '../../cadastro.php';
    }

    if (preg_match('~[\r\n]~', $url)) {
        return '../../cadastro.php';
    }

    if (preg_match('~^https?://~i', $url)) {
        return '../../cadastro.php';
    }

    return $url;
}

function flash_back(string $redirectUrl, string $erro, array $old = []): void
{
    $_SESSION['cadastro_erro'] = $erro;
    $_SESSION['cadastro_old']  = $old;
    redirect($redirectUrl);
}

function flash_ok(string $redirectUrl, string $msg): void
{
    unset($_SESSION['cadastro_old']);
    $_SESSION['cadastro_ok'] = $msg;
    redirect($redirectUrl);
}

function ensure_usuarios_table(PDO $pdo): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS usuarios (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            senha_hash CHAR(64) NOT NULL,
            senha_salt CHAR(64) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ATIVO',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($sql);
}

function email_existe(PDO $pdo, string $email): bool
{
    $st = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $st->execute([$email]);
    return (bool)$st->fetchColumn();
}

function gerar_salt(): string
{
    return bin2hex(random_bytes(32)); // 64 chars hex
}

function gerar_hash_sha256_com_salt(string $senha, string $salt): string
{
    return hash('sha256', $salt . '|' . $senha);
}

/* =========================
   PROCESSAMENTO
========================= */
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('../../cadastro.php');
}

$redirectBack = normalize_return_url(post_str('redirect_back', '../../cadastro.php'));

$csrf = post_str('_csrf');
if (!csrf_validate($csrf)) {
    flash_back($redirectBack, 'Sessão inválida. Atualize a página e tente novamente.');
}

$nome            = post_str('nome');
$email           = mb_strtolower(post_str('email'));
$senha           = post_raw('senha');
$confirmarSenha  = post_raw('confirmar_senha');
$aceite          = isset($_POST['aceite']) ? '1' : '';

$old = [
    'nome'  => $nome,
    'email' => $email,
];

/* =========================
   VALIDAÇÕES
========================= */
if ($nome === '' || mb_strlen($nome) < 3) {
    flash_back($redirectBack, 'Informe um nome completo válido.', $old);
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_back($redirectBack, 'Informe um e-mail válido.', $old);
}

if ($senha === '') {
    flash_back($redirectBack, 'Informe uma senha.', $old);
}

if (mb_strlen($senha) < 8) {
    flash_back($redirectBack, 'A senha deve ter pelo menos 8 caracteres.', $old);
}

if ($senha !== $confirmarSenha) {
    flash_back($redirectBack, 'A confirmação da senha não confere.', $old);
}

if ($aceite !== '1') {
    flash_back($redirectBack, 'Você precisa aceitar os termos para continuar.', $old);
}

/* =========================
   BANCO
========================= */
try {
    $pdo = db();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    ensure_usuarios_table($pdo);

    if (email_existe($pdo, $email)) {
        flash_back($redirectBack, 'Este e-mail já está cadastrado.', $old);
    }

    /*
      SENHA:
      - não alteramos a senha digitada pelo usuário
      - geramos um SALT único por conta
      - o hash final muda mesmo que duas pessoas usem a mesma senha
    */
    $salt      = gerar_salt();
    $senhaHash = gerar_hash_sha256_com_salt($senha, $salt);

    $sql = "INSERT INTO usuarios (nome, email, senha_hash, senha_salt, status)
            VALUES (:nome, :email, :senha_hash, :senha_salt, 'ATIVO')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome'       => $nome,
        ':email'      => $email,
        ':senha_hash' => $senhaHash,
        ':senha_salt' => $salt,
    ]);

    flash_ok($redirectBack, 'Conta criada com sucesso. Agora você já pode entrar no sistema.');
} catch (Throwable $e) {
    $logFile = __DIR__ . '/../debug_errors.log';
    @file_put_contents(
        $logFile,
        "[" . date('Y-m-d H:i:s') . "] CADASTRO ERROR: " . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    flash_back($redirectBack, 'Não foi possível concluir o cadastro agora. Tente novamente.', $old);
}