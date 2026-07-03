<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Environment;
use App\Core\Logger;
use App\Core\Validator;

require_once dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = htmlspecialchars(Csrf::token('create-first-admin'), ENT_QUOTES, 'UTF-8');

    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Instalação SIGAS</title></head><body>';
    echo '<h1>Criar primeiro administrador</h1>';
    echo '<p>Use este instalador apenas uma vez e remova ou bloqueie o arquivo após o uso.</p>';
    echo '<form method="post">';
    echo '<input type="hidden" name="_csrf" value="' . $token . '">';
    echo '<p><label>Chave de instalação <input name="installation_key" type="password" required></label></p>';
    echo '<p><label>Nome <input name="nome" required></label></p>';
    echo '<p><label>CPF <input name="cpf" required></label></p>';
    echo '<p><label>E-mail <input name="email" type="email" required></label></p>';
    echo '<p><label>Senha temporária <input name="senha" type="password" required></label></p>';
    echo '<button type="submit">Criar administrador</button>';
    echo '</form></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Método não permitido.';
    exit;
}

$installationKey = Environment::required('INSTALLATION_KEY');
$providedKey = (string) ($_POST['installation_key'] ?? '');

if ($installationKey === '' || !hash_equals($installationKey, $providedKey)) {
    Logger::security('Invalid first admin installation key attempt.');
    http_response_code(403);
    echo 'Solicitação não autorizada.';
    exit;
}

if (!Csrf::validate((string) ($_POST['_csrf'] ?? ''), 'create-first-admin')) {
    Logger::security('Invalid CSRF token on first admin installer.');
    http_response_code(419);
    echo 'Solicitação expirada.';
    exit;
}

$name = trim((string) ($_POST['nome'] ?? ''));
$cpf = Validator::onlyDigits((string) ($_POST['cpf'] ?? ''));
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['senha'] ?? '');

if ($name === '' || !Validator::cpf($cpf) || !Validator::email($email) || !Validator::strongPassword($password)) {
    http_response_code(422);
    echo 'Dados inválidos.';
    exit;
}

$hashAlgorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
$passwordHash = password_hash($password, $hashAlgorithm);

if ($passwordHash === false) {
    http_response_code(500);
    echo 'Não foi possível preparar a conta inicial.';
    exit;
}

$pdo = Database::connection();

try {
    $pdo->beginTransaction();

    $adminExists = $pdo->query(
        "SELECT COUNT(*) FROM usuarios u
         INNER JOIN niveis_acesso n ON n.id = u.nivel_id
         WHERE n.slug = 'administrador' AND u.excluido_em IS NULL"
    )->fetchColumn();

    if ((int) $adminExists > 0) {
        $pdo->rollBack();
        http_response_code(409);
        echo 'Administrador inicial já existe. Remova ou bloqueie este arquivo.';
        exit;
    }

    $sectorId = $pdo->query("SELECT id FROM setores WHERE slug = 'administracao-sistema' LIMIT 1")->fetchColumn();
    $levelId = $pdo->query("SELECT id FROM niveis_acesso WHERE slug = 'administrador' LIMIT 1")->fetchColumn();

    if (!$sectorId || !$levelId) {
        throw new RuntimeException('Initial seed data is missing.');
    }

    $statement = $pdo->prepare(
        'INSERT INTO usuarios
            (setor_id, nivel_id, nome, cpf, email, senha_hash, status, precisa_trocar_senha, aprovado_em, criado_em)
         VALUES
            (:setor_id, :nivel_id, :nome, :cpf, :email, :senha_hash, :status, 1, NOW(), NOW())'
    );

    $statement->execute([
        'setor_id' => (int) $sectorId,
        'nivel_id' => (int) $levelId,
        'nome' => $name,
        'cpf' => $cpf,
        'email' => $email,
        'senha_hash' => $passwordHash,
        'status' => 'ativo',
    ]);

    $userId = (int) $pdo->lastInsertId();

    $audit = $pdo->prepare(
        'INSERT INTO auditoria (usuario_id, usuario_alvo_id, acao, modulo, descricao, ip, user_agent, criado_em)
         VALUES (:usuario_id, :usuario_alvo_id, :acao, :modulo, :descricao, :ip, :user_agent, NOW())'
    );

    $audit->execute([
        'usuario_id' => $userId,
        'usuario_alvo_id' => $userId,
        'acao' => 'primeiro_administrador_criado',
        'modulo' => 'instalacao',
        'descricao' => 'Primeiro administrador criado por instalador temporário.',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    $pdo->commit();

    echo 'Administrador inicial criado. Remova ou bloqueie este arquivo imediatamente.';
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    Logger::security('First admin creation failed.', ['type' => $exception::class]);
    http_response_code(500);
    echo 'Não foi possível concluir a criação do administrador inicial.';
}
