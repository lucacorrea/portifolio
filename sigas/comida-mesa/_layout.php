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

$baseFrontendContext = PageContext::requireAuthenticatedFrontendContext();
if (!isset($baseFrontendContext['navigation']['comida-mesa'])) {
    http_response_code(403);
    $errorTitle = 'Acesso não autorizado';
    $errorMessage = 'Seu setor ou perfil não possui acesso a este módulo.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}
$frontendContext = cm_frontend_context($baseFrontendContext);
$moduleRepository = new ComidaMesaModuleRepository(cm_db());
$pageDefinition = [];
$pageCustomContent = '';
$pageExtraStyles = [];
$pageExtraScripts = [];

$viewName = $pageKey === 'beneficiarios' ? 'beneficiarios-review' : $pageKey;
$view = dirname(__DIR__) . '/frontend/modules/comida-mesa/pages/' . $viewName . '.php';

if (!is_file($view)) {
    // Compatibilidade: enquanto a nova tela não estiver publicada, preserva a anterior.
    $view = dirname(__DIR__) . '/frontend/modules/comida-mesa/pages/' . $pageKey . '.php';
}

if (!is_file($view)) {
    http_response_code(404);
    $errorTitle = 'Conteúdo indisponível';
    $errorMessage = 'A estrutura desta página não foi localizada.';
    require dirname(__DIR__) . '/frontend/layouts/error-layout.php';
    return;
}

/*
 * Compatibilidade isolada da conferência de importação.
 *
 * A rotina legada cm_import_review_items() ainda reutiliza o mesmo placeholder
 * nomeado em vários LIKEs. O SIGAS usa prepared statements nativos globalmente,
 * o que é o padrão correto, mas o MySQL/PDO não aceita reutilização de placeholder
 * nesse modo e dispara SQLSTATE[HY093] quando review_search é informado.
 *
 * Limitamos a emulação SOMENTE a esta requisição/tela e restauramos a configuração
 * logo após montar a view. Nenhuma outra página ou operação do SIGAS é afetada.
 */
$compatPdo = null;
$restoreNativePrepares = false;
if ($pageKey === 'importar-beneficiarios' && trim((string) ($_GET['review_search'] ?? '')) !== '') {
    try {
        $compatPdo = cm_db();
        if (!(bool) $compatPdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) {
            $compatPdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $restoreNativePrepares = true;
        }
    } catch (Throwable) {
        $compatPdo = null;
        $restoreNativePrepares = false;
    }
}

try {
    require $view;
} finally {
    if ($restoreNativePrepares && $compatPdo instanceof PDO) {
        try {
            $compatPdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (Throwable) {
            // A configuração global da conexão já é nativa; não mascarar a resposta da página.
        }
    }
}

$extraStyles = array_values(array_unique($pageExtraStyles));
$extraScripts = array_values(array_unique(array_merge([
    'assets/js/comida-mesa.js',
    'assets/js/modules/comida-mesa-conflicts.js',
    'assets/js/modules/comida-mesa-import-review.js',
    'assets/js/modules/comida-mesa-beneficiary-review.js',
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
