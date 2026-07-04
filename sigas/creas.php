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
    <meta name="description" content="Centro de Referência Especializado de Assistência Social">
    <title>SIGAS Coari — CREAS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="creas">
    <div class="app-shell">
        <aside class="app-sidebar" id="appSidebar" aria-label="Menu principal"></aside>
        <div class="app-main">
            <header class="app-topbar" id="appTopbar"></header>
            <main class="app-content">
                <section class="unit-banner"><div class="unit-banner-main"><span class="unit-symbol"><i class="bi bi-building"></i></span><div><div class="eyebrow mb-1"><i class="bi bi-geo-alt"></i>Unidade socioassistencial</div><h1>CREAS</h1><p>Centro de Referência Especializado de Assistência Social</p></div></div><div class="unit-banner-actions"><button class="btn btn-light" type="button" data-demo-action="ver localização"><i class="bi bi-map"></i>Localização</button><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#newServiceModal"><i class="bi bi-plus-lg"></i>Novo atendimento</button></div></section>
                <section class="dashboard-grid" aria-label="Indicadores da unidade">
                                        <article class="stat-card stat-card-featured col-span-3">
                        <div class="stat-card-head"><span class="stat-card-title">Famílias referenciadas</span><span class="stat-icon"><i class="bi bi-house-heart"></i></span></div>
                        <strong class="stat-value">486</strong>
                        <div class="stat-footer"><span class="stat-trend positive"><i class="bi bi-arrow-up-right"></i>território ativo</span><button class="card-link border-0 bg-transparent" type="button" data-demo-action="abrir indicador famílias referenciadas">Detalhes</button></div>
                    </article>
                    <article class="stat-card col-span-3">
                        <div class="stat-card-head"><span class="stat-card-title">Atendimentos hoje</span><span class="stat-icon"><i class="bi bi-clipboard2-pulse"></i></span></div>
                        <strong class="stat-value">27</strong>
                        <div class="stat-footer"><span class="stat-trend positive"><i class="bi bi-arrow-up-right"></i>fluxo normal</span><button class="card-link border-0 bg-transparent" type="button" data-demo-action="abrir indicador atendimentos hoje">Detalhes</button></div>
                    </article>
                    <article class="stat-card col-span-3">
                        <div class="stat-card-head"><span class="stat-card-title">Visitas programadas</span><span class="stat-icon"><i class="bi bi-house-door"></i></span></div>
                        <strong class="stat-value">14</strong>
                        <div class="stat-footer"><span class="stat-trend positive"><i class="bi bi-arrow-up-right"></i>esta semana</span><button class="card-link border-0 bg-transparent" type="button" data-demo-action="abrir indicador visitas programadas">Detalhes</button></div>
                    </article>
                    <article class="stat-card col-span-3">
                        <div class="stat-card-head"><span class="stat-card-title">Pendências técnicas</span><span class="stat-icon"><i class="bi bi-exclamation-circle"></i></span></div>
                        <strong class="stat-value">7</strong>
                        <div class="stat-footer"><span class="stat-trend warning"><i class="bi bi-arrow-up-right"></i>requerem atenção</span><button class="card-link border-0 bg-transparent" type="button" data-demo-action="abrir indicador pendências técnicas">Detalhes</button></div>
                    </article>
                    <article class="content-card col-span-8"><div class="card-heading"><div><div class="card-kicker">Produção da unidade</div><h2>Atendimentos por semana</h2><p>Histórico das últimas oito semanas.</p></div><span class="status-badge status-success"><i class="bi bi-arrow-repeat"></i>Atualizado hoje</span></div><div class="chart-wrap"><canvas id="creasProduction" data-chart="bar" data-chart-labels='["S1", "S2", "S3", "S4", "S5", "S6", "S7", "S8"]' data-chart-values='[246, 271, 254, 289, 278, 304, 296, 318]' data-chart-label="Atendimentos" aria-label="Atendimentos"></canvas></div></article>
                    <article class="content-card col-span-4"><div class="card-heading"><div><div class="card-kicker">Unidade</div><h2>Informações operacionais</h2></div></div><div class="reference-card"><span>Território de abrangência</span><strong>Atendimento especializado municipal e articulação da rede de proteção</strong></div><div class="reference-card"><span>Coordenação</span><strong>Cláudia Lima</strong></div><div class="reference-card"><span>Horário</span><strong>Segunda a sexta, 08h às 17h</strong></div><div class="reference-card"><span>Contato institucional</span><strong>(97) 3561-0000 · unidade@sigas.coari.am.gov.br</strong></div></article>
                    <article class="content-card col-span-5"><div class="card-heading"><div><div class="card-kicker">Agenda</div><h2>Atividades programadas</h2></div><button class="btn btn-light btn-sm" type="button" data-demo-action="abrir agenda"><i class="bi bi-calendar3"></i>Agenda completa</button></div><div class="agenda-list"><div class="agenda-item"><div class="agenda-date"><strong>22</strong><span>jun</span></div><div class="item-main"><strong>Grupo de acompanhamento familiar</strong><span>09h · Sala multiuso</span></div><span class="status-badge status-success"><i class="bi bi-check-circle"></i>Confirmado</span></div><div class="agenda-item"><div class="agenda-date"><strong>23</strong><span>jun</span></div><div class="item-main"><strong>Visitas domiciliares — rota urbana</strong><span>08h · Equipe PAIF</span></div><span class="status-badge status-info"><i class="bi bi-people"></i>12 famílias</span></div><div class="agenda-item"><div class="agenda-date"><strong>24</strong><span>jun</span></div><div class="item-main"><strong>Reunião de rede</strong><span>14h · Coordenação</span></div><span class="status-badge status-neutral"><i class="bi bi-building"></i>Interno</span></div></div></article>
                    <article class="content-card col-span-7"><div class="card-heading"><div><div class="card-kicker">Equipe de referência</div><h2>Profissionais da unidade</h2></div><button class="btn btn-light btn-sm" type="button" data-demo-action="gerenciar equipe"><i class="bi bi-people"></i>Equipe</button></div><div class="team-grid"><div class="team-member"><span class="mini-avatar">CL</span><div><strong>Cláudia Lima</strong><span>Coordenação</span></div><span class="status-badge status-success"><i class="bi bi-circle-fill"></i>Disponível</span></div><div class="team-member"><span class="mini-avatar">MO</span><div><strong>Maria Oliveira</strong><span>Assistente Social</span></div><span class="status-badge status-info"><i class="bi bi-circle-fill"></i>Em atendimento</span></div><div class="team-member"><span class="mini-avatar">RS</span><div><strong>Rafael Santos</strong><span>Psicólogo</span></div><span class="status-badge status-success"><i class="bi bi-circle-fill"></i>Disponível</span></div><div class="team-member"><span class="mini-avatar">JL</span><div><strong>Juliana Lima</strong><span>Orientadora social</span></div><span class="status-badge status-warning"><i class="bi bi-circle-fill"></i>Visita externa</span></div></div></article>
                    <article class="content-card table-card col-span-12"><div class="table-toolbar"><div><h2 class="fs-6 mb-1">Fila atual de atendimento</h2><div class="table-toolbar-info">Registros demonstrativos da unidade</div></div><button class="btn btn-light btn-sm" type="button" data-demo-action="chamar próximo"><i class="bi bi-megaphone"></i>Chamar próximo</button></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Senha</th><th>Pessoa</th><th>Serviço</th><th>Chegada</th><th>Prioridade</th><th>Situação</th><th>Ações</th></tr></thead><tbody><tr><td><strong>A-024</strong></td><td><div class="record-person"><span class="mini-avatar">MD</span><div><strong>Maria da Silva</strong><span>CPF: ***.456.789-**</span></div></div></td><td>Atualização cadastral</td><td>08:42</td><td><span class="status-badge status-neutral"><i class="bi bi-dash-circle"></i>Normal</span></td><td><span class="status-badge status-warning"><i class="bi bi-clock"></i>Aguardando</span></td><td><button class="btn btn-primary btn-sm" type="button" data-demo-action="iniciar atendimento">Atender</button></td></tr><tr><td><strong>P-006</strong></td><td><div class="record-person"><span class="mini-avatar">AS</span><div><strong>Ana Souza</strong><span>CPF: ***.914.208-**</span></div></div></td><td>Avaliação social</td><td>08:55</td><td><span class="status-badge status-danger"><i class="bi bi-exclamation-triangle"></i>Prioritária</span></td><td><span class="status-badge status-warning"><i class="bi bi-clock"></i>Aguardando</span></td><td><button class="btn btn-primary btn-sm" type="button" data-demo-action="iniciar atendimento">Atender</button></td></tr><tr><td><strong>A-025</strong></td><td><div class="record-person"><span class="mini-avatar">CO</span><div><strong>Carlos Oliveira</strong><span>CPF: ***.102.654-**</span></div></div></td><td>Orientação de benefício</td><td>09:03</td><td><span class="status-badge status-neutral"><i class="bi bi-dash-circle"></i>Normal</span></td><td><span class="status-badge status-info"><i class="bi bi-clipboard-check"></i>Triagem</span></td><td><button class="btn btn-light btn-sm" type="button" data-demo-action="abrir triagem">Abrir</button></td></tr></tbody></table></div></article>
                </section>
            </main>
            <footer class="app-footer"><span>Dados demonstrativos utilizados apenas para prototipação.</span><span>SIGAS Coari — SEMAS Coari/AM</span></footer>
        </div>
        <div id="bottomNavigation"></div>
    </div>
<div class="modal fade" id="newServiceModal" tabindex="-1" aria-labelledby="newServiceTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-clipboard2-plus"></i>CREAS</div><h2 class="modal-title fs-5" id="newServiceTitle">Novo atendimento</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label" for="unitPerson">Pessoa atendida</label><input class="form-control" id="unitPerson" required></div><div class="mb-3"><label class="form-label" for="unitService">Serviço</label><select class="form-select" id="unitService" required><option value="">Selecione</option><option>Acolhida</option><option>Atualização cadastral</option><option>Avaliação social</option><option>Orientação</option></select></div><div><label class="form-label" for="unitNotes">Observações iniciais</label><textarea class="form-control" id="unitNotes" rows="3"></textarea></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="button" data-submit-demo="Atendimento iniciado na unidade.">Iniciar</button></div></div></div></div>

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

