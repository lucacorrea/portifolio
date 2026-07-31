<?php

declare(strict_types=1);

use App\Config\ModuleRegistry;
use App\Core\PageContext;

require_once __DIR__ . '/bootstrap.php';

$environment = is_string($_GET['ambiente'] ?? null) ? $_GET['ambiente'] : '';
$page = is_string($_GET['pagina'] ?? null) ? $_GET['pagina'] : 'painel';
$sector = ModuleRegistry::find($environment);

if ($sector === null || ($sector['kind'] ?? null) !== 'sector') {
    http_response_code(404);
    exit('Setor não encontrado.');
}

$items = $sector['items'];
$current = null;
foreach ($items as $item) {
    if ($item['page'] === $page) {
        $current = $item;
        break;
    }
}

if ($current === null) {
    http_response_code(404);
    exit('Página não encontrada.');
}

$context = PageContext::requireAuthenticatedFrontendContext();
$context['module'] = $environment;
$context['page'] = $page;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($sector['name'], ENT_QUOTES, 'UTF-8') ?>">
    <title>SIGAS Coari — <?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>" data-module="<?= htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') ?>">
    <div class="app-shell">
        <aside class="app-sidebar" id="appSidebar" aria-label="Menu do setor"></aside>
        <div class="app-main">
            <header class="app-topbar" id="appTopbar"></header>
            <main class="app-content">
                <header class="page-header">
                    <div><div class="eyebrow"><i class="bi bi-<?= htmlspecialchars($sector['icon'], ENT_QUOTES, 'UTF-8') ?>"></i><?= htmlspecialchars($sector['name'], ENT_QUOTES, 'UTF-8') ?></div><h1><?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?></h1><p>Estrutura visual preparada para os fluxos e indicadores deste setor.</p></div>
                    <div class="page-actions"><a class="btn btn-light" href="portal.php"><i class="bi bi-grid"></i>Trocar setor ou módulo</a></div>
                </header>
                <section class="compact-stats" aria-label="Indicadores do setor"><div class="compact-stat"><span>Em acompanhamento</span><strong>—</strong><small>Indicador a configurar</small></div><div class="compact-stat"><span>Demandas abertas</span><strong>—</strong><small>Indicador a configurar</small></div><div class="compact-stat"><span>Atualização</span><strong>—</strong><small>Sem fonte conectada</small></div></section>
                <section class="content-card"><div class="card-heading"><div><div class="card-kicker">Estrutura inicial</div><h2><?= htmlspecialchars($current['label'], ENT_QUOTES, 'UTF-8') ?></h2><p>Esta página foi organizada no ambiente de <?= htmlspecialchars($sector['name'], ENT_QUOTES, 'UTF-8') ?>. A implementação dos fluxos e permissões será feita no back-end em etapa própria.</p></div></div></section>
            </main>
            <footer class="app-footer"><span>SIGAS Coari — SEMAS Coari/AM</span><span>Ambiente em estruturação</span></footer>
        </div>
        <div id="bottomNavigation"></div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?= PageContext::script($context) ?>
    <script src="assets/js/app.js"></script>
</body>
</html>
