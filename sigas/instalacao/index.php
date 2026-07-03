<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Session;
use App\Services\FirstAdminService;

require_once dirname(__DIR__) . '/bootstrap.php';

$service = new FirstAdminService();

if ($service->lockExists()) {
    http_response_code(410);
    echo 'Instalação indisponível.';
    exit;
}

if (!$service->isAvailable()) {
    http_response_code(403);
    echo 'Instalação indisponível.';
    exit;
}

$attempts = (int) Session::get('installation_attempts', 0);

if ($attempts >= 5) {
    http_response_code(429);
    echo 'Muitas tentativas. Aguarde antes de tentar novamente.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = htmlspecialchars(Csrf::token('first-admin'), ENT_QUOTES, 'UTF-8');

    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Instalação SIGAS</title></head><body>';
    echo '<h1>Criar primeiro administrador</h1>';
    echo '<p>Use este instalador apenas uma vez. Depois, defina INSTALLATION_ENABLED=false e remova a pasta instalacao.</p>';
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

if (!$service->installationKeyMatches($_POST['installation_key'] ?? null)) {
    Session::put('installation_attempts', $attempts + 1);
    Logger::security('Invalid installation key attempt.');
    http_response_code(403);
    echo 'Solicitação não autorizada.';
    exit;
}

if (!Csrf::validateAndRotate($_POST['_csrf'] ?? null, 'first-admin')) {
    Session::put('installation_attempts', $attempts + 1);
    Logger::security('Invalid CSRF token on installer.');
    http_response_code(419);
    echo 'Solicitação expirada.';
    exit;
}

try {
    $service->create([
        'nome' => (string) ($_POST['nome'] ?? ''),
        'cpf' => (string) ($_POST['cpf'] ?? ''),
        'email' => (string) ($_POST['email'] ?? ''),
        'senha' => (string) ($_POST['senha'] ?? ''),
    ]);

    Session::remove('installation_attempts');
    echo 'Administrador inicial criado. Defina INSTALLATION_ENABLED=false e remova a pasta instalacao.';
} catch (Throwable $exception) {
    Logger::security('First admin installer failed.', ['type' => $exception::class]);
    http_response_code(422);
    echo 'Não foi possível concluir a instalação.';
}
