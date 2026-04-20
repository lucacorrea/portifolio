<?php
declare(strict_types=1);
require_once __DIR__ . '/auth_guard_real_admin.php';

try {
    $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

    $challenge = $_SESSION['passkey_register_challenge'] ?? null;
    $userId    = (int)($_SESSION['passkey_register_user_id'] ?? 0);

    if (!$challenge || $userId <= 0 || $userId !== (int)$usuarioLogado['id']) {
        throw new RuntimeException('Sessão de biometria inválida.');
    }

    $clientDataJSON = base64_decode((string)($payload['clientDataJSON'] ?? ''), true);
    $attestationObject = base64_decode((string)($payload['attestationObject'] ?? ''), true);

    if ($clientDataJSON === false || $attestationObject === false) {
        throw new RuntimeException('Dados da biometria inválidos.');
    }

    $server = webauthn_server();
    $data = $server->processCreate(
        $clientDataJSON,
        $attestationObject,
        $challenge,
        true,
        true,
        false
    );

    $credentialIdB64 = base64_encode((string)$data->credentialId);
    $publicKeyB64    = base64_encode((string)$data->credentialPublicKey);
    $signCount       = (int)($data->signatureCounter ?? 0);

    $aaguid = null;
    if (isset($data->AAGUID)) {
        $aaguid = is_string($data->AAGUID) ? bin2hex($data->AAGUID) : (string)$data->AAGUID;
    }

    $fmt = isset($data->fmt) ? (string)$data->fmt : null;

    $stmt = db()->prepare("
        INSERT INTO usuario_passkeys (
            usuario_id, credential_id_b64, public_key_b64, sign_count, aaguid, fmt, created_at, last_used_at
        ) VALUES (
            :usuario_id, :credential_id_b64, :public_key_b64, :sign_count, :aaguid, :fmt, NOW(), NULL
        )
        ON DUPLICATE KEY UPDATE
            public_key_b64 = VALUES(public_key_b64),
            sign_count = VALUES(sign_count),
            aaguid = VALUES(aaguid),
            fmt = VALUES(fmt)
    ");

    $stmt->execute([
        ':usuario_id'        => (int)$usuarioLogado['id'],
        ':credential_id_b64' => $credentialIdB64,
        ':public_key_b64'    => $publicKeyB64,
        ':sign_count'        => $signCount,
        ':aaguid'            => $aaguid,
        ':fmt'               => $fmt,
    ]);

    $stmt = db()->prepare("
        UPDATE usuarios
           SET passkey_enabled = 1
         WHERE id = ?
    ");
    $stmt->execute([(int)$usuarioLogado['id']]);

    unset($_SESSION['passkey_register_challenge'], $_SESSION['passkey_register_user_id']);

    json_out([
        'ok' => true,
        'message' => 'Biometria cadastrada com sucesso.',
    ]);
} catch (Throwable $e) {
    json_out([
        'ok' => false,
        'message' => $e->getMessage(),
    ], 422);
}

?>