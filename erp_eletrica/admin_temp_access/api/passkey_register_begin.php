<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!app_is_post()) {
    app_json(['ok' => false, 'message' => 'Método não permitido.'], 405);
}

$admin = app_current_admin();
$userId = (int)($_SESSION['passkey_enroll_user_id'] ?? 0);

if (!$admin || $userId <= 0 || $userId !== (int)$admin['id']) {
    app_json(['ok' => false, 'message' => 'Sessão de cadastro do dispositivo não encontrada.'], 401);
}

if (!app_webauthn_available()) {
    app_json(['ok' => false, 'message' => 'Biblioteca WebAuthn não instalada. Execute: composer require lbuchs/webauthn'], 500);
}

$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id AND ativo = 1 LIMIT 1');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    app_json(['ok' => false, 'message' => 'Usuário do admin não encontrado.'], 404);
}

try {
    $webauthn = app_webauthn_instance();
    $options = $webauthn->getCreateArgs(
        app_user_handle((int)$user['id']),
        (string)$user['email'],
        (string)$user['nome'],
        60 * 4,
        true,
        'required',
        false
    );

    $_SESSION['passkey_challenge'] = app_get_challenge_binary($webauthn->getChallenge());

    app_json([
        'ok' => true,
        'options' => $options,
    ]);
} catch (Throwable $e) {
    app_json(['ok' => false, 'message' => 'Falha ao iniciar o cadastro do dispositivo: ' . $e->getMessage()], 500);
}
