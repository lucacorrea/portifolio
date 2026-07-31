<?php

declare(strict_types=1);

use App\Core\PageContext;

require_once dirname(__DIR__) . '/bootstrap.php';

/** @param array<string, mixed> $extra */
function primeiro_emprego_page(string $page, string $title, string $description, string $body, array $extra = []): void
{
    $context = PageContext::requireAuthenticatedFrontendContext();
    $context['urls']['dashboard'] = '../dashboard.php';
    $context['urls']['logout'] = '../sair.php';
    $context['module'] = 'primeiroEmprego';
    $context['page'] = $page;
    $context['modules'] = ['primeiroEmprego' => ['allowed' => true]];
    $context['moduleExtra'] = $extra;
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <title>SIGAS Coari — <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/module-navigation.css" rel="stylesheet">
    <link href="../assets/css/primeiro-emprego.css" rel="stylesheet">
</head>
<body data-page="<?= htmlspecialchars($page, ENT_QUOTES, 'UTF-8') ?>" data-module="primeiro-emprego" data-portal-url="../portal.php">
    <div id="moduleRoot">
        <div class="pe-page-content">
                <header class="page-header pe-page-header">
                    <div><div class="eyebrow"><i class="bi bi-briefcase-fill"></i>Coari Meu Primeiro Emprego</div><h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1><p><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p></div>
                    <div class="page-actions"><a class="btn btn-light" href="../portal.php"><i class="bi bi-grid-1x2"></i><span class="optional">Trocar módulo</span></a></div>
                </header>
                <div class="pe-demo-notice" role="status"><i class="bi bi-info-circle"></i><span>Ambiente visual demonstrativo. Ações e dados ainda não são persistidos.</span></div>
                <?= $body ?>
            <footer class="app-footer"><span>Dados demonstrativos para prototipação.</span><span>SIGAS Coari — Coari Meu Primeiro Emprego</span></footer>
        </div>
    </div>
    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <?= PageContext::script($context) ?>
    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/module-navigation.js"></script>
    <script src="../assets/js/primeiro-emprego.js"></script>
</body>
</html>
<?php
}

/** @param list<string> $columns @param list<list<string>> $rows */
function primeiro_emprego_list(string $page, string $title, string $description, array $columns, array $rows, string $createLabel = 'Novo registro'): void
{
    ob_start(); ?>
<section class="pe-stat-grid pe-stat-grid-small"><article><span>Registros ativos</span><strong>124</strong><small>Atualizado hoje</small></article><article><span>Em acompanhamento</span><strong>48</strong><small>Requer atenção</small></article><article><span>Concluídos no mês</span><strong>58</strong><small>Dados demonstrativos</small></article></section>
<section class="content-card pe-list-card"><div class="pe-list-toolbar"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" type="search" placeholder="Pesquisar nesta lista" data-pe-search aria-label="Pesquisar nesta lista"></div><button class="btn btn-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#peFilters"><i class="bi bi-funnel"></i>Filtros</button><button class="btn btn-primary" type="button" data-demo-action="<?= htmlspecialchars($createLabel, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-plus-lg"></i><?= htmlspecialchars($createLabel, ENT_QUOTES, 'UTF-8') ?></button></div>
<div class="table-responsive pe-desktop-table"><table class="table align-middle"><thead><tr><?php foreach ($columns as $column): ?><th><?= htmlspecialchars($column, ENT_QUOTES, 'UTF-8') ?></th><?php endforeach; ?><th class="text-end">Ações</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><?php foreach ($row as $index => $value): ?><td><?= $index === 0 ? '<strong>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</strong>' : htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></td><?php endforeach; ?><td class="text-end"><button class="btn btn-light btn-sm" type="button" data-pe-detail="<?= htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-three-dots"></i><span class="visually-hidden">Abrir ações</span></button></td></tr><?php endforeach; ?></tbody></table></div>
<div class="pe-mobile-cards"><?php foreach ($rows as $row): ?><article data-pe-row><strong><?= htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8') ?></strong><p><?= htmlspecialchars(implode(' · ', array_slice($row, 1)), ENT_QUOTES, 'UTF-8') ?></p><button class="btn btn-light btn-sm" type="button" data-pe-detail="<?= htmlspecialchars($row[0], ENT_QUOTES, 'UTF-8') ?>">Ver detalhes</button></article><?php endforeach; ?></div><div class="state-panel" data-pe-empty><i class="bi bi-search"></i><h3>Nenhum resultado encontrado</h3><p>Altere os filtros ou a pesquisa para continuar.</p></div></section>
<div class="offcanvas offcanvas-end" tabindex="-1" id="peFilters" aria-labelledby="peFiltersTitle"><div class="offcanvas-header"><h2 class="fs-5" id="peFiltersTitle">Filtros</h2><button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Fechar"></button></div><div class="offcanvas-body"><label class="form-label" for="peStatus">Situação</label><select class="form-select mb-3" id="peStatus"><option>Todas</option><option>Ativo</option><option>Em acompanhamento</option><option>Concluído</option></select><label class="form-label" for="peArea">Área de interesse</label><select class="form-select mb-3" id="peArea"><option>Todas</option><option>Comércio</option><option>Administrativo</option><option>Serviços</option></select><button class="btn btn-primary w-100" type="button" data-demo-action="aplicar filtros">Aplicar filtros</button></div></div>
<div class="modal fade" id="peDetailModal" tabindex="-1" aria-labelledby="peDetailTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow"><i class="bi bi-eye"></i>Detalhes demonstrativos</div><h2 class="modal-title fs-5" id="peDetailTitle">Registro</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><p data-pe-detail-text>Informações demonstrativas do registro selecionado.</p></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Fechar</button></div></div></div></div>
<?php $body = ob_get_clean(); primeiro_emprego_page($page, $title, $description, $body); }
