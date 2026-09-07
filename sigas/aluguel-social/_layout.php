<?php

declare(strict_types=1);

use App\Config\ModuleRegistry;
use App\Core\PageContext;

require_once dirname(__DIR__) . '/bootstrap.php';

if (!isset($pageKey) || !is_string($pageKey) || $pageKey === '') {
    throw new RuntimeException('A página do módulo não foi informada.');
}

$environmentKey = 'aluguel-social';
$baseHref = '../';
$environment = ModuleRegistry::find($environmentKey);
$page = ModuleRegistry::findPage($environmentKey, $pageKey);

if ($environment === null || $page === null || ($page['target'] ?? null) !== 'view') {
    http_response_code(404);
    $errorTitle = 'Página não encontrada';
    $errorMessage = 'A página solicitada não pertence ao módulo Aluguel Social.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}

$frontendContext = PageContext::requireAuthenticatedFrontendContext();
if (!isset($frontendContext['navigation'][$environmentKey])) {
    http_response_code(403);
    $errorTitle = 'Acesso não autorizado';
    $errorMessage = 'Seu perfil não possui acesso ao módulo Aluguel Social.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}

$frontendRoot = realpath(dirname(__DIR__) . '/frontend/modules');
$view = realpath(dirname(__DIR__) . '/frontend/modules/' . (string) ($page['view'] ?? ''));

if ($frontendRoot === false || $view === false || !str_starts_with($view, $frontendRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    $errorTitle = 'Conteúdo indisponível';
    $errorMessage = 'A estrutura desta página não foi localizada.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}

$pageDefinition = [];
$pageCustomContent = '';
$pageExtraStyles = [];
$pageExtraScripts = [];
$pageDefinition = require $view;

if (!is_array($pageDefinition)) {
    throw new RuntimeException('A view do Aluguel Social deve retornar uma definição de página.');
}

$frontendContext['module'] = $environmentKey;
$frontendContext['page'] = $pageKey;
$extraStyles = array_values(array_unique($pageExtraStyles));
$extraScripts = array_values(array_unique($pageExtraScripts));

require dirname(__DIR__) . '/frontend/layouts/module-layout.php';
