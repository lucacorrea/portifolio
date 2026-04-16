<?php
declare(strict_types=1);

session_start();


require_once __DIR__ . '/../conexao.php';

/*
|--------------------------------------------------------------------------
| FUNÇÕES AUXILIARES
|--------------------------------------------------------------------------
*/
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

/*
|--------------------------------------------------------------------------
| ACEITA SOMENTE POST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('../../auth-register-basic.php', [
        'erro' => 'metodo_invalido'
    ]);
}

/*
|--------------------------------------------------------------------------
| RECEBENDO DADOS
|--------------------------------------------------------------------------
*/
$username = limpar((string)($_POST['username'] ?? ''));
$email    = limpar((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$terms    = isset($_POST['terms']);

/*
|--------------------------------------------------------------------------
| VALIDAÇÕES
|--------------------------------------------------------------------------
*/
if ($username === '' || $email === '' || $password === '') {
    redirecionar('../../auth-register-basic.php', [
        'erro' => 'preencha_todos_os_campos'
    ]);
}

if (mb_strlen($username) < 3 || mb_strlen($username) > 100) {
    redirecionar('../../auth-register-basic.php', [
        'erro' => 'username_invalido'
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirecionar('../../auth-register-basic.php', [
        'erro' => 'email_invalido'
    ]);
}

if (strlen($password) < 6) {
    redirecionar('../../auth-register-basic.php', [
        'erro' => 'senha_fraca'
    ]);
}

if (!$terms) {
    redirecionar('../../auth-register-basic.php', [
        'erro' => 'aceite_os_termos'
    ]);
}

try {
    /*
    |--------------------------------------------------------------------------
    | VERIFICA SE JÁ EXISTE USUÁRIO COM MESMO E-MAIL
    |--------------------------------------------------------------------------
    */
    $sqlEmail = "SELECT id FROM usuarios WHERE email = :email LIMIT 1";
    $stmtEmail = $pdo->prepare($sqlEmail);
    $stmtEmail->execute([
        ':email' => $email
    ]);

    if ($stmtEmail->fetch()) {
        redirecionar('../../auth-register-basic.php', [
            'erro' => 'email_ja_cadastrado'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VERIFICA SE JÁ EXISTE USUÁRIO COM MESMO USERNAME
    |--------------------------------------------------------------------------
    */
    $sqlUser = "SELECT id FROM usuarios WHERE username = :username LIMIT 1";
    $stmtUser = $pdo->prepare($sqlUser);
    $stmtUser->execute([
        ':username' => $username
    ]);

    if ($stmtUser->fetch()) {
        redirecionar('../../auth-register-basic.php', [
            'erro' => 'usuario_ja_cadastrado'
        ]);
    }


    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    if ($passwordHash === false) {
        throw new RuntimeException('Falha ao gerar hash da senha.');
    }

    /*
    |--------------------------------------------------------------------------
    | INSERE NO BANCO
    |--------------------------------------------------------------------------
    */
    $sqlInsert = "INSERT INTO usuarios (username, email, senha)
                  VALUES (:username, :email, :senha)";

    $stmtInsert = $pdo->prepare($sqlInsert);
    $stmtInsert->execute([
        ':username' => $username,
        ':email'    => $email,
        ':senha'    => $passwordHash
    ]);

    $usuarioId = (int)$pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | SALVA SESSÃO APÓS CADASTRO
    |--------------------------------------------------------------------------
    */
    $_SESSION['usuario_id'] = $usuarioId;
    $_SESSION['username']   = $username;
    $_SESSION['email']      = $email;

    redirecionar('../../index.php', [
        'sucesso' => 'cadastro_realizado'
    ]);

} catch (PDOException $e) {
    redirecionar('../../auth-register-basic.php', [
        'erro' => 'erro_banco'
    ]);
} catch (Throwable $e) {
    redirecionar('../../auth-register-basic.php', [
        'erro' => 'erro_interno'
    ]);
}
