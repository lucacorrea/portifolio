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
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException("db() não retornou um PDO válido.");
    }

} catch (Throwable $e) {
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

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_erro'] = 'Informe um e-mail válido.';
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
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['flash_erro'] = 'E-mail ou senha inválidos.';
        header("Location: {$index}");
        exit;
    }

    if ((int)$user['ativo'] !== 1) {
        $_SESSION['flash_erro'] = 'Usuário inativo. Procure o administrador.';
        header("Location: {$index}");
        exit;
    }

    if (!password_verify($senha, (string)$user['senha_hash'])) {
        $_SESSION['flash_erro'] = 'E-mail ou senha inválidos.';
        header("Location: {$index}");
        exit;
    }

    // Carrega perfis do usuário
    $pstmt = $pdo->prepare("
        SELECT p.codigo
        FROM usuario_perfis up
        JOIN perfis p ON p.id = up.perfil_id
        WHERE up.usuario_id = :uid
    ");
    $pstmt->execute([':uid' => (int)$user['id']]);
    $perfis = $pstmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // Normaliza (trim + uppercase) e remove vazios
    $perfis = array_values(array_filter(array_map(static function($v) {
        $v = strtoupper(trim((string)$v));
        return $v !== '' ? $v : null;
    }, $perfis)));

    // Só permite ADMIN e GESTOR_GERAL
    $permitidos = ['ADMIN', 'GESTOR_GERAL'];
    $temPerfilValido = false;
    foreach ($perfis as $p) {
        if (in_array($p, $permitidos, true)) { $temPerfilValido = true; break; }
    }

    if (!$temPerfilValido) {
        $_SESSION['flash_erro'] = 'Seu usuário não possui perfil autorizado (Gestor Geral ou Administrador).';
        header("Location: {$index}");
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['usuario_logado'] = true;
    $_SESSION['usuario_id']     = (int)$user['id'];
    $_SESSION['usuario_nome']   = (string)$user['nome'];
    $_SESSION['usuario_email']  = (string)$user['email'];
    $_SESSION['perfis']         = $perfis;

    $upd = $pdo->prepare("UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = :id");
    $upd->execute([':id' => (int)$user['id']]);

    // Sem OPERADOR: ambos entram no painel ADM
    header('Location: ../../painel/adm/index.php');
    exit;

} catch (Throwable $e) {
    error_log("ERRO LOGIN (query): " . $e->getMessage());
    $_SESSION['flash_erro'] = "Erro interno no login: " . $e->getMessage();
    header("Location: {$index}");
    exit;
}
