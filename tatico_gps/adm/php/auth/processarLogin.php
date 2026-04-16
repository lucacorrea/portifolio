<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../conexao.php';

function alertarERedirecionar(string $mensagem, string $destino): never
{
    $mensagemJs = json_encode($mensagem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $destinoJs  = json_encode($destino, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    echo "<script>
        alert({$mensagemJs});
        window.location.href = {$destinoJs};
    </script>";
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
    alertarERedirecionar('Método de envio inválido.', '../../index.html');
}

/*
|--------------------------------------------------------------------------
| RECEBENDO DADOS
|--------------------------------------------------------------------------
*/
$login    = limpar((string)($_POST['email-username'] ?? ''));
$password = (string)($_POST['password'] ?? '');

/*
|--------------------------------------------------------------------------
| VALIDAÇÕES BÁSICAS
|--------------------------------------------------------------------------
*/
if ($login === '' || $password === '') {
    alertarERedirecionar('Preencha o e-mail/usuário e a senha.', '../../index.html');
}

try {
    /*
    |--------------------------------------------------------------------------
    | BUSCA USUÁRIO POR E-MAIL OU USERNAME
    |--------------------------------------------------------------------------
    */
    $sql = "SELECT id, username, email, senha
            FROM usuarios
            WHERE email = :login OR username = :login
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':login' => $login
    ]);

    $usuario = $stmt->fetch();

    /*
    |--------------------------------------------------------------------------
    | VALIDA USUÁRIO E SENHA
    |--------------------------------------------------------------------------
    */
    if (!$usuario) {
        alertarERedirecionar('Usuário ou senha inválidos.', '../../index.html');
    }

    if (!password_verify($password, (string)$usuario['senha'])) {
        alertarERedirecionar('Usuário ou senha inválidos.', '../../index.html');
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN OK
    |--------------------------------------------------------------------------
    */
    session_regenerate_id(true);

    $_SESSION['usuario_id'] = (int)$usuario['id'];
    $_SESSION['username']   = (string)$usuario['username'];
    $_SESSION['email']      = (string)$usuario['email'];
    $_SESSION['logado']     = true;

    echo "<script>
        alert('Login realizado com sucesso.');
        window.location.href = '../../dashboard.php';
    </script>";
    exit;

} catch (PDOException $e) {
    alertarERedirecionar('Erro ao processar o login.', '../../index.html');
} catch (Throwable $e) {
    alertarERedirecionar('Erro interno no sistema.', '../../index.html');
}

?>
