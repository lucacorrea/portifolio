<?php

declare(strict_types=1);

use App\Config\ModuleRegistry;
use App\Core\PageContext;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/repository.php';
require_once dirname(__DIR__) . '/frontend/modules/comida-mesa/lib/list-ui.php';

if (!isset($pageKey) || !is_string($pageKey) || $pageKey === '') {
    throw new RuntimeException('A página do módulo não foi informada.');
}

cm_require('comida_mesa.visualizar');

$environmentKey = 'comida-mesa';
$baseHref = '../';
$environment = ModuleRegistry::find($environmentKey);
$page = ModuleRegistry::findPage($environmentKey, $pageKey);

if ($environment === null || $page === null) {
    http_response_code(404);
    $errorTitle = 'Página não encontrada';
    $errorMessage = 'A página solicitada não pertence ao módulo Coari Comida na Mesa.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}

$frontendContext = cm_frontend_context(PageContext::requireAuthenticatedFrontendContext());
$moduleRepository = new ComidaMesaModuleRepository(cm_db());
$pageDefinition = [];
$pageCustomContent = '';
$pageExtraStyles = [];
$pageExtraScripts = [];
$view = dirname(__DIR__) . '/frontend/modules/comida-mesa/pages/' . $pageKey . '.php';

if (!is_file($view)) {
    http_response_code(404);
    $errorTitle = 'Conteúdo indisponível';
    $errorMessage = 'A estrutura desta página não foi localizada.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}

require $view;

$extraStyles = array_values(array_unique($pageExtraStyles));
$extraScripts = array_values(array_unique(array_merge([
    'assets/js/comida-mesa.js',
], $pageExtraScripts)));

/*
 * Os assets base do módulo são carregados pelo ModuleRegistry:
 *   assets/css/modules/comida-mesa.css
 *   assets/js/modules/comida-mesa.js
 *
 * /frontend permanece protegido pelo .htaccess e nunca deve ser
 * referenciado por URL pública.
 */

require dirname(__DIR__) . '/frontend/layouts/module-layout.php';
