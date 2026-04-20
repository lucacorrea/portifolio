<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!app_is_post()) {
    app_json(['ok' => false, 'message' => 'Método não permitido.'], 405);
}

$challenge = (string)($_SESSION['passkey_auth_challenge'] ?? '');
if ($challenge === '') {
    app_json(['ok' => false, 'message' => 'Desafio biométrico expirou. Tente novamente.'], 419);
}

$idBinary = !empty($_POST['id']) ? base64_decode((string)$_POST['id'], true) : null;
$clientDataJSON = !empty($_POST['clientDataJSON']) ? base64_decode((string)$_POST['clientDataJSON'], true) : null;
$authenticatorData = !empty($_POST['authenticatorData']) ? base64_decode((string)$_POST['authenticatorData'], true) : null;
$signature = !empty($_POST['signature']) ? base64_decode((string)$_POST['signature'], true) : null;
$userHandle = !empty($_POST['userHandle']) ? base64_decode((string)$_POST['userHandle'], true) : null;

if (!$idBinary || !$clientDataJSON || !$authenticatorData || !$signature || !$userHandle) {
    app_json(['ok' => false, 'message' => 'Resposta biométrica incompleta.'], 422);
}

$idB64 = base64_encode($idBinary);
$userHandleB64 = base64_encode($userHandle);

$stmt = $pdo->prepare('SELECT up.*, u.id AS usuario_id_real, u.filial_id, u.nome, u.email, u.nivel, u.avatar, u.ativo FROM user_passkeys up INNER JOIN usuarios u ON u.id = up.usuario_id WHERE up.credential_id_b64 = :credential_id_b64 LIMIT 1');
$stmt->execute([':credential_id_b64' => $idB64]);
$row = $stmt->fetch();

if (!$row) {
    app_json(['ok' => false, 'message' => 'Dispositivo não reconhecido para este sistema.'], 404);
}

if ((string)$row['user_handle_b64'] !== $userHandleB64) {
    app_json(['ok' => false, 'message' => 'A credencial retornada não corresponde ao usuário salvo.'], 401);
}

if ((int)$row['ativo'] !== 1 || !app_is_admin_level((string)$row['nivel'])) {
    app_json(['ok' => false, 'message' => 'O usuário vinculado a esta credencial não pode acessar esta área.'], 403);
}

try {
    $webauthn = app_webauthn_instance();
    $webauthn->processGet(
        $clientDataJSON,
        $authenticatorData,
        $signature,
        base64_decode((string)$row['credential_public_key_b64'], true) ?: '',
        $challenge,
        null,
        true
    );

    app_set_admin_session([
        'id' => (int)$row['usuario_id_real'],
        'filial_id' => $row['filial_id'],
        'nome' => (string)$row['nome'],
        'email' => (string)$row['email'],
        'nivel' => (string)$row['nivel'],
        'avatar' => (string)$row['avatar'],
    ]);
    app_update_last_login($pdo, (int)$row['usuario_id_real']);

    $update = $pdo->prepare('UPDATE user_passkeys SET last_used_at = NOW() WHERE id = :id LIMIT 1');
    $update->execute([':id' => (int)$row['id']]);

    unset($_SESSION['passkey_auth_challenge']);

    app_json([
        'ok' => true,
        'message' => 'Biometria validada com sucesso.',
        'redirect' => 'gerar_usuario_temporario.php',
    ]);
} catch (Throwable $e) {
    app_json(['ok' => false, 'message' => 'Falha na validação biométrica: ' . $e->getMessage()], 500);
}
