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

$nomeTemporario = trim((string)($_POST['nome_temporario'] ?? ''));
$nivelTemporario = trim((string)($_POST['nivel_temporario'] ?? ''));
$observacao = trim((string)($_POST['observacao'] ?? ''));

if ($nomeTemporario === '' || $nivelTemporario === '') {
    app_json(['ok' => false, 'message' => 'Informe o nome e o nível temporário.'], 422);
}

$allowed = app_allowed_temp_levels((string)$admin['nivel']);
if (!in_array($nivelTemporario, $allowed, true)) {
    app_json(['ok' => false, 'message' => 'Este administrador não pode gerar acesso temporário para este nível.'], 403);
}

$codigo = app_generate_temp_code();
$validoAte = (new DateTimeImmutable('now'))->modify('+30 minutes')->format('Y-m-d H:i:s');

$stmt = $pdo->prepare('INSERT INTO usuarios_temporarios (admin_usuario_id, filial_id, nome_temporario, nivel_temporario, codigo_acesso, observacao, valido_ate, created_at) VALUES (:admin_usuario_id, :filial_id, :nome_temporario, :nivel_temporario, :codigo_acesso, :observacao, :valido_ate, NOW())');
$stmt->execute([
    ':admin_usuario_id' => (int)$admin['id'],
    ':filial_id' => $admin['filial_id'],
    ':nome_temporario' => $nomeTemporario,
    ':nivel_temporario' => $nivelTemporario,
    ':codigo_acesso' => $codigo,
    ':observacao' => $observacao !== '' ? $observacao : null,
    ':valido_ate' => $validoAte,
]);

$id = (int)$pdo->lastInsertId();

app_json([
    'ok' => true,
    'message' => 'Usuário temporário criado com validade de 30 minutos.',
    'item' => [
        'id' => $id,
        'nome_temporario' => $nomeTemporario,
        'nivel_temporario' => $nivelTemporario,
        'codigo_acesso' => $codigo,
        'valido_ate' => $validoAte,
        'valido_ate_formatado' => app_format_dt($validoAte),
        'restante_minutos' => app_remaining_minutes($validoAte),
    ],
]);
