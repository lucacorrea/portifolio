<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!app_is_post()) {
    app_json(['ok' => false, 'message' => 'Método não permitido.'], 405);
}

if (!app_webauthn_available()) {
    app_json(['ok' => false, 'message' => 'Biblioteca WebAuthn não instalada. Execute: composer require lbuchs/webauthn'], 500);
}

try {
    $webauthn = app_webauthn_instance();
    $options = $webauthn->getGetArgs(
        [],
        60 * 4,
        false,
        false,
        false,
        true,
        true,
        'required'
    );

    $_SESSION['passkey_auth_challenge'] = app_get_challenge_binary($webauthn->getChallenge());

    app_json([
        'ok' => true,
        'options' => $options,
    ]);
} catch (Throwable $e) {
    app_json(['ok' => false, 'message' => 'Falha ao iniciar a autenticação biométrica: ' . $e->getMessage()], 500);
}
