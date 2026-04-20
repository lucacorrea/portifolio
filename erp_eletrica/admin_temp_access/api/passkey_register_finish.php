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

$challenge = (string)($_SESSION['passkey_challenge'] ?? '');
if ($challenge === '') {
    app_json(['ok' => false, 'message' => 'Desafio do dispositivo expirou. Tente novamente.'], 419);
}

$clientDataJSON = !empty($_POST['clientDataJSON']) ? base64_decode((string)$_POST['clientDataJSON'], true) : null;
$attestationObject = !empty($_POST['attestationObject']) ? base64_decode((string)$_POST['attestationObject'], true) : null;
$transports = (string)($_POST['transport'] ?? '[]');

if (!$clientDataJSON || !$attestationObject) {
    app_json(['ok' => false, 'message' => 'Resposta do dispositivo incompleta.'], 422);
}

try {
    $webauthn = app_webauthn_instance();
    $data = $webauthn->processCreate(
        $clientDataJSON,
        $attestationObject,
        $challenge,
        true,
        true,
        false
    );

    $signatureCounter = isset($data->signatureCounter) ? (int)$data->signatureCounter : 0;
    $credentialIdB64 = base64_encode((string)$data->credentialId);
    $credentialPublicKeyB64 = base64_encode((string)$data->credentialPublicKey);
    $userHandleB64 = base64_encode(app_user_handle($userId));

    $stmt = $pdo->prepare('SELECT id FROM user_passkeys WHERE credential_id_b64 = :credential_id_b64 LIMIT 1');
    $stmt->execute([':credential_id_b64' => $credentialIdB64]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $update = $pdo->prepare('UPDATE user_passkeys SET usuario_id = :usuario_id, credential_public_key_b64 = :credential_public_key_b64, sign_count = :sign_count, transports_json = :transports_json, user_handle_b64 = :user_handle_b64, last_used_at = NULL WHERE id = :id');
        $update->execute([
            ':usuario_id' => $userId,
            ':credential_public_key_b64' => $credentialPublicKeyB64,
            ':sign_count' => $signatureCounter,
            ':transports_json' => $transports,
            ':user_handle_b64' => $userHandleB64,
            ':id' => (int)$existingId,
        ]);
    } else {
        $insert = $pdo->prepare('INSERT INTO user_passkeys (usuario_id, credential_id_b64, credential_public_key_b64, sign_count, transports_json, user_handle_b64, created_at) VALUES (:usuario_id, :credential_id_b64, :credential_public_key_b64, :sign_count, :transports_json, :user_handle_b64, NOW())');
        $insert->execute([
            ':usuario_id' => $userId,
            ':credential_id_b64' => $credentialIdB64,
            ':credential_public_key_b64' => $credentialPublicKeyB64,
            ':sign_count' => $signatureCounter,
            ':transports_json' => $transports,
            ':user_handle_b64' => $userHandleB64,
        ]);
    }

    unset($_SESSION['passkey_challenge'], $_SESSION['passkey_enroll_user_id']);

    app_json([
        'ok' => true,
        'message' => 'Este dispositivo foi salvo com sucesso para os próximos logins.',
        'redirect' => 'gerar_usuario_temporario.php',
    ]);
} catch (Throwable $e) {
    app_json(['ok' => false, 'message' => 'Falha ao concluir o cadastro do dispositivo: ' . $e->getMessage()], 500);
}
