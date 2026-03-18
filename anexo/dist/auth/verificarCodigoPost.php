<?php
declare(strict_types=1);
session_start();

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../assets/conexao.php';
date_default_timezone_set('America/Manaus');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('Requisição inválida.'); history.back();</script>";
    exit;
}

$email  = trim($_POST['email'] ?? '');
$codigo = preg_replace('/\D+/', '', $_POST['codigo'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<script>alert('E-mail inválido.'); history.back();</script>";
    exit;
}
if (strlen($codigo) !== 6) {
    echo "<script>alert('Informe um código válido de 6 dígitos.'); history.back();</script>";
    exit;
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Pega o token mais recente desse e-mail que ainda não foi usado
    $stmt = $pdo->prepare("
        SELECT id, codigo, used, expires_at
        FROM senha_tokens
        WHERE email = :email AND used = 0
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $tok = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tok) {
        echo "<script>
            alert('Nenhum código ativo encontrado. Reenvie um novo.');
            window.location.href = '../verificarCodigo.php?email=" . rawurlencode($email) . "';
        </script>";
        exit;
    }

    // Verifica expiração
    $now = new DateTimeImmutable('now');
    $exp = new DateTimeImmutable($tok['expires_at']);
    if ($now > $exp) {
        echo "<script>
            alert('Código expirou. Clique em Reenviar para gerar um novo.');
            window.location.href = '../verificarCodigo.php?email=" . rawurlencode($email) . "';
        </script>";
        exit;
    }

    // Compara código
    if (!hash_equals((string)$tok['codigo'], $codigo)) {
        echo "<script>
            alert('Código inválido.');
            window.location.href = '../verificarCodigo.php?email=" . rawurlencode($email) . "';
        </script>";
        exit;
    }

    // Marca como usado
    $upd = $pdo->prepare("UPDATE senha_tokens SET used = 1 WHERE id = :id");
    $upd->execute([':id' => $tok['id']]);

    // (opcional) guarda sessão para liberar próxima etapa (definir nova senha)
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_ok']    = true;

    // Sucesso -> redireciona para a tela de definir nova senha
    echo "<script>
        alert('Código verificado com sucesso!');
        // Opcional: limpar countdown localStorage no próximo carregamento (feito na próxima página se desejar)
        window.location.href = '../definirNovaSenha.php?email=" . rawurlencode($email) . "';
    </script>";
    exit;

} catch (Throwable $e) {
    $msg = addslashes($e->getMessage());
    echo "<script>alert('Erro: {$msg}'); history.back();</script>";
    exit;
}

?>