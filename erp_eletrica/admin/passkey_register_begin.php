<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_guard_real_admin.php';

try {
    $userHandleHex = ensure_passkey_handle((int)$usuarioLogado['id']);

    $server = webauthn_server();
    $createArgs = $server->getCreateArgs(
        hex2bin($userHandleHex),
        (string)$usuarioLogado['email'],
        (string)$usuarioLogado['nome'],
        240,
        false,
        'required',
        false
    );

    $_SESSION['passkey_register_challenge'] = $server->getChallenge();
    $_SESSION['passkey_register_user_id'] = (int)$usuarioLogado['id'];

    json_out([
        'ok' => true,
        'publicKey' => $createArgs,
    ]);
} catch (Throwable $e) {
    json_out([
        'ok' => false,
        'message' => $e->getMessage(),
    ], 422);
}

?>