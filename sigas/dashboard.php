<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\SectorRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;

require_once __DIR__ . '/bootstrap.php';

$pdo = Database::connection();
$userRepository = new UserRepository($pdo);
$sessionRepository = new UserSessionRepository($pdo);
$accessLevelRepository = new AccessLevelRepository($pdo);
$auditService = new AuditService(new AuditLogRepository($pdo));
$authService = new AuthService($userRepository, $sessionRepository, $accessLevelRepository, $auditService);
$user = $authService->requireUser();
$level = $user->nivelId === null ? null : $accessLevelRepository->findById($user->nivelId);
$sector = $user->setorId === null ? null : (new SectorRepository($pdo))->findById($user->setorId);

function sigas_initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';

    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= mb_substr($part, 0, 1);
        }

        if (mb_strlen($letters) >= 2) {
            break;
        }
    }

    return mb_strtoupper($letters !== '' ? $letters : 'U');
}

$frontendContext = [
    'user' => [
        'name' => $user->nome,
        'initials' => sigas_initials($user->nome),
        'jobTitle' => $user->cargo ?: ($level?->nome ?? 'Usuário'),
        'sector' => $sector?->nome ?: 'Sem setor',
    ],
    'urls' => [
        'dashboard' => 'dashboard.php',
        'logout' => 'sair.php',
    ],
    'csrf' => [
        'logout' => Csrf::token('logout'),
    ],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Dashboard demonstrativo do SIGAS Coari.">
    <title>SIGAS Coari — Visão Geral</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="dashboard">
    <div class="app-shell">
        <aside class="app-sidebar" id="appSidebar" aria-label="Menu principal"></aside>
        <div class="app-main">
            <header class="app-topbar" id="appTopbar"></header>

            <main class="app-content">
                <header class="page-header">
                    <div>
                        <div class="eyebrow"><i class="bi bi-grid-1x2"></i>Painel municipal</div>
                        <h1>Visão Geral</h1>
                        <p>Acompanhe os principais indicadores da assistência social municipal.</p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-light" type="button" data-demo-action="gerar relatório"><i class="bi bi-file-earmark-bar-graph"></i><span class="optional">Gerar relatório</span></button>
                        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#newServiceModal"><i class="bi bi-plus-lg"></i>Novo atendimento</button>
                    </div>
                </header>

                <section class="integration-strip" aria-label="Situação das bases de dados">
                    <div class="integration-strip-main">
                        <span class="integration-strip-icon"><i class="bi bi-database-lock"></i></span>
                        <div><strong>Integração SEMTH em modo somente leitura</strong><span>O SIGAS consulta o sistema atual para evitar cadastros duplicados, sem alterar nenhum dado legado.</span></div>
                    </div>
                    <div class="integration-strip-metrics">
                        <span><strong>15.230</strong> referências consultáveis</span>
                        <span><strong>1.842</strong> registros vinculados</span>
                        <span class="text-danger"><strong>12</strong> divergências para revisar</span>
                    </div>
                    <a class="btn btn-light btn-sm" href="integracao-semth.html"><i class="bi bi-arrow-up-right-square"></i>Acompanhar integração</a>
                </section>

                <section class="dashboard-grid" aria-label="Indicadores principais">
                    <article class="stat-card stat-card-featured col-span-3">
                        <div class="stat-card-head"><span class="stat-card-title">Pessoas cadastradas</span><span class="stat-icon"><i class="bi bi-people"></i></span></div>
                        <strong class="stat-value">18.452</strong>
                        <div class="stat-footer"><span class="stat-trend positive"><i class="bi bi-arrow-up-right"></i>+6,8% no mês</span><a href="pessoas.html">Abrir <i class="bi bi-arrow-right"></i></a></div>
                    </article>
                    <article class="stat-card col-span-3">
                        <div class="stat-card-head"><span class="stat-card-title">Famílias cadastradas</span><span class="stat-icon"><i class="bi bi-house-heart"></i></span></div>
                        <strong class="stat-value">7.830</strong>
                        <div class="stat-footer"><span class="stat-trend positive"><i class="bi bi-plus-circle"></i>+184 neste mês</span><a href="pessoas.html">Abrir <i class="bi bi-arrow-right"></i></a></div>
                    </article>
                    <article class="stat-card col-span-3">
                        <div class="stat-card-head"><span class="stat-card-title">Solicitações abertas</span><span class="stat-icon"><i class="bi bi-inboxes"></i></span></div>
                        <strong class="stat-value">328</strong>
                        <div class="stat-footer"><span class="stat-trend warning"><i class="bi bi-clock"></i>74 aguardando análise</span><a href="solicitacoes.html">Abrir <i class="bi bi-arrow-right"></i></a></div>
                    </article>
                    <article class="stat-card col-span-3">
                        <div class="stat-card-head"><span class="stat-card-title">Atendimentos hoje</span><span class="stat-icon"><i class="bi bi-clipboard2-pulse"></i></span></div>
                        <strong class="stat-value">146</strong>
                        <div class="stat-footer"><span class="stat-trend warning"><i class="bi bi-exclamation-circle"></i>18 prioritários</span><a href="atendimentos.html">Abrir <i class="bi bi-arrow-right"></i></a></div>
                    </article>

                    <article class="program-card col-span-6">
                        <div class="program-top">
                            <div><div class="program-label">Programa prioritário</div><h2 class="program-title">Programa Coari Comida na Mesa</h2><div class="program-subtitle">Operação mensal — Junho de 2026</div></div>
                            <span class="program-icon"><i class="bi bi-basket2"></i></span>
                        </div>
                        <div class="program-body">
                            <div class="program-progress-info"><div><strong>4.736</strong><span> de 5.000 famílias atendidas</span></div><strong>94,7%</strong></div>
                            <div class="program-progress" role="progressbar" aria-label="Progresso da operação mensal" aria-valuenow="94.7" aria-valuemin="0" aria-valuemax="100"><span></span></div>
                            <div class="program-metrics">
                                <div class="program-metric"><strong>264</strong><span>Aguardando retirada</span></div>
                                <div class="program-metric"><strong>18</strong><span>Ocorrências</span></div>
                                <div class="program-metric"><strong>4</strong><span>Polos ativos</span></div>
                            </div>
                        </div>
                        <div class="program-actions"><a class="btn btn-program-light" href="modulo.php"><i class="bi bi-arrow-up-right-square"></i>Abrir programa</a><button class="btn btn-program-outline" type="button" data-bs-toggle="modal" data-bs-target="#deliveryModal"><i class="bi bi-check2-square"></i>Registrar entrega</button></div>
                    </article>

                    <article class="content-card col-span-3">
                        <div class="card-heading"><div><div class="card-kicker">Fila de análise</div><h2>Solicitações prioritárias</h2></div><a class="card-link" href="solicitacoes.html">Ver todas</a></div>
                        <div class="priority-list">
                            <div class="priority-item"><span class="mini-avatar">MS</span><div class="item-main"><strong>Mariana Souza</strong><span>Auxílio-Natalidade</span></div><div class="item-side"><span class="item-priority"><i class="bi bi-flag-fill"></i>Alta</span><br>2h 18min</div></div>
                            <div class="priority-item"><span class="mini-avatar">JP</span><div class="item-main"><strong>José Pereira</strong><span>Benefício eventual</span></div><div class="item-side"><span class="item-priority"><i class="bi bi-flag-fill"></i>Alta</span><br>3h 06min</div></div>
                            <div class="priority-item"><span class="mini-avatar">AL</span><div class="item-main"><strong>Ana Lima</strong><span>Auxílio-Funeral</span></div><div class="item-side"><span class="item-priority"><i class="bi bi-flag-fill"></i>Crítica</span><br>41min</div></div>
                            <div class="priority-item"><span class="mini-avatar">CO</span><div class="item-main"><strong>Carlos Oliveira</strong><span>Atendimento social</span></div><div class="item-side">Média<br>4h 12min</div></div>
                        </div>
                    </article>

                    <article class="content-card col-span-3">
                        <div class="card-heading"><div><div class="card-kicker">Operações</div><h2>Atalhos rápidos</h2></div></div>
                        <div class="quick-actions">
                            <a class="quick-action" href="cadastro-anexo.html"><i class="bi bi-file-earmark-person"></i><span>Novo Cadastro ANEXO</span></a>
                            <a class="quick-action" href="consulta-documento.html"><i class="bi bi-person-bounding-box"></i><span>Consultar documento</span></a>
                            <button class="quick-action" type="button" data-demo-action="nova solicitação"><i class="bi bi-file-earmark-plus"></i><span>Nova solicitação</span></button>
                            <button class="quick-action" type="button" data-bs-toggle="modal" data-bs-target="#deliveryModal"><i class="bi bi-basket2"></i><span>Registrar entrega</span></button>
                            <button class="quick-action" type="button" data-demo-action="agendar visita"><i class="bi bi-calendar2-plus"></i><span>Agendar visita</span></button>
                            <button class="quick-action wide" type="button" data-demo-action="gerar relatório"><i class="bi bi-file-earmark-bar-graph"></i><span>Gerar relatório consolidado</span></button>
                        </div>
                    </article>

                    <article class="content-card col-span-5">
                        <div class="card-heading"><div><div class="card-kicker">Últimos sete dias</div><h2>Atendimentos realizados</h2><p>Volume diário de registros concluídos.</p></div><span class="status-badge status-success"><i class="bi bi-arrow-up-right"></i>12,4%</span></div>
                        <div class="chart-wrap"><canvas id="attendanceChart" aria-label="Gráfico de atendimentos nos últimos sete dias"></canvas></div>
                    </article>

                    <article class="content-card col-span-3">
                        <div class="card-heading"><div><div class="card-kicker">Solicitações</div><h2>Distribuição por status</h2><p>Visão proporcional da fila atual.</p></div></div>
                        <div class="chart-wrap small"><canvas id="statusChart" aria-label="Gráfico de distribuição por status"></canvas></div>
                    </article>

                    <article class="content-card col-span-4">
                        <div class="card-heading"><div><div class="card-kicker">Próximos compromissos</div><h2>Agenda e lembretes</h2></div><button class="btn btn-light btn-sm" type="button" data-demo-action="abrir agenda"><i class="bi bi-calendar3"></i>Agenda</button></div>
                        <div class="agenda-list">
                            <div class="agenda-item"><div class="agenda-date"><strong>22</strong><span>jun</span></div><div class="item-main"><strong>Visita domiciliar</strong><span>Família CM-000141 · 09:00</span></div><i class="bi bi-chevron-right text-secondary"></i></div>
                            <div class="agenda-item"><div class="agenda-date"><strong>22</strong><span>jun</span></div><div class="item-main"><strong>Avaliação social</strong><span>CREAS · 11:30</span></div><i class="bi bi-chevron-right text-secondary"></i></div>
                            <div class="agenda-item"><div class="agenda-date"><strong>23</strong><span>jun</span></div><div class="item-main"><strong>Entrega mensal</strong><span>Polo São Sebastião · 08:00</span></div><i class="bi bi-chevron-right text-secondary"></i></div>
                            <div class="agenda-item"><div class="agenda-date"><strong>24</strong><span>jun</span></div><div class="item-main"><strong>Reunião de equipe</strong><span>Sede administrativa · 15:00</span></div><i class="bi bi-chevron-right text-secondary"></i></div>
                        </div>
                    </article>

                    <article class="content-card col-span-5">
                        <div class="card-heading"><div><div class="card-kicker">Movimentações recentes</div><h2>Últimos atendimentos</h2></div><a class="card-link" href="modulo.php">Abrir histórico</a></div>
                        <div class="activity-list">
                            <div class="activity-item"><span class="mini-avatar">FS</span><div class="item-main"><strong>Francisca Santos</strong><span>Atualização cadastral concluída</span></div><div class="item-side">14:32<br><span class="status-badge status-success">Concluído</span></div></div>
                            <div class="activity-item"><span class="mini-avatar">JL</span><div class="item-main"><strong>João Lima</strong><span>Solicitação de benefício eventual</span></div><div class="item-side">13:48<br><span class="status-badge status-info">Em análise</span></div></div>
                            <div class="activity-item"><span class="mini-avatar">AS</span><div class="item-main"><strong>Ana Souza</strong><span>Entrega do Comida na Mesa</span></div><div class="item-side">12:16<br><span class="status-badge status-success">Recebida</span></div></div>
                            <div class="activity-item"><span class="mini-avatar">MO</span><div class="item-main"><strong>Marcos Oliveira</strong><span>Documento cadastral solicitado</span></div><div class="item-side">11:05<br><span class="status-badge status-warning">Pendente</span></div></div>
                        </div>
                    </article>

                    <article class="content-card col-span-3">
                        <div class="card-heading"><div><div class="card-kicker">Acompanhamento</div><h2>Pendências documentais</h2></div><span class="status-badge status-warning">32 abertas</span></div>
                        <div class="notice-list">
                            <div class="notice-item"><span class="notice-icon warning"><i class="bi bi-file-earmark-x"></i></span><div class="item-main"><strong>Comprovante de residência</strong><span>12 cadastros aguardando envio</span></div></div>
                            <div class="notice-item"><span class="notice-icon warning"><i class="bi bi-person-vcard"></i></span><div class="item-main"><strong>Documento de identificação</strong><span>9 registros incompletos</span></div></div>
                            <div class="notice-item"><span class="notice-icon info"><i class="bi bi-file-earmark-text"></i></span><div class="item-main"><strong>Declaração socioeconômica</strong><span>11 revisões necessárias</span></div></div>
                        </div>
                    </article>

                    <article class="content-card col-span-4">
                        <div class="card-heading"><div><div class="card-kicker">Comunicados</div><h2>Avisos da gestão</h2></div><button class="btn btn-light btn-sm" type="button" data-demo-action="publicar aviso"><i class="bi bi-megaphone"></i>Publicar</button></div>
                        <div class="notice-list">
                            <div class="notice-item"><span class="notice-icon info"><i class="bi bi-info-circle"></i></span><div class="item-main"><strong>Atualização de cadastros</strong><span>Priorizar famílias com revisão prevista para julho.</span></div></div>
                            <div class="notice-item"><span class="notice-icon success"><i class="bi bi-check-circle"></i></span><div class="item-main"><strong>Operação mensal</strong><span>Quatro polos confirmados para a próxima competência.</span></div></div>
                            <div class="notice-item"><span class="notice-icon warning"><i class="bi bi-exclamation-triangle"></i></span><div class="item-main"><strong>Manutenção programada</strong><span>Ambiente demonstrativo indisponível das 18h às 18h30.</span></div></div>
                        </div>
                    </article>
                </section>
            </main>

            <footer class="app-footer"><span>Dados demonstrativos utilizados apenas para prototipação.</span><span>SIGAS Coari — SEMAS Coari/AM</span></footer>
        </div>
        <div id="bottomNavigation"></div>
    </div>

    <div class="modal fade" id="newServiceModal" tabindex="-1" aria-labelledby="newServiceTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-plus-circle"></i>Novo registro</div><h2 class="modal-title fs-5" id="newServiceTitle">Novo atendimento</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label" for="servicePerson">Pessoa ou família</label><input class="form-control" id="servicePerson" type="text" placeholder="Pesquisar nome, CPF ou código" required></div>
                    <div class="mb-3"><label class="form-label" for="serviceType">Tipo de atendimento</label><select class="form-select" id="serviceType" required><option value="">Selecione</option><option>Orientação social</option><option>Benefício eventual</option><option>Atualização cadastral</option><option>Encaminhamento</option></select></div>
                    <div><label class="form-label" for="serviceNotes">Observações</label><textarea class="form-control" id="serviceNotes" rows="4" placeholder="Registre as informações iniciais"></textarea></div>
                </div>
                <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="button" data-submit-demo="Atendimento registrado com sucesso."><i class="bi bi-check2"></i>Registrar atendimento</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deliveryModal" tabindex="-1" aria-labelledby="deliveryTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-basket2"></i>Comida na Mesa</div><h2 class="modal-title fs-5" id="deliveryTitle">Registrar entrega</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                <div class="modal-body">
                    <div class="alert-soft warning mb-3"><i class="bi bi-exclamation-triangle"></i><div>Confirme a identidade do responsável e a competência antes de concluir.</div></div>
                    <div class="mb-3"><label class="form-label" for="deliveryCode">Código da família</label><input class="form-control" id="deliveryCode" type="text" value="CM-000125" required></div>
                    <div class="row g-3"><div class="col-sm-6"><label class="form-label" for="deliveryMonth">Competência</label><select class="form-select" id="deliveryMonth" required><option>Junho de 2026</option></select></div><div class="col-sm-6"><label class="form-label" for="deliveryPole">Polo</label><select class="form-select" id="deliveryPole" required><option>São Sebastião</option><option>Centro</option><option>Itamarati</option></select></div></div>
                    <div class="mt-3"><label class="form-label" for="deliveryReceiver">Nome do recebedor</label><input class="form-control" id="deliveryReceiver" type="text" value="Maria da Silva" required></div>
                </div>
                <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="button" data-submit-demo="Entrega confirmada com sucesso."><i class="bi bi-check2-circle"></i>Confirmar entrega</button></div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="assets/js/integration-demo.js"></script>
    <script>
window.SIGAS_CONTEXT = <?= json_encode(
    $frontendContext,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?>;
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
