<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!app_is_post()) {
    app_json(['ok' => false, 'message' => 'Método não permitido.'], 405);
}

$codigo = strtoupper(trim((string)($_POST['codigo_acesso'] ?? '')));
if ($codigo === '') {
    app_json(['ok' => false, 'message' => 'Informe o código temporário.'], 422);
}

$stmt = $pdo->prepare("SELECT * FROM usuarios_temporarios WHERE codigo_acesso = :codigo_acesso AND revogado_em IS NULL AND valido_ate >= NOW() LIMIT 1");
$stmt->execute([':codigo_acesso' => $codigo]);
$item = $stmt->fetch();

if (!$item) {
    app_json(['ok' => false, 'message' => 'Código temporário inválido, expirado ou revogado.'], 404);
}

$_SESSION['temp_auth'] = [
    'id' => (int)$item['id'],
    'nome_temporario' => (string)$item['nome_temporario'],
    'nivel_temporario' => (string)$item['nivel_temporario'],
    'codigo_acesso' => (string)$item['codigo_acesso'],
    'valido_ate' => (string)$item['valido_ate'],
    'admin_usuario_id' => (int)$item['admin_usuario_id'],
    'filial_id' => isset($item['filial_id']) ? (int)$item['filial_id'] : null,
];

$update = $pdo->prepare('UPDATE usuarios_temporarios SET usado_em = COALESCE(usado_em, NOW()) WHERE id = :id LIMIT 1');
$update->execute([':id' => (int)$item['id']]);

app_json([
    'ok' => true,
    'message' => 'Código temporário validado com sucesso.',
]);
