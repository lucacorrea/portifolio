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
$operationalPreview = ($_GET['modo'] ?? '') === 'operacional';

if ($environment === null || $page === null) {
    http_response_code(404);
    exit('Preview não encontrado.');
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

if ($operationalPreview) {
    $pageDefinition = [
        'title' => $page['label'],
        'description' => 'Pré-visualização isolada do shell operacional com o menu canônico do ambiente.',
    ];
    require dirname(__DIR__) . '/frontend/layouts/head.php';
    ?>
    <body data-page="<?= sigas_frontend_escape($pageKey) ?>" data-module="<?= sigas_frontend_escape($environmentKey) ?>">
        <div class="app-shell module-shell module-shell--<?= sigas_frontend_escape($environment['theme']) ?>" data-module-shell data-menu-environment="<?= sigas_frontend_escape($environmentKey) ?>">
            <?php $menuSurface = 'sidebar'; $menuPageKey = $pageKey; require dirname(__DIR__) . '/' . $environment['menu']; ?>
            <div class="app-main module-main">
                <?php require dirname(__DIR__) . '/frontend/layouts/module-topbar.php'; ?>
                <main class="app-content">
                    <header class="page-header"><div><div class="eyebrow">Fluxo operacional</div><h1><?= sigas_frontend_escape($page['label']) ?></h1><p>O conteúdo funcional permanece nesta área, usando a mesma navegação das páginas do setor ou módulo.</p></div></header>
                    <section class="content-card p-4"><h2 class="fs-5">Conteúdo da página</h2><p class="text-secondary mb-0">Apenas o shell é demonstrado neste preview; formulários, tabelas e operações reais não foram executados.</p></section>
                </main>
                <?php require dirname(__DIR__) . '/frontend/layouts/footer.php'; ?>
            </div>
            <?php $menuSurface = 'mobile'; require dirname(__DIR__) . '/' . $environment['menu']; ?>
        </div>
        <?= App\Core\PageContext::script($frontendContext) ?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="assets/js/app.js"></script>
        <script src="assets/js/module-navigation.js"></script>
    </body>
    </html>
    <?php
    exit;
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

$extraStyles = $environmentKey === 'primeiro-emprego' ? ['frontend/modules/primeiro-emprego/primeiro-emprego-ui-20260819.css'] : [];
$extraScripts = $environmentKey === 'primeiro-emprego' ? ['frontend/modules/primeiro-emprego/primeiro-emprego-ui-20260819.js'] : [];

require dirname(__DIR__) . '/frontend/layouts/module-layout.php';
