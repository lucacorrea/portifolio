<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');

require_once __DIR__ . '/../../auth/auth.php';
if (function_exists('auth_require')) {
    auth_require('../../../index.php');
}

require_once __DIR__ . '/../../conexao.php';

$pdo = db();

function users_redirect_to(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function users_post_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function users_post_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? $default;
    return is_numeric($value) ? (int)$value : $default;
}

function users_flash_set(string $key, string $msg): void
{
    $_SESSION[$key] = $msg;
}

function users_csrf_ok(): bool
{
    $posted = (string)($_POST['csrf'] ?? '');
    $sess   = (string)($_SESSION['_csrf'] ?? '');
    return $posted !== '' && $sess !== '' && hash_equals($sess, $posted);
}

function users_hash_password(string $senha): array
{
    $salt = bin2hex(random_bytes(32));
    $hash = hash('sha256', $salt . '|' . $senha);

    return [
        'salt' => $salt,
        'hash' => $hash,
    ];
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    users_redirect_to('../../../usuarios.php');
}

if (!users_csrf_ok()) {
    users_flash_set('flash_err', 'CSRF inválido. Atualize a página e tente novamente.');
    users_redirect_to('../../../usuarios.php');
}

$id     = users_post_int('id', 0);
$nome   = users_post_str('nome');
$email  = users_post_str('email');
$senha  = (string)($_POST['senha'] ?? '');
$status = strtoupper(users_post_str('status', 'ATIVO'));

$email = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);

if ($nome === '' || mb_strlen($nome) < 3) {
    users_flash_set('flash_err', 'Informe um nome válido.');
    users_redirect_to('../../../usuarios.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    users_flash_set('flash_err', 'Informe um e-mail válido.');
    users_redirect_to('../../../usuarios.php');
}

if (!in_array($status, ['ATIVO', 'INATIVO'], true)) {
    $status = 'ATIVO';
}

try {
    $sqlCheck = "SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1";
    $stCheck = $pdo->prepare($sqlCheck);
    $stCheck->execute([
        ':email' => $email,
        ':id'    => $id,
    ]);

    if ($stCheck->fetch()) {
        users_flash_set('flash_err', 'Já existe um usuário com este e-mail.');
        users_redirect_to('../../../usuarios.php');
    }

    if ($id > 0) {
        if ($senha !== '') {
            if (mb_strlen($senha) < 8) {
                users_flash_set('flash_err', 'A senha deve ter pelo menos 8 caracteres.');
                users_redirect_to('../../../usuarios.php');
            }

            $pass = users_hash_password($senha);

            $sql = "UPDATE usuarios
                    SET nome = :nome,
                        email = :email,
                        senha_hash = :senha_hash,
                        senha_salt = :senha_salt,
                        status = :status
                    WHERE id = :id";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':nome'       => $nome,
                ':email'      => $email,
                ':senha_hash' => $pass['hash'],
                ':senha_salt' => $pass['salt'],
                ':status'     => $status,
                ':id'         => $id,
            ]);
        } else {
            $sql = "UPDATE usuarios
                    SET nome = :nome,
                        email = :email,
                        status = :status
                    WHERE id = :id";
            $st = $pdo->prepare($sql);
            $st->execute([
                ':nome'   => $nome,
                ':email'  => $email,
                ':status' => $status,
                ':id'     => $id,
            ]);
        }

        users_flash_set('flash_ok', 'Usuário atualizado com sucesso.');
        users_redirect_to('../../../usuarios.php');
    }

    if ($senha === '') {
        users_flash_set('flash_err', 'Informe uma senha para o novo usuário.');
        users_redirect_to('../../../usuarios.php');
    }

    if (mb_strlen($senha) < 8) {
        users_flash_set('flash_err', 'A senha deve ter pelo menos 8 caracteres.');
        users_redirect_to('../../../usuarios.php');
    }

    $pass = users_hash_password($senha);

    $sql = "INSERT INTO usuarios (nome, email, senha_hash, senha_salt, status)
            VALUES (:nome, :email, :senha_hash, :senha_salt, :status)";
    $st = $pdo->prepare($sql);
    $st->execute([
        ':nome'       => $nome,
        ':email'      => $email,
        ':senha_hash' => $pass['hash'],
        ':senha_salt' => $pass['salt'],
        ':status'     => $status,
    ]);

    users_flash_set('flash_ok', 'Usuário cadastrado com sucesso.');
    users_redirect_to('../../../usuarios.php');
} catch (Throwable $e) {
    users_flash_set('flash_err', 'Erro ao salvar usuário: ' . $e->getMessage());
    users_redirect_to('../../../usuarios.php');
}

?>