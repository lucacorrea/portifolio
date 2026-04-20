<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!app_is_post()) {
    app_json(['ok' => false, 'message' => 'Método não permitido.'], 405);
}

$admin = app_current_admin();
if (!$admin || !app_is_admin_level($admin['nivel'] ?? null)) {
    app_json(['ok' => false, 'message' => 'Acesso não autorizado.'], 401);
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    app_json(['ok' => false, 'message' => 'Registro inválido.'], 422);
}

$stmt = $pdo->prepare('UPDATE usuarios_temporarios SET revogado_em = NOW() WHERE id = :id AND admin_usuario_id = :admin_usuario_id LIMIT 1');
$stmt->execute([
    ':id' => $id,
    ':admin_usuario_id' => (int)$admin['id'],
]);

if ($stmt->rowCount() <= 0) {
    app_json(['ok' => false, 'message' => 'Não foi possível revogar este acesso.'], 404);
}

app_json([
    'ok' => true,
    'message' => 'Acesso temporário revogado com sucesso.',
]);
