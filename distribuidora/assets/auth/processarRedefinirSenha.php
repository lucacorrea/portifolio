<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../dados/_helpers.php';
require_once __DIR__ . '/../conexao.php';

$redirectBack = normalize_redirect_url(
    post_str('redirect_back', '../../redefinir-senha.php'),
    '../../redefinir-senha.php'
);

if (!is_post()) {
    redirect($redirectBack);
}

$email = strtolower(trim(post_str('email')));
$codigo = preg_replace('/\D+/', '', post_str('codigo'));
$senha = post_raw('senha');
$confirmarSenha = post_raw('confirmar_senha');

$_SESSION['redefine_old'] = [
    'email' => $email,
    'codigo' => $codigo,
];

try {
    csrf_validate_or_die(post_str('_csrf'), 'Token CSRF invalido.');

    if (!filled($email) || !email_valido($email)) {
        throw new RuntimeException('Informe um e-mail valido.');
    }

    if (!preg_match('/^\d{6}$/', $codigo)) {
        throw new RuntimeException('Informe um codigo valido com 6 digitos.');
    }

    if (strlen($senha) < 8) {
        throw new RuntimeException('A senha deve ter no minimo 8 caracteres.');
    }

    if ($senha !== $confirmarSenha) {
        throw new RuntimeException('A confirmacao da senha nao confere.');
    }

    $pdo = require_db_or_die();

    $cleanup = $pdo->prepare("
        DELETE FROM senha_tokens
        WHERE expira_em < NOW()
           OR usado_em IS NOT NULL
    ");
    $cleanup->execute();

    $pdo->beginTransaction();

    $stmtUser = $pdo->prepare("
        SELECT id, nome, email, status, senha_hash, senha_salt
        FROM usuarios
        WHERE email = :email
        LIMIT 1
    ");
    $stmtUser->execute([
        ':email' => $email,
    ]);

    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new RuntimeException('E-mail nao encontrado.');
    }

    if (strtoupper((string)($usuario['status'] ?? '')) !== 'ATIVO') {
        throw new RuntimeException('Este usuario esta inativo.');
    }

    $stmtToken = $pdo->prepare("
        SELECT id, usuario_id, email, codigo_hash, expira_em, usado_em, tentativas
        FROM senha_tokens
        WHERE usuario_id = :usuario_id
          AND email = :email
          AND usado_em IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmtToken->execute([
        ':usuario_id' => (int)$usuario['id'],
        ':email' => $email,
    ]);

    $token = $stmtToken->fetch(PDO::FETCH_ASSOC);

    if (!$token) {
        throw new RuntimeException('Nenhum codigo ativo foi encontrado para este e-mail.');
    }

    if (strtotime((string)$token['expira_em']) < time()) {
        $exp = $pdo->prepare("
            UPDATE senha_tokens
            SET usado_em = NOW()
            WHERE id = :id
        ");
        $exp->execute([
            ':id' => (int)$token['id'],
        ]);

        throw new RuntimeException('O codigo expirou. Solicite outro.');
    }

    $codigoHash = hash('sha256', $codigo);

    if (!hash_equals((string)$token['codigo_hash'], $codigoHash)) {
        $tentativas = (int)($token['tentativas'] ?? 0) + 1;

        if ($tentativas >= 5) {
            $bloq = $pdo->prepare("
                UPDATE senha_tokens
                SET tentativas = :tentativas,
                    usado_em = NOW()
                WHERE id = :id
            ");
            $bloq->execute([
                ':tentativas' => $tentativas,
                ':id' => (int)$token['id'],
            ]);

            throw new RuntimeException('Codigo invalido. O token foi bloqueado. Solicite outro.');
        }

        $inc = $pdo->prepare("
            UPDATE senha_tokens
            SET tentativas = :tentativas
            WHERE id = :id
        ");
        $inc->execute([
            ':tentativas' => $tentativas,
            ':id' => (int)$token['id'],
        ]);

        throw new RuntimeException('Codigo invalido.');
    }

    $novoSalt = gerar_salt_senha();
    $novoHash = hash_senha_sha256($senha, $novoSalt);

    $updUser = $pdo->prepare("
        UPDATE usuarios
        SET senha_hash = :senha_hash,
            senha_salt = :senha_salt,
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");
    $updUser->execute([
        ':senha_hash' => $novoHash,
        ':senha_salt' => $novoSalt,
        ':id' => (int)$usuario['id'],
    ]);

    $updToken = $pdo->prepare("
        UPDATE senha_tokens
        SET usado_em = NOW()
        WHERE id = :id
    ");
    $updToken->execute([
        ':id' => (int)$token['id'],
    ]);

    $delOld = $pdo->prepare("
        DELETE FROM senha_tokens
        WHERE usuario_id = :usuario_id
          AND id <> :id
    ");
    $delOld->execute([
        ':usuario_id' => (int)$usuario['id'],
        ':id' => (int)$token['id'],
    ]);

    $pdo->commit();

    unset($_SESSION['redefine_old']);
    flash_set('login_ok', 'Senha redefinida com sucesso. Entre com sua nova senha.');
    redirect('../../index.php');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    @file_put_contents(
        __DIR__ . '/../debug_errors.log',
        '[' . date('Y-m-d H:i:s') . '] REDEFINIR_SENHA ERROR: ' . $e->getMessage() . PHP_EOL,
        FILE_APPEND
    );

    flash_set('redefine_erro', $e->getMessage());
    redirect($redirectBack . '?email=' . urlencode($email));
}