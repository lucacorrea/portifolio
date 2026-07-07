<?php

declare(strict_types=1);

use App\Core\PageContext;

require_once __DIR__ . '/bootstrap.php';

$frontendContext = PageContext::requireAuthenticatedFrontendContext();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Central de relatórios do SIGAS Coari.">
    <title>SIGAS Coari — Relatórios</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="relatorios">
    <div class="app-shell">
        <aside class="app-sidebar" id="appSidebar" aria-label="Menu principal"></aside>
        <div class="app-main">
            <header class="app-topbar" id="appTopbar"></header>
            <main class="app-content">
                <header class="page-header"><div><div class="eyebrow"><i class="bi bi-bar-chart"></i>Administração</div><h1>Relatórios</h1><p>Central de indicadores, extrações operacionais e relatórios gerenciais da assistência social.</p></div><div class="page-actions"><button class="btn btn-light" type="button" data-demo-action="agendar relatório"><i class="bi bi-calendar2-plus"></i>Agendar</button><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#reportModal"><i class="bi bi-file-earmark-plus"></i>Novo relatório</button></div></header>
                <section class="report-card-grid" aria-label="Modelos de relatório"><button class="report-option" type="button" data-demo-action="gerar relatório de atendimentos"><span class="report-icon"><i class="bi bi-clipboard2-data"></i></span><strong>Atendimentos</strong><span>Produção por período, unidade e profissional.</span><b>Gerar agora <i class="bi bi-arrow-right"></i></b></button><button class="report-option" type="button" data-demo-action="gerar relatório de famílias"><span class="report-icon"><i class="bi bi-house-heart"></i></span><strong>Famílias e territórios</strong><span>Perfil familiar, vulnerabilidades e cobertura.</span><b>Gerar agora <i class="bi bi-arrow-right"></i></b></button><button class="report-option" type="button" data-demo-action="gerar relatório de benefícios"><span class="report-icon"><i class="bi bi-gift"></i></span><strong>Benefícios</strong><span>Solicitações, concessões e valores demonstrativos.</span><b>Gerar agora <i class="bi bi-arrow-right"></i></b></button><button class="report-option" type="button" data-demo-action="gerar relatório de programas"><span class="report-icon"><i class="bi bi-grid-1x2"></i></span><strong>Programas prioritários</strong><span>Metas, cobertura, entregas e acompanhamento.</span><b>Gerar agora <i class="bi bi-arrow-right"></i></b></button></section>
                <section class="dashboard-grid mt-3"><article class="content-card col-span-8"><div class="card-heading"><div><div class="card-kicker">Série histórica</div><h2>Atendimentos por unidade</h2><p>Comparativo demonstrativo do semestre.</p></div><select class="form-select form-select-sm compact-select" aria-label="Período"><option>Janeiro a junho de 2026</option></select></div><div class="chart-wrap"><canvas id="reportsUnits" data-chart="bar" data-chart-labels='["Jan", "Fev", "Mar", "Abr", "Mai", "Jun"]' data-chart-values='[2140, 2312, 2448, 2571, 2684, 2816]' data-chart-label="CRAS 1" data-chart-values-2='[1890, 2014, 2156, 2240, 2368, 2472]' data-chart-label-2="CRAS 2" aria-label="CRAS 1"></canvas></div></article><article class="content-card col-span-4"><div class="card-heading"><div><div class="card-kicker">Cobertura</div><h2>Famílias por território</h2></div></div><div class="chart-wrap"><canvas id="reportsTerritory" data-chart="doughnut" data-chart-labels='["CRAS 1", "CRAS 2", "CREAS", "Sede"]' data-chart-values='[36, 32, 12, 20]' data-chart-label="Famílias" aria-label="Famílias"></canvas></div></article><article class="content-card table-card col-span-12"><div class="table-toolbar"><div><h2 class="fs-6 mb-1">Relatórios recentes</h2><div class="table-toolbar-info">Arquivos demonstrativos gerados pelos usuários</div></div><button class="btn btn-light btn-sm" type="button" data-demo-action="atualizar histórico"><i class="bi bi-arrow-repeat"></i>Atualizar</button></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Relatório</th><th>Período</th><th>Solicitado por</th><th>Formato</th><th>Gerado em</th><th>Situação</th><th>Ações</th></tr></thead><tbody><tr><td><strong>Produção mensal consolidada</strong></td><td>Junho de 2026</td><td>Maria Oliveira</td><td>PDF</td><td>18/06/2026 09:42</td><td><span class="status-badge status-success"><i class="bi bi-check-circle"></i>Disponível</span></td><td><button class="btn btn-light btn-sm" type="button" data-demo-action="baixar relatório"><i class="bi bi-download"></i>Baixar</button></td></tr><tr><td><strong>Benefícios por unidade</strong></td><td>Maio de 2026</td><td>Rafael Santos</td><td>XLSX</td><td>17/06/2026 16:10</td><td><span class="status-badge status-success"><i class="bi bi-check-circle"></i>Disponível</span></td><td><button class="btn btn-light btn-sm" type="button" data-demo-action="baixar relatório"><i class="bi bi-download"></i>Baixar</button></td></tr><tr><td><strong>Famílias com revisão pendente</strong></td><td>Próximos 90 dias</td><td>Cláudia Lima</td><td>PDF</td><td>17/06/2026 14:26</td><td><span class="status-badge status-info"><i class="bi bi-arrow-repeat"></i>Processando</span></td><td><button class="btn btn-light btn-sm" type="button" disabled><i class="bi bi-hourglass"></i>Aguardar</button></td></tr></tbody></table></div></article></section>
            </main>
            <footer class="app-footer"><span>Dados demonstrativos utilizados apenas para prototipação.</span><span>SIGAS Coari — SEMAS Coari/AM</span></footer>
        </div>
        <div id="bottomNavigation"></div>
    </div>
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-file-earmark-bar-graph"></i>Relatórios</div><h2 class="modal-title fs-5" id="reportTitle">Gerar novo relatório</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><div class="form-grid"><div class="field-6"><label class="form-label" for="reportType">Modelo</label><select class="form-select" id="reportType" required><option value="">Selecione</option><option>Atendimentos</option><option>Famílias e territórios</option><option>Benefícios</option><option>Programas prioritários</option></select></div><div class="field-3"><label class="form-label" for="reportStart">Data inicial</label><input class="form-control" id="reportStart" type="date" required></div><div class="field-3"><label class="form-label" for="reportEnd">Data final</label><input class="form-control" id="reportEnd" type="date" required></div><div class="field-6"><label class="form-label" for="reportUnit">Unidade</label><select class="form-select" id="reportUnit"><option>Todas as unidades</option><option>CRAS 1</option><option>CRAS 2</option><option>CREAS</option></select></div><div class="field-6"><label class="form-label" for="reportFormat">Formato</label><select class="form-select" id="reportFormat"><option>PDF</option><option>XLSX</option><option>CSV</option></select></div></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="button" data-submit-demo="Relatório gerado e adicionado ao histórico."><i class="bi bi-gear"></i>Gerar relatório</button></div></div></div></div>

    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content"><div class="modal-body text-center p-4"><span class="notice-icon warning mx-auto mb-3"><i class="bi bi-exclamation-triangle"></i></span><h2 class="fs-5" id="confirmationTitle">Confirmar ação</h2><p class="text-secondary small" id="confirmationText">Deseja continuar?</p><div class="d-grid gap-2 mt-4"><button class="btn btn-primary" id="confirmActionButton" type="button">Confirmar</button><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button></div></div></div></div>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="assets/js/integration-demo.js"></script>
    <?= PageContext::script($frontendContext) ?>
    <script src="assets/js/app.js"></script>
</body>
</html>

