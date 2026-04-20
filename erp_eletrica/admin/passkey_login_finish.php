<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

try {
    $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

    $userId = (int)($_SESSION['passkey_login_user_id'] ?? 0);
    $challenge = (string)($_SESSION['passkey_login_challenge'] ?? '');

    if ($userId <= 0 || $challenge === '') {
        throw new RuntimeException('Sessão biométrica expirada.');
    }

    $user = find_user_by_id($userId);

    if (!$user || (int)$user['ativo'] !== 1) {
        throw new RuntimeException('Usuário inválido.');
    }

    if (!is_admin_level((string)$user['nivel']) || (int)$user['is_temp_admin'] === 1) {
        throw new RuntimeException('Usuário sem permissão para este acesso.');
    }

    $credentialIdB64 = (string)($payload['id'] ?? '');
    if ($credentialIdB64 === '') {
        throw new RuntimeException('Credential ID ausente.');
    }

    $stmt = db()->prepare("
        SELECT *
          FROM usuario_passkeys
         WHERE usuario_id = :usuario_id
           AND credential_id_b64 = :credential_id_b64
         LIMIT 1
    ");
    $stmt->execute([
        ':usuario_id' => $userId,
        ':credential_id_b64' => $credentialIdB64,
    ]);

    $passkey = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$passkey) {
        throw new RuntimeException('Passkey não encontrada para este usuário.');
    }

    $clientDataJSON = base64_decode((string)($payload['clientDataJSON'] ?? ''), true);
    $authenticatorData = base64_decode((string)($payload['authenticatorData'] ?? ''), true);
    $signature = base64_decode((string)($payload['signature'] ?? ''), true);
    $publicKey = base64_decode((string)$passkey['public_key_b64'], true);

    if ($clientDataJSON === false || $authenticatorData === false || $signature === false || $publicKey === false) {
        throw new RuntimeException('Dados biométricos inválidos.');
    }

    $server = webauthn_server();
    $server->processGet(
        $clientDataJSON,
        $authenticatorData,
        $signature,
        $publicKey,
        $challenge,
        null,
        true
    );

    update_last_login((int)$user['id']);
    login_user($user, true);

    $stmt = db()->prepare("UPDATE usuario_passkeys SET last_used_at = NOW() WHERE id = ?");
    $stmt->execute([(int)$passkey['id']]);

    unset($_SESSION['passkey_login_challenge'], $_SESSION['passkey_login_user_id']);

    json_out([
        'ok' => true,
        'redirect' => 'painel_admin.php',
    ]);
} catch (Throwable $e) {
    json_out([
        'ok' => false,
        'message' => $e->getMessage(),
    ], 422);
}