<?php
// REMOVA estas 3 linhas depois que funcionar:
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

$index = '../../index.php';

try {
    $con = __DIR__ . '/../../assets/php/conexao.php';

    if (!file_exists($con)) {
        throw new RuntimeException("Arquivo não encontrado: {$con}");
    }

    require $con;

    if (!function_exists('db')) {
        throw new RuntimeException("Função db() não existe no conexao.php");
    }

    $pdo = db();

} catch (Throwable $e) {
    // registra no log do PHP/servidor
    error_log("ERRO LOGIN (bootstrap/db): " . $e->getMessage());

    $_SESSION['flash_erro'] = "Erro interno: " . $e->getMessage();
    header("Location: {$index}");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$index}");
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
    $_SESSION['flash_erro'] = 'Informe e-mail e senha.';
    header("Location: {$index}");
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, nome, email, senha_hash, ativo
        FROM usuarios
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['ativo'] !== 1 || !password_verify($senha, $user['senha_hash'])) {
        $_SESSION['flash_erro'] = 'E-mail ou senha inválidos (ou usuário inativo).';
        header("Location: {$index}");
        exit;
    }

    $pstmt = $pdo->prepare("
        SELECT p.codigo
        FROM usuario_perfis up
        JOIN perfis p ON p.id = up.perfil_id
        WHERE up.usuario_id = :uid
    ");
    $pstmt->execute([':uid' => (int)$user['id']]);
    $perfis = $pstmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    session_regenerate_id(true);
    $_SESSION['usuario_logado'] = true;
    $_SESSION['usuario_id']     = (int)$user['id'];
    $_SESSION['usuario_nome']   = (string)$user['nome'];
    $_SESSION['usuario_email']  = (string)$user['email'];
    $_SESSION['perfis']         = $perfis;

    $upd = $pdo->prepare("UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = :id");
    $upd->execute([':id' => (int)$user['id']]);

    if (in_array('ADMIN', $perfis, true)) {
        header('Location: /../../painel/adm/index.php');
    } else {
        header('Location: /../../painel/operador/index.php');
    }
    exit;

} catch (Throwable $e) {
    error_log("ERRO LOGIN (query): " . $e->getMessage());
    $_SESSION['flash_erro'] = "Erro interno no login: " . $e->getMessage();
    header("Location: {$index}");
    exit;
}
