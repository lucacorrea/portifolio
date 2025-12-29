<?php
declare(strict_types=1);
session_start();

$index = '../../redefinirSenha.php';

try {
    $con = __DIR__ . '/../../assets/php/conexao.php';
    if (!file_exists($con)) throw new RuntimeException("conexao.php não encontrado.");
    require $con;
    if (!function_exists('db')) throw new RuntimeException("db() não existe.");
    $pdo = db();
} catch (Throwable $e) {
    error_log("ERRO RESET (db): " . $e->getMessage());
    $_SESSION['flash_erro'] = "Erro interno. Tente novamente.";
    header("Location: {$index}");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$index}");
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_erro'] = "Informe um e-mail válido.";
    header("Location: {$index}");
    exit;
}

// Mensagem neutra (segurança)
$msgNeutra = "Se o e-mail estiver cadastrado e ativo, enviaremos um link para redefinir a senha.";

try {
    $st = $pdo->prepare("
        SELECT id, nome, email, ativo
        FROM usuarios
        WHERE email = :email
        LIMIT 1
    ");
    $st->execute([':email' => $email]);
    $user = $st->fetch(PDO::FETCH_ASSOC);

    if (!$user || (int)$user['ativo'] !== 1) {
        $_SESSION['flash_ok'] = $msgNeutra;
        header("Location: {$index}");
        exit;
    }

    // Invalida tokens antigos
    $pdo->prepare("
        UPDATE password_resets
        SET usado_em = NOW()
        WHERE usuario_id = :uid
          AND usado_em IS NULL
    ")->execute([':uid' => (int)$user['id']]);

    // Gera token
    $tokenRaw  = bin2hex(random_bytes(32)); // token do link
    $tokenHash = password_hash($tokenRaw, PASSWORD_DEFAULT);
    $expiraEm  = date('Y-m-d H:i:s', time() + 1800); // 30 min

    $pdo->prepare("
        INSERT INTO password_resets
        (usuario_id, token_hash, expira_em, ip_solicitacao, user_agent)
        VALUES
        (:uid, :hash, :expira, :ip, :ua)
    ")->execute([
        ':uid'   => (int)$user['id'],
        ':hash'  => $tokenHash,
        ':expira'=> $expiraEm,
        ':ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua'    => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);

    // Link (ajuste domínio se quiser)
    $link = "https://" . ($_SERVER['HTTP_HOST'] ?? 'localhost')
          . "/resetarSenha.php?token={$tokenRaw}";

    /**
     * ENVIO DE EMAIL
     * Se não tiver mail() configurado, pode logar o link:
     */
    @mail(
        $user['email'],
        'Redefinição de senha - SIGRelatórios',
        "Olá {$user['nome']},\n\n"
        . "Para redefinir sua senha, acesse o link abaixo (válido por 30 minutos):\n\n"
        . "{$link}\n\n"
        . "Se você não solicitou, ignore este e-mail."
    );

    // DEBUG opcional (remova depois)
    // error_log("LINK RESET ({$user['email']}): {$link}");

    $_SESSION['flash_ok'] = $msgNeutra;
    header("Location: {$index}");
    exit;

} catch (Throwable $e) {
    error_log("ERRO RESET (query): " . $e->getMessage());
    $_SESSION['flash_erro'] = "Erro interno ao solicitar redefinição.";
    header("Location: {$index}");
    exit;
}
