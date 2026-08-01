<?php

declare(strict_types=1);

use App\Config\ModuleRegistry;
use App\Core\PageContext;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/frontend/support/helpers.php';

// Autenticação ocorre antes de qualquer decisão de rota ou resposta visual.
$frontendContext = PageContext::requireAuthenticatedFrontendContext();
$environmentKey = is_string($_GET['ambiente'] ?? null) ? trim($_GET['ambiente']) : '';
$pageKey = is_string($_GET['pagina'] ?? null) ? trim($_GET['pagina']) : '';
$validKey = static fn (string $value): bool => preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $value) === 1;
$environment = $validKey($environmentKey) ? ModuleRegistry::find($environmentKey) : null;

if ($environment !== null && $pageKey === '') {
    $pageKey = (string) $environment['home_page'];
}

$page = $environment !== null && $validKey($pageKey)
    ? ModuleRegistry::findPage($environmentKey, $pageKey)
    : null;

if ($environment === null || $page === null || ($page['target'] ?? null) !== 'view') {
    http_response_code(404);
    $errorTitle = 'Página não encontrada';
    $errorMessage = 'O endereço informado não corresponde a uma página visual disponível no SIGAS.';
    require __DIR__ . '/frontend/layouts/error-layout.php';
    exit;
}

$frontendRoot = realpath(__DIR__ . '/frontend/modules');
$view = realpath(__DIR__ . '/frontend/modules/' . $page['view']);

if ($frontendRoot === false || $view === false || !str_starts_with($view, $frontendRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    $errorTitle = 'Conteúdo indisponível';
    $errorMessage = 'A página está registrada, mas sua view ainda não está disponível.';
    require __DIR__ . '/frontend/layouts/error-layout.php';
    exit;
}

$pageDefinition = require $view;

if (!is_array($pageDefinition)) {
    throw new RuntimeException('A view visual deve retornar uma definição de página.');
}

$frontendContext['module'] = $environmentKey;
$frontendContext['page'] = $pageKey;
require __DIR__ . '/frontend/layouts/module-layout.php';
