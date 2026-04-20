<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';

try {
    expire_temp_users();

    $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $email = trim((string)($payload['email'] ?? ''));

    if ($email === '') {
        throw new RuntimeException('Informe o e-mail do administrador.');
    }

    $user = find_user_by_email($email);

    if (!$user || (int)$user['ativo'] !== 1) {
        throw new RuntimeException('Administrador não encontrado.');
    }

    if (!is_admin_level((string)$user['nivel'])) {
        throw new RuntimeException('Somente admin/master podem usar esta biometria.');
    }

    if ((int)$user['is_temp_admin'] === 1) {
        throw new RuntimeException('Usuário temporário não pode autenticar nesta tela por biometria.');
    }

    if ((int)($user['passkey_enabled'] ?? 0) !== 1) {
        throw new RuntimeException('Biometria ainda não cadastrada para este admin.');
    }

    $stmt = db()->prepare("SELECT credential_id_b64 FROM usuario_passkeys WHERE usuario_id = ?");
    $stmt->execute([(int)$user['id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        throw new RuntimeException('Nenhum aparelho biométrico cadastrado.');
    }

    $credentialIds = [];
    foreach ($rows as $row) {
        $credentialIds[] = base64_decode((string)$row['credential_id_b64'], true);
    }

    $server = webauthn_server();
    $getArgs = $server->getGetArgs(
        $credentialIds,
        240,
        false,
        false,
        false,
        false,
        true,
        'required'
    );

    $_SESSION['passkey_login_challenge'] = $server->getChallenge();
    $_SESSION['passkey_login_user_id'] = (int)$user['id'];

    json_out([
        'ok' => true,
        'publicKey' => $getArgs,
    ]);
} catch (Throwable $e) {
    json_out([
        'ok' => false,
        'message' => $e->getMessage(),
    ], 422);
}