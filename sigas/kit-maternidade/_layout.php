<?php

declare(strict_types=1);

use App\Config\ModuleRegistry;
use App\Core\PageContext;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/support/operational-program-access.php';
require_once dirname(__DIR__) . '/frontend/support/operational-program-front.php';

if (!isset($pageKey) || !is_string($pageKey) || trim($pageKey) === '') {
    throw new RuntimeException('A página do módulo não foi informada.');
}

$pageKey = trim($pageKey);
$environmentKey = 'kit-maternidade';
$baseHref = '../';
$environment = ModuleRegistry::find($environmentKey);
$page = ModuleRegistry::findPage($environmentKey, $pageKey);

if ($environment === null || $page === null || ($page['target'] ?? null) !== 'view') {
    http_response_code(404);
    $errorTitle = 'Página não encontrada';
    $errorMessage = 'A página solicitada não pertence ao módulo Kit Maternidade.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}

$frontendContext = PageContext::requireAuthenticatedFrontendContext();
if (!isset($frontendContext['navigation'][$environmentKey])) {
    http_response_code(403);
    $errorTitle = 'Acesso não autorizado';
    $errorMessage = 'Seu setor, nível ou exceção individual não permite acessar o módulo Kit Maternidade.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}

if (!sigas_operational_can_page($environmentKey, $pageKey)) {
    http_response_code(403);
    $errorTitle = 'Área não autorizada';
    $errorMessage = 'Você possui acesso ao Kit Maternidade, mas não tem permissão para esta área específica.';
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
    throw new RuntimeException('A view do Kit Maternidade deve retornar uma definição de página.');
}

$pageDefinition = sigas_operational_filter_page_definition($environmentKey, $pageKey, $pageDefinition);
$pageDefinition = sigas_operational_front_enhance($environmentKey, $pageKey, $pageDefinition);
$menuVisiblePageKeys = sigas_operational_visible_pages($environmentKey);

$pageExtraStyles[] = 'assets/css/modules/operational-programs.css';
$pageExtraScripts[] = 'assets/js/modules/operational-programs.js';

$frontendContext['module'] = $environmentKey;
$frontendContext['page'] = $pageKey;
$frontendContext['operationalProgramAccess'] = [
    'module' => $environmentKey,
    'page' => $pageKey,
    'canMutate' => sigas_operational_can_mutate($environmentKey, $pageKey),
    'visiblePages' => $menuVisiblePageKeys,
];

$extraStyles = array_values(array_unique($pageExtraStyles));
$extraScripts = array_values(array_unique($pageExtraScripts));

require dirname(__DIR__) . '/frontend/layouts/module-layout.php';
