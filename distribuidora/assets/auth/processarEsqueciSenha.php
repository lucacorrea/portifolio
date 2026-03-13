<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/./_helpers.php';
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/mailer.php';

$redirectBack = normalize_redirect_url(
    post_str('redirect_back', '../../esqueci-senha.php'),
    '../../esqueci-senha.php'
);

if (!is_post()) {
    redirect($redirectBack);
}

$email = strtolower(trim(post_str('email')));
$_SESSION['recupera_old'] = ['email' => $email];

try {
    csrf_validate_or_die(post_str('_csrf'), 'Token CSRF invalido.');

    if (!filled($email) || !email_valido($email)) {
        throw new RuntimeException('Informe um e-mail valido.');
    }

    $pdo = require_db_or_die();

    $cleanup = $pdo->prepare("
        DELETE FROM senha_tokens
        WHERE expira_em < NOW()
           OR usado_em IS NOT NULL
    ");
    $cleanup->execute();

    $stmt = $pdo->prepare("
        SELECT id, nome, email, status
        FROM usuarios
        WHERE email = :email
        LIMIT 1
    ");
    $stmt->execute([
        ':email' => $email,
    ]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new RuntimeException('E-mail nao encontrado.');
    }

    if (strtoupper((string)($usuario['status'] ?? '')) !== 'ATIVO') {
        throw new RuntimeException('Este usuario esta inativo.');
    }

    $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $codigoHash = hash('sha256', $codigo);
    $expiraEm = date('Y-m-d H:i:s', time() + 300);

    $pdo->beginTransaction();

    $del = $pdo->prepare("
        DELETE FROM senha_tokens
        WHERE usuario_id = :usuario_id
           OR email = :email
    ");
    $del->execute([
        ':usuario_id' => (int)$usuario['id'],
        ':email' => (string)$usuario['email'],
    ]);

    $ins = $pdo->prepare("
        INSERT INTO senha_tokens (
            usuario_id,
            email,
            codigo_hash,
            expira_em
        ) VALUES (
            :usuario_id,
            :email,
            :codigo_hash,
            :expira_em
        )
    ");
    $ins->execute([
        ':usuario_id' => (int)$usuario['id'],
        ':email' => (string)$usuario['email'],
        ':codigo_hash' => $codigoHash,
        ':expira_em' => $expiraEm,
    ]);

    $pdo->commit();

    try {
        enviar_codigo_recuperacao(
            (string)$usuario['nome'],
            (string)$usuario['email'],
            $codigo
        );
    } catch (Throwable $mailError) {
        $delMail = $pdo->prepare("
            DELETE FROM senha_tokens
            WHERE usuario_id = :usuario_id
              AND email = :email
              AND codigo_hash = :codigo_hash
        ");
        $delMail->execute([
            ':usuario_id' => (int)$usuario['id'],
            ':email' => (string)$usuario['email'],
            ':codigo_hash' => $codigoHash,
        ]);

        throw new RuntimeException('Nao foi possivel enviar o e-mail. Verifique a configuracao SMTP.');
    }

    unset($_SESSION['recupera_old']);
    $_SESSION['redefine_old'] = [
        'email' => (string)$usuario['email'],
        'codigo' => '',
    ];

    flash_set('redefine_ok', 'Codigo enviado com sucesso. Ele expira em 5 minutos.');
    redirect('../../redefinir-senha.php?email=' . urlencode((string)$usuario['email']));
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    @file_put_contents(
        __DIR__ . '/../debug_errors.log',
        '[' . date('Y-m-d H:i:s') . '] ESQUECI_SENHA ERROR: ' . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    flash_set('recupera_erro', $e->getMessage());
    redirect($redirectBack);
}

?>