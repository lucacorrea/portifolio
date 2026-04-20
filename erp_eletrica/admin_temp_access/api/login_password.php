<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!app_is_post()) {
    app_json(['ok' => false, 'message' => 'Método não permitido.'], 405);
}

$email = app_normalize_email((string)($_POST['email'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
    app_json(['ok' => false, 'message' => 'Informe e-mail e senha para continuar.'], 422);
}

$user = app_find_admin_by_email($pdo, $email);

if (!$user) {
    app_json(['ok' => false, 'message' => 'Administrador não encontrado ou sem permissão.'], 404);
}

if (!app_password_matches($pdo, $user, $senha)) {
    app_json(['ok' => false, 'message' => 'Senha inválida.'], 401);
}

app_set_admin_session($user);
app_update_last_login($pdo, (int)$user['id']);
$_SESSION['passkey_enroll_user_id'] = (int)$user['id'];

app_json([
    'ok' => true,
    'message' => 'Credenciais validadas com sucesso.',
    'offer_passkey' => !app_passkey_exists($pdo, (int)$user['id']) && app_webauthn_available(),
    'redirect' => 'gerar_usuario_temporario.php',
]);
