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
    error_log("ERRO LOGIN (bootstrap/db): " . $e->getMessage());
    $_SESSION['flash_erro'] = "Erro interno. Tente novamente.";
    header("Location: {$index}");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$index}");
    exit;
}

/**
 * Agora o campo pode ser "email OU nome"
 * (você pode manter o name="email" no form, pra não mudar layout)
 */
$login = trim((string)($_POST['email'] ?? '')); // pode ser nome ou email
$senha = (string)($_POST['senha'] ?? '');

if ($login === '' || $senha === '') {
    $_SESSION['flash_erro'] = 'Informe seu e-mail ou nome e sua senha.';
    header("Location: {$index}");
    exit;
}

try {
    // Detecta se é email
    $isEmail = (strpos($login, '@') !== false) && filter_var($login, FILTER_VALIDATE_EMAIL);

    if ($isEmail) {
        // Login por EMAIL
        $stmt = $pdo->prepare("
            SELECT id, nome, email, senha_hash, ativo
            FROM usuarios
            WHERE email = :login
            LIMIT 1
        ");
        $stmt->execute([':login' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Login por NOME (pode haver nomes repetidos -> pega o mais recente ativo)
        // Se quiser exigir nome exato, está ok assim. Se quiser "parecido", dá pra mudar.
        $stmt = $pdo->prepare("
            SELECT id, nome, email, senha_hash, ativo
            FROM usuarios
            WHERE nome = :login
            ORDER BY ativo DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([':login' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        $_SESSION['flash_erro'] = 'Usuário não encontrado.';
        header("Location: {$index}");
        exit;
    }

    if ((int)$user['ativo'] !== 1) {
        $_SESSION['flash_erro'] = 'Usuário inativo. Procure o administrador.';
        header("Location: {$index}");
        exit;
    }

    if (!password_verify($senha, (string)$user['senha_hash'])) {
        $_SESSION['flash_erro'] = 'E-mail/nome ou senha inválidos.';
        header("Location: {$index}");
        exit;
    }

    // Perfis do usuário
    $pstmt = $pdo->prepare("
        SELECT p.codigo
        FROM usuario_perfis up
        JOIN perfis p ON p.id = up.perfil_id
        WHERE up.usuario_id = :uid
    ");
    $pstmt->execute([':uid' => (int)$user['id']]);
    $perfis = $pstmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // Se não tiver perfil, bloqueia (opcional, mas recomendado)
    if (empty($perfis)) {
        $_SESSION['flash_erro'] = 'Usuário sem perfil. Contate o administrador.';
        header("Location: {$index}");
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['usuario_logado'] = true;
    $_SESSION['usuario_id']     = (int)$user['id'];
    $_SESSION['usuario_nome']   = (string)$user['nome'];
    $_SESSION['usuario_email']  = (string)$user['email'];
    $_SESSION['perfis']         = $perfis;

    // Atualiza último login
    $upd = $pdo->prepare("UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = :id");
    $upd->execute([':id' => (int)$user['id']]);

    // Redireciona (sem operador)
    // - Gestor Geral (ou outro nome que você escolher) e Admin -> painel ADM
    if (in_array('GESTOR_GERAL', $perfis, true) || in_array('ADMIN', $perfis, true)) {
        header('Location: ../../painel/adm/index.php');
        exit;
    }

    // fallback: se tiver algum perfil inesperado
    $_SESSION['flash_erro'] = 'Perfil não autorizado para acessar o painel.';
    header("Location: {$index}");
    exit;

} catch (Throwable $e) {
    error_log("ERRO LOGIN (query): " . $e->getMessage());
    $_SESSION['flash_erro'] = "Erro interno no login. Tente novamente.";
    header("Location: {$index}");
    exit;
}
