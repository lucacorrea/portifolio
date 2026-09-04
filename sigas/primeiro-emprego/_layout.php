<?php

declare(strict_types=1);

use App\Config\ModuleRegistry;
use App\Core\PageContext;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/primeiro-emprego/data/demo-data.php';
require_once dirname(__DIR__) . '/frontend/modules/primeiro-emprego/lib/access.php';

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

if (!pe_can_page($pageKey)) {
    http_response_code(403);
    $errorTitle = 'Acesso não autorizado';
    $errorMessage = 'Seu nível de acesso não possui permissão para esta área do Primeiro Emprego.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}

if ($pageKey === 'candidatos' && !pe_can('primeiro_emprego.editar')) {
    $restrictedCandidateAction = false;
    foreach (['revisar', 'visita', 'ficha'] as $candidateActionKey) {
        if ((int) ($_GET[$candidateActionKey] ?? 0) > 0) {
            $restrictedCandidateAction = true;
            break;
        }
    }

    if ($restrictedCandidateAction) {
        http_response_code(403);
        $errorTitle = 'Operação não autorizada';
        $errorMessage = 'Seu nível permite consultar candidatos, mas não abrir formulários de revisão ou acompanhamento.';
        require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
        return;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['pe_action'] ?? ''));
    $postPermission = pe_post_permission($pageKey, $action);

    if ($postPermission === null || !pe_can($postPermission)) {
        http_response_code(403);
        $errorTitle = 'Operação não autorizada';
        $errorMessage = 'Seu nível de acesso permite consultar esta área, mas não executar esta alteração.';
        require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
        return;
    }
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

$frontendContext['primeiroEmpregoAccess'] = pe_access_snapshot($pageKey);
$extraStyles = isset($extraStyles) && is_array($extraStyles) ? $extraStyles : [];
$extraScripts = isset($extraScripts) && is_array($extraScripts) ? $extraScripts : [];
$extraScripts[] = 'assets/js/modules/primeiro-emprego-access.js';
$extraScripts = array_values(array_unique($extraScripts));

require dirname(__DIR__) . '/frontend/layouts/module-layout.php';
