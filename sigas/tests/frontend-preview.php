<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
require_once dirname(__DIR__) . '/frontend/support/helpers.php';

App\Core\Autoloader::register();

use App\Config\ModuleRegistry;

$environmentKey = is_string($_GET['ambiente'] ?? null) ? $_GET['ambiente'] : 'planejamento-gestao';
$pageKey = is_string($_GET['pagina'] ?? null) ? $_GET['pagina'] : 'painel';
$environment = ModuleRegistry::find($environmentKey);
$page = ModuleRegistry::findPage($environmentKey, $pageKey);

if ($environment === null || $page === null) {
    http_response_code(404);
    exit('Preview não encontrado.');
}

$viewPath = $page['target'] === 'view'
    ? dirname(__DIR__) . '/frontend/modules/' . $page['view']
    : dirname(__DIR__) . '/frontend/modules/' . $environmentKey . '/pages/' . $pageKey . '.php';

if (!is_file($viewPath)) {
    http_response_code(404);
    exit('Esta rota pública funcional não possui preview isolado.');
}

$dataFile = dirname(__DIR__) . '/frontend/modules/' . $environmentKey . '/data/demo-data.php';
if (is_file($dataFile)) {
    require_once $dataFile;
}

$pageDefinition = [];
$pageCustomContent = '';
$result = require $viewPath;

if (is_array($result)) {
    $pageDefinition = $result;
}

$frontendContext = [
    'user' => ['name' => 'Usuário de Teste', 'initials' => 'UT', 'jobTitle' => 'Perfil demonstrativo', 'sector' => 'SEMAS'],
    'urls' => ['dashboard' => 'portal.php', 'logout' => 'sair.php'],
    'csrf' => ['logout' => 'token-demonstrativo'],
    'navigation' => ModuleRegistry::all(),
    'module' => $environmentKey,
    'page' => $pageKey,
];
$baseHref = '../';
$extraStyles = $environmentKey === 'primeiro-emprego' ? ['frontend/modules/primeiro-emprego/module.css'] : [];
$extraScripts = $environmentKey === 'primeiro-emprego' ? ['frontend/modules/primeiro-emprego/module.js'] : [];

require dirname(__DIR__) . '/frontend/layouts/module-layout.php';
