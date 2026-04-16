<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../conexao.php';

function redirecionar(string $destino, array $params = []): never
{
    $url = $destino;

    if (!empty($params)) {
        $url .= (str_contains($destino, '?') ? '&' : '?') . http_build_query($params);
    }

    header('Location: ' . $url);
    exit;
}

function limpar(string $valor): string
{
    return trim($valor);
}

$paginaCadastro = '../../criarConta.php';
$paginaInicial  = '../../index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar($paginaCadastro, [
        'erro' => 'metodo_invalido'
    ]);
}

$username = limpar((string)($_POST['username'] ?? ''));
$email    = limpar((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$terms    = isset($_POST['terms']);

if ($username === '' || $email === '' || $password === '') {
    redirecionar($paginaCadastro, [
        'erro' => 'preencha_todos_os_campos'
    ]);
}

if (mb_strlen($username) < 3 || mb_strlen($username) > 100) {
    redirecionar($paginaCadastro, [
        'erro' => 'username_invalido'
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirecionar($paginaCadastro, [
        'erro' => 'email_invalido'
    ]);
}

if (strlen($password) < 6) {
    redirecionar($paginaCadastro, [
        'erro' => 'senha_fraca'
    ]);
}

if (!$terms) {
    redirecionar($paginaCadastro, [
        'erro' => 'aceite_os_termos'
    ]);
}

try {
    $sqlEmail = "SELECT id FROM usuarios WHERE email = :email LIMIT 1";
    $stmtEmail = $pdo->prepare($sqlEmail);
    $stmtEmail->execute([
        ':email' => $email
    ]);

    if ($stmtEmail->fetch()) {
        redirecionar($paginaCadastro, [
            'erro' => 'email_ja_cadastrado'
        ]);
    }

    $sqlUser = "SELECT id FROM usuarios WHERE username = :username LIMIT 1";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([
        ':username' => $username
    ]);

    if ($stmtUser->fetch()) {
        redirecionar($paginaCadastro, [
            'erro' => 'usuario_ja_cadastrado'
        ]);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($passwordHash === false) {
        throw new RuntimeException('Falha ao gerar hash da senha.');
    }

    $sqlInsert = "INSERT INTO usuarios (username, email, senha)
                  VALUES (:username, :email, :senha)";

    $stmtInsert = $pdo->prepare($sqlInsert);
    $stmtInsert->execute([
        ':username' => $username,
        ':email'    => $email,
        ':senha'    => $passwordHash
    ]);

    $usuarioId = (int)$pdo->lastInsertId();

    session_regenerate_id(true);

    $_SESSION['usuario_id'] = $usuarioId;
    $_SESSION['username']   = $username;
    $_SESSION['email']      = $email;

    redirecionar($paginaInicial, [
        'sucesso' => 'cadastro_realizado'
    ]);

} catch (PDOException $e) {
    redirecionar($paginaCadastro, [
        'erro' => 'erro_banco'
    ]);
} catch (Throwable $e) {
    redirecionar($paginaCadastro, [
        'erro' => 'erro_interno'
    ]);
}

?>
