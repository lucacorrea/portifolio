<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/database.php';

$email = trim((string) getenv('SUPPORT_EMAIL')) ?: 'suporte@arteflor.demo';
$name = trim((string) getenv('SUPPORT_NAME')) ?: 'Suporte';
$password = (string) getenv('SUPPORT_PASSWORD');
$profile = trim((string) getenv('SUPPORT_PROFILE')) ?: 'operador';
$allowedProfiles = ['admin', 'gerente', 'operador'];

if ($password === '') {
    fwrite(STDERR, "Defina SUPPORT_PASSWORD antes de executar.\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "SUPPORT_EMAIL inválido.\n");
    exit(1);
}

if (!in_array($profile, $allowedProfiles, true)) {
    fwrite(STDERR, "SUPPORT_PROFILE deve ser admin, gerente ou operador.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Aviso: senha fraca. Troque assim que possível.\n");
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
if ($passwordHash === false) {
    fwrite(STDERR, "Não foi possível gerar o hash da senha.\n");
    exit(1);
}

$statement = db()->prepare(
    'INSERT INTO usuarios_admin (nome, email, senha_hash, perfil, ativo)
     VALUES (:nome, :email, :senha_hash, :perfil, 1)
     ON DUPLICATE KEY UPDATE
       nome = VALUES(nome),
       senha_hash = VALUES(senha_hash),
       perfil = VALUES(perfil),
       ativo = 1,
       atualizado_em = CURRENT_TIMESTAMP'
);

$statement->execute([
    'nome' => $name,
    'email' => $email,
    'senha_hash' => $passwordHash,
    'perfil' => $profile,
]);

echo "Usuário de suporte criado/atualizado: {$email}\n";
