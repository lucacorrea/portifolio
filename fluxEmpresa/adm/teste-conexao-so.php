<?php

declare(strict_types=1);

use App\Core\Application;

$app = require dirname(__DIR__) . '/bootstrap.php';

/** @var Application $application */
$application = $app['application'];

$application->session()->start();

try {
    $user = $application->authorization()->requireLogin();

    if (!$user->isPlatformAdministrator()) {
        http_response_code(403);
        exit('Acesso negado.');
    }

    $total = $application->soSuppliers()->count('');

    echo '<h1>Conexão funcionando</h1>';
    echo '<p>Fornecedores encontrados: '
        . htmlspecialchars((string) $total, ENT_QUOTES, 'UTF-8')
        . '</p>';
} catch (Throwable $exception) {
    http_response_code(500);

    echo '<h1>Falha na conexão</h1>';
    echo '<p>Verifique o error_log da hospedagem.</p>';

    error_log(
        'Teste SO: tipo='
        . $exception::class
        . ' código='
        . $exception->getCode()
        . ' mensagem='
        . $exception->getMessage()
    );
}