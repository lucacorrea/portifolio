<?php

declare(strict_types=1);

use App\Config\ModuleRegistry;
use App\Core\PageContext;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/primeiro-emprego/data/demo-data.php';
$frontendContext = PageContext::requireAuthenticatedFrontendContext();
if (!isset($frontendContext['navigation']['primeiro-emprego'])) {
    http_response_code(403);
    $errorTitle = 'Acesso não autorizado';
    $errorMessage = 'Seu setor ou perfil não possui acesso a este módulo.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}

if (!isset($pageKey) || !is_string($pageKey)) {
    throw new RuntimeException('A página do módulo não foi informada.');
}

$environmentKey = 'primeiro-emprego';
$baseHref = '../';
$environment = ModuleRegistry::find($environmentKey);
$page = ModuleRegistry::findPage($environmentKey, $pageKey);

if ($environment === null || $page === null) {
    http_response_code(404);
    $errorTitle = 'Página não encontrada';
    $errorMessage = 'A página solicitada não pertence ao módulo Coari Meu Primeiro Emprego.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}

$pageDefinition = [];
$pageCustomContent = '';
$view = dirname(__DIR__) . '/frontend/modules/primeiro-emprego/pages/' . $pageKey . '.php';

if (!is_file($view)) {
    http_response_code(404);
    $errorTitle = 'Conteúdo indisponível';
    $errorMessage = 'A estrutura visual desta página não foi localizada.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}

require $view;
$extraStyles = isset($extraStyles) && is_array($extraStyles) ? $extraStyles : [];
$extraScripts = isset($extraScripts) && is_array($extraScripts) ? $extraScripts : [];
require dirname(__DIR__) . '/frontend/layouts/module-layout.php';
