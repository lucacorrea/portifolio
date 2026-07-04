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
    <meta name="description" content="Gestão de usuários do SIGAS Coari.">
    <title>SIGAS Coari — Usuários</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="usuarios">
    <div class="app-shell">
        <aside class="app-sidebar" id="appSidebar" aria-label="Menu principal"></aside>
        <div class="app-main">
            <header class="app-topbar" id="appTopbar"></header>
            <main class="app-content">
                <header class="page-header"><div><div class="eyebrow"><i class="bi bi-person-gear"></i>Administração</div><h1>Usuários</h1><p>Contas de acesso, perfis, unidades, permissões e auditoria de uso do sistema.</p></div><div class="page-actions"><button class="btn btn-light" type="button" data-demo-action="exportar usuários"><i class="bi bi-download"></i>Exportar</button><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#userModal"><i class="bi bi-person-plus"></i>Novo usuário</button></div></header>
                <section class="dashboard-grid">                    <article class="stat-card stat-card-featured col-span-3">
                        <div class="stat-card-head"><span class="stat-card-title">Usuários ativos</span><span class="stat-icon"><i class="bi bi-people"></i></span></div>
                        <strong class="stat-value">86</strong>
                        <div class="stat-footer"><span class="stat-trend positive"><i class="bi bi-arrow-up-right"></i>4 novos no mês</span><button class="card-link border-0 bg-transparent" type="button" data-demo-action="abrir indicador usuários ativos">Detalhes</button></div>
                    </article>
                    <article class="stat-card col-span-3">
                        <div class="stat-card-head"><span class="stat-card-title">Administradores</span><span class="stat-icon"><i class="bi bi-shield-lock"></i></span></div>
                        <strong class="stat-value">6</strong>
                        <div class="stat-footer"><span class="stat-trend positive"><i class="bi bi-arrow-up-right"></i>acesso ampliado</span><button class="card-link border-0 bg-transparent" type="button" data-demo-action="abrir indicador administradores">Detalhes</button></div>
                    </article>
                    <article class="stat-card col-span-3">
                        <div class="stat-card-head"><span class="stat-card-title">Sessões hoje</span><span class="stat-icon"><i class="bi bi-activity"></i></span></div>
                        <strong class="stat-value">54</strong>
                        <div class="stat-footer"><span class="stat-trend positive"><i class="bi bi-arrow-up-right"></i>última hora: 31</span><button class="card-link border-0 bg-transparent" type="button" data-demo-action="abrir indicador sessões hoje">Detalhes</button></div>
                    </article>
                    <article class="stat-card col-span-3">
                        <div class="stat-card-head"><span class="stat-card-title">Acessos bloqueados</span><span class="stat-icon"><i class="bi bi-person-lock"></i></span></div>
                        <strong class="stat-value">3</strong>
                        <div class="stat-footer"><span class="stat-trend warning"><i class="bi bi-arrow-up-right"></i>revisar permissões</span><button class="card-link border-0 bg-transparent" type="button" data-demo-action="abrir indicador acessos bloqueados">Detalhes</button></div>
                    </article>
                    <article class="content-card col-span-8"><div class="card-heading"><div><div class="card-kicker">Segurança</div><h2>Acessos nos últimos 14 dias</h2><p>Sessões autenticadas e tentativas recusadas.</p></div><span class="status-badge status-success"><i class="bi bi-shield-check"></i>Sem anomalias</span></div><div class="chart-wrap"><canvas id="userAccessChart" data-chart="line" data-chart-labels='["05", "06", "07", "08", "09", "10", "11", "12", "13", "14", "15", "16", "17", "18"]' data-chart-values='[42, 47, 51, 49, 38, 27, 45, 53, 56, 48, 31, 46, 52, 54]' data-chart-label="Sessões" aria-label="Sessões"></canvas></div></article><article class="content-card col-span-4"><div class="card-heading"><div><div class="card-kicker">Perfis</div><h2>Distribuição de usuários</h2></div></div><div class="chart-wrap"><canvas id="userRolesChart" data-chart="doughnut" data-chart-labels='["Administrador", "Gestor", "Técnico", "Atendente", "Leitura"]' data-chart-values='[6, 12, 34, 24, 10]' data-chart-label="Usuários" aria-label="Usuários"></canvas></div></article>
                    <article class="content-card table-card col-span-12"><div class="table-toolbar"><div><h2 class="fs-6 mb-1">Contas cadastradas</h2><div class="table-toolbar-info">Exibindo 6 de 86 usuários demonstrativos</div></div><div class="d-flex gap-2"><button class="btn btn-light btn-sm" type="button" data-confirm-action="Bloquear os usuários selecionados?"><i class="bi bi-person-lock"></i>Bloquear</button><button class="btn btn-light btn-sm" type="button" data-demo-action="revisar permissões"><i class="bi bi-shield-check"></i>Permissões</button></div></div><div class="table-responsive"><table class="data-table"><thead><tr><th>Usuário</th><th>CPF</th><th>Perfil</th><th>Unidade</th><th>Último acesso</th><th>Situação</th><th>Ações</th></tr></thead><tbody><tr><td><div class="record-person"><span class="mini-avatar">MO</span><div><strong>Maria Oliveira</strong><span>CPF: ***.456.789-** · maria.oliveira@semas.gov.br</span></div></div></td><td>***.456.789-**</td><td>Assistente Social</td><td>SEMAS Sede</td><td>Hoje, 17:42</td><td><span class="status-badge status-success"><i class="bi bi-check-circle"></i>Ativo</span></td><td><button class="btn btn-light btn-sm" type="button" data-demo-action="editar usuário"><i class="bi bi-pencil"></i>Editar</button></td></tr><tr><td><div class="record-person"><span class="mini-avatar">RS</span><div><strong>Rafael Santos</strong><span>CPF: ***.267.431-** · rafael.santos@semas.gov.br</span></div></div></td><td>***.267.431-**</td><td>Técnico</td><td>CRAS 1</td><td>Hoje, 16:58</td><td><span class="status-badge status-success"><i class="bi bi-check-circle"></i>Ativo</span></td><td><button class="btn btn-light btn-sm" type="button" data-demo-action="editar usuário"><i class="bi bi-pencil"></i>Editar</button></td></tr><tr><td><div class="record-person"><span class="mini-avatar">CL</span><div><strong>Cláudia Lima</strong><span>CPF: ***.914.208-** · claudia.lima@semas.gov.br</span></div></div></td><td>***.914.208-**</td><td>Gestora</td><td>CREAS</td><td>Hoje, 16:31</td><td><span class="status-badge status-success"><i class="bi bi-check-circle"></i>Ativo</span></td><td><button class="btn btn-light btn-sm" type="button" data-demo-action="editar usuário"><i class="bi bi-pencil"></i>Editar</button></td></tr><tr><td><div class="record-person"><span class="mini-avatar">PS</span><div><strong>Paula Souza</strong><span>CPF: ***.102.654-** · paula.souza@semas.gov.br</span></div></div></td><td>***.102.654-**</td><td>Atendente</td><td>Casa do Cidadão</td><td>Ontem, 17:04</td><td><span class="status-badge status-success"><i class="bi bi-check-circle"></i>Ativo</span></td><td><button class="btn btn-light btn-sm" type="button" data-demo-action="editar usuário"><i class="bi bi-pencil"></i>Editar</button></td></tr><tr><td><div class="record-person"><span class="mini-avatar">MS</span><div><strong>Marcos Silva</strong><span>CPF: ***.785.312-** · marcos.silva@semas.gov.br</span></div></div></td><td>***.785.312-**</td><td>Leitura</td><td>CRAS 2</td><td>16/06/2026</td><td><span class="status-badge status-danger"><i class="bi bi-lock"></i>Bloqueado</span></td><td><button class="btn btn-light btn-sm" type="button" data-confirm-action="Reativar este usuário?"><i class="bi bi-unlock"></i>Reativar</button></td></tr><tr><td><div class="record-person"><span class="mini-avatar">JC</span><div><strong>Juliana Costa</strong><span>CPF: ***.331.547-** · juliana.costa@semas.gov.br</span></div></div></td><td>***.331.547-**</td><td>Visitadora</td><td>CRAS 1</td><td>15/06/2026</td><td><span class="status-badge status-warning"><i class="bi bi-calendar-x"></i>Férias</span></td><td><button class="btn btn-light btn-sm" type="button" data-demo-action="editar usuário"><i class="bi bi-pencil"></i>Editar</button></td></tr></tbody></table></div></article>
                </section>
            </main>
            <footer class="app-footer"><span>Dados demonstrativos utilizados apenas para prototipação.</span><span>SIGAS Coari — SEMAS Coari/AM</span></footer>
        </div>
        <div id="bottomNavigation"></div>
    </div>
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-person-plus"></i>Administração</div><h2 class="modal-title fs-5" id="userTitle">Novo usuário</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><div class="form-grid"><div class="field-8"><label class="form-label" for="userName">Nome completo</label><input class="form-control" id="userName" required></div><div class="field-4"><label class="form-label" for="userCpf">CPF</label><input class="form-control" id="userCpf" required></div><div class="field-6"><label class="form-label" for="userEmail">E-mail institucional</label><input class="form-control" id="userEmail" type="email" required></div><div class="field-3"><label class="form-label" for="userRole">Perfil</label><select class="form-select" id="userRole" required><option value="">Selecione</option><option>Administrador</option><option>Gestor</option><option>Técnico</option><option>Atendente</option><option>Leitura</option></select></div><div class="field-3"><label class="form-label" for="userUnit">Unidade</label><select class="form-select" id="userUnit" required><option value="">Selecione</option><option>SEMAS Sede</option><option>CRAS 1</option><option>CRAS 2</option><option>CREAS</option><option>Casa do Cidadão</option></select></div></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="button" data-submit-demo="Usuário criado e convite demonstrativo enviado.">Criar usuário</button></div></div></div></div>

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

