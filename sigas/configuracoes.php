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
    <meta name="description" content="Configurações do SIGAS Coari.">
    <title>SIGAS Coari — Configurações</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="configuracoes">
    <div class="app-shell">
        <aside class="app-sidebar" id="appSidebar" aria-label="Menu principal"></aside>
        <div class="app-main">
            <header class="app-topbar" id="appTopbar"></header>
            <main class="app-content">
                <header class="page-header"><div><div class="eyebrow"><i class="bi bi-sliders"></i>Administração</div><h1>Configurações</h1><p>Parâmetros institucionais, fluxos, segurança, notificações e preferências do sistema.</p></div><div class="page-actions"><button class="btn btn-light" type="button" data-demo-action="restaurar configurações"><i class="bi bi-arrow-counterclockwise"></i>Restaurar</button><button class="btn btn-primary" type="button" data-settings-save><i class="bi bi-check2"></i>Salvar alterações</button></div></header>
                <section class="settings-layout" data-tabs-group><nav class="settings-nav" aria-label="Categorias de configuração"><button class="active" type="button" data-tab-target="institution"><i class="bi bi-building"></i><span>Instituição</span></button><button type="button" data-tab-target="flows"><i class="bi bi-diagram-3"></i><span>Fluxos e prazos</span></button><button type="button" data-tab-target="notifications"><i class="bi bi-bell"></i><span>Notificações</span></button><button type="button" data-tab-target="security"><i class="bi bi-shield-lock"></i><span>Segurança</span></button><button type="button" data-tab-target="integrations"><i class="bi bi-plug"></i><span>Integrações</span></button></nav><div class="settings-panels">
                    <article class="content-card settings-section" data-tab-panel="institution"><div class="card-heading"><div><div class="card-kicker">Identidade institucional</div><h2>Dados da organização</h2><p>Informações exibidas nos cabeçalhos e documentos demonstrativos.</p></div><span class="status-badge status-success"><i class="bi bi-check-circle"></i>Configuração ativa</span></div><div class="form-grid"><div class="field-8"><label class="form-label" for="orgName">Órgão responsável</label><input class="form-control" id="orgName" value="Secretaria Municipal de Assistência Social — SEMAS"></div><div class="field-4"><label class="form-label" for="orgAcronym">Sigla</label><input class="form-control" id="orgAcronym" value="SEMAS"></div><div class="field-6"><label class="form-label" for="orgCity">Município</label><input class="form-control" id="orgCity" value="Coari/AM"></div><div class="field-6"><label class="form-label" for="orgUnit">Unidade padrão</label><select class="form-select" id="orgUnit"><option>SEMAS — Sede Administrativa</option><option>CRAS 1</option><option>CRAS 2</option><option>CREAS</option></select></div><div class="field-6"><label class="form-label" for="orgPhone">Telefone institucional</label><input class="form-control" id="orgPhone" value="(97) 3561-0000"></div><div class="field-6"><label class="form-label" for="orgEmail">E-mail institucional</label><input class="form-control" id="orgEmail" value="semas@coari.am.gov.br"></div></div><hr class="my-4"><div class="setting-row"><div><strong>Exibir aviso de dados demonstrativos</strong><span>Mantém o rodapé de prototipação visível em todas as páginas.</span></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" checked aria-label="Exibir aviso demonstrativo"></div></div><div class="setting-row"><div><strong>Formato de data brasileiro</strong><span>Utiliza DD/MM/AAAA em tabelas e documentos.</span></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" checked aria-label="Formato de data brasileiro"></div></div></article>
                    <article class="content-card settings-section" data-tab-panel="flows" hidden><div class="card-heading"><div><div class="card-kicker">Operação</div><h2>Fluxos e prazos</h2><p>Parâmetros gerais para filas e alertas de atendimento.</p></div></div><div class="setting-row"><div><strong>Prazo padrão de análise</strong><span>Tempo recomendado para resposta de solicitações comuns.</span></div><select class="form-select compact-select"><option>3 dias úteis</option><option>5 dias úteis</option><option>10 dias úteis</option></select></div><div class="setting-row"><div><strong>Alerta de prazo crítico</strong><span>Notificar quando restar o período selecionado.</span></div><select class="form-select compact-select"><option>24 horas</option><option>48 horas</option><option>72 horas</option></select></div><div class="setting-row"><div><strong>Exigir parecer técnico</strong><span>Bloqueia a conclusão sem parecer preenchido.</span></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" checked></div></div></article>
                    <article class="content-card settings-section" data-tab-panel="notifications" hidden><div class="card-heading"><div><div class="card-kicker">Comunicação</div><h2>Notificações</h2><p>Eventos que geram avisos internos para usuários e gestores.</p></div></div><div class="setting-row"><div><strong>Solicitações prioritárias</strong><span>Avisar a unidade e o responsável técnico.</span></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" checked></div></div><div class="setting-row"><div><strong>Pendências documentais</strong><span>Resumo diário das pendências abertas.</span></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" checked></div></div><div class="setting-row"><div><strong>Metas de programas</strong><span>Alertas quando a execução ficar abaixo do planejado.</span></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch"></div></div></article>
                    <article class="content-card settings-section" data-tab-panel="security" hidden><div class="card-heading"><div><div class="card-kicker">Proteção de acesso</div><h2>Segurança</h2><p>Políticas demonstrativas de autenticação e sessão.</p></div><span class="status-badge status-info"><i class="bi bi-shield-check"></i>Recomendado</span></div><div class="setting-row"><div><strong>Autenticação em duas etapas</strong><span>Exigir código adicional para perfis administrativos.</span></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" checked></div></div><div class="setting-row"><div><strong>Expiração da sessão</strong><span>Encerrar sessões sem atividade.</span></div><select class="form-select compact-select"><option>30 minutos</option><option>1 hora</option><option>4 horas</option></select></div><div class="setting-row"><div><strong>Registro de auditoria</strong><span>Rastrear alterações em cadastros e benefícios.</span></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" checked disabled></div></div><div class="alert-soft warning mt-3"><i class="bi bi-lock"></i><div>Algumas políticas são protegidas e exigem perfil de administrador geral.</div></div></article>
                    <article class="content-card settings-section" data-tab-panel="integrations" hidden>
                        <div class="card-heading"><div><div class="card-kicker">Integração controlada</div><h2>SEMTH / SEMAS legado</h2><p>Parâmetros de consulta externa utilizados exclusivamente para prevenção de duplicidade.</p></div><span class="status-badge status-info"><i class="bi bi-database-lock"></i>Somente leitura</span></div>
                        <div class="integration-config-grid">
                            <div class="integration-config-card"><span>Modo de acesso</span><strong>SELECT por views autorizadas</strong><small>Sem INSERT, UPDATE, DELETE, ALTER ou DROP.</small></div>
                            <div class="integration-config-card"><span>Chave de comparação</span><strong>CPF normalizado</strong><small>Nome, data de nascimento e NIS servem como apoio.</small></div>
                            <div class="integration-config-card"><span>Comportamento em duplicidade</span><strong>Bloquear e abrir existente</strong><small>Nenhum cadastro paralelo é criado automaticamente.</small></div>
                            <div class="integration-config-card"><span>Atualização cruzada</span><strong>Desativada</strong><small>Cada sistema permanece proprietário dos próprios dados.</small></div>
                        </div>
                        <div class="setting-row"><div><strong>Consulta preventiva obrigatória</strong><span>Exigir comparação nas duas bases antes de liberar um novo cadastro.</span></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" checked disabled></div></div>
                        <div class="setting-row"><div><strong>Registrar consultas na auditoria</strong><span>Armazenar usuário, horário, CPF mascarado, resultado e finalidade.</span></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" checked></div></div>
                        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mt-4"><span class="integration-health" data-integration-health><i class="bi bi-check-circle-fill"></i>Conectado em modo somente leitura</span><div class="d-flex gap-2"><button class="btn btn-light" type="button" data-integration-test><i class="bi bi-activity"></i>Testar leitura</button><a class="btn btn-primary" href="integracao-semth.php"><i class="bi bi-diagram-3"></i>Abrir painel</a></div></div>
                    </article>
                </div></section>
            </main>
            <footer class="app-footer"><span>Dados demonstrativos utilizados apenas para prototipação.</span><span>SIGAS Coari — SEMAS Coari/AM</span></footer>
        </div>
        <div id="bottomNavigation"></div>
    </div>


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

