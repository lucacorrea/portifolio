<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\DTO\ComidaMesaFilter;
use App\DTO\PaginatedResult;
use App\Exceptions\AuthorizationException;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ComidaMesaRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\SectorRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\ComidaMesaService;
use App\Services\PermissionService;

require_once __DIR__ . '/bootstrap.php';

$pdo = Database::connection();
$userRepository = new UserRepository($pdo);
$sessionRepository = new UserSessionRepository($pdo);
$accessLevelRepository = new AccessLevelRepository($pdo);
$auditService = new AuditService(new AuditLogRepository($pdo));
$authService = new AuthService($userRepository, $sessionRepository, $accessLevelRepository, $auditService);
$user = $authService->requireUser();
$authorization = new AuthorizationService(new PermissionService(new PermissionRepository($pdo)), $accessLevelRepository);

try {
    $authorization->requirePermission($user, 'comida_mesa.visualizar');
} catch (AuthorizationException) {
    http_response_code(403);
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIGAS Coari - Acesso negado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="modulo">
    <main class="container py-5">
        <section class="content-card mx-auto" style="max-width: 560px;">
            <div class="state-panel show">
                <i class="bi bi-shield-lock"></i>
                <h1 class="fs-4">Acesso negado</h1>
                <p>Seu usuário não possui permissão para acessar o módulo Coari Comida na Mesa.</p>
                <a class="btn btn-primary mt-3" href="dashboard.php"><i class="bi bi-arrow-left"></i>Voltar ao dashboard</a>
            </div>
        </section>
    </main>
</body>
</html>
<?php
    exit;
}

$level = $user->nivelId === null ? null : $accessLevelRepository->findById($user->nivelId);
$sector = $user->setorId === null ? null : (new SectorRepository($pdo))->findById($user->setorId);
$service = new ComidaMesaService(new ComidaMesaRepository($pdo));
$loadError = false;

try {
    $dashboardData = $service->getDashboardData($_GET);
    $filter = $dashboardData['filter'];
    $competence = $dashboardData['competence'];
    $competences = $dashboardData['competences'];
    $poles = $dashboardData['poles'];
    $statistics = $dashboardData['statistics'];
    $registrations = $dashboardData['registrations'];
} catch (Throwable $exception) {
    Logger::application('Comida Mesa page load failed.', ['type' => $exception::class, 'code' => $exception->getCode()]);
    $loadError = true;
    $filter = $service->buildFilter($_GET);
    $competence = null;
    $competences = [];
    $poles = [];
    $statistics = [
        'familias_cadastradas' => 0,
        'beneficiarias_ativas' => 0,
        'em_analise' => 0,
        'lista_espera' => 0,
        'suspensas' => 0,
        'bloqueadas' => 0,
        'polos_ativos' => 0,
        'entregas_competencia' => 0,
        'aguardando_retirada' => 0,
    ];
    $registrations = new PaginatedResult([], 0, $filter->page, $filter->perPage);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

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

function selected(mixed $actual, mixed $expected): string
{
    return (string) $actual === (string) $expected ? ' selected' : '';
}

function date_br(mixed $value): string
{
    if ($value === null || $value === '') {
        return 'Sem atualização';
    }

    try {
        return (new DateTimeImmutable((string) $value))->format('d/m/Y');
    } catch (Throwable) {
        return 'Data inválida';
    }
}

function module_url(ComidaMesaFilter $filter, int $page): string
{
    $params = array_filter([
        'search' => $filter->search,
        'competencia_id' => $filter->competenceId,
        'program_status' => $filter->programStatus,
        'delivery_status' => $filter->deliveryStatus,
        'zone' => $filter->zone,
        'district' => $filter->district,
        'community' => $filter->community,
        'pole_id' => $filter->poleId,
        'page' => $page > 1 ? $page : null,
    ], static fn (mixed $value): bool => $value !== null && $value !== '');
    $query = http_build_query($params);

    return 'modulo.php' . ($query === '' ? '' : '?' . $query);
}

function program_status_meta(string $status): array
{
    return match ($status) {
        'ativa' => ['status-success', 'check-circle'],
        'em_analise' => ['status-info', 'hourglass-split'],
        'lista_espera' => ['status-warning', 'list-ul'],
        'suspensa' => ['status-danger', 'pause-circle'],
        'bloqueada' => ['status-dark', 'lock'],
        default => ['status-neutral', 'dash-circle'],
    };
}

function location_label(array $row): string
{
    $parts = array_filter([
        $row['bairro'] ?? null,
        $row['comunidade'] ?? null,
        $row['zona'] ?? null,
    ], static fn (mixed $value): bool => $value !== null && trim((string) $value) !== '');

    return $parts === [] ? 'Sem localidade' : implode(' - ', array_map('strval', $parts));
}

$items = $registrations->getItems();
$total = $registrations->getTotal();
$page = $registrations->getPage();
$totalPages = $registrations->getTotalPages();
$hasFilters = $filter->search !== null
    || $filter->programStatus !== null
    || $filter->deliveryStatus !== null
    || $filter->zone !== null
    || $filter->district !== null
    || $filter->community !== null
    || $filter->poleId !== null;
$hasAdvanced = $filter->zone !== null || $filter->district !== null || $filter->community !== null || $filter->poleId !== null;
$competenceLabel = $competence === null ? 'Sem competência' : $service->formatCompetence((int) $competence['mes'], (int) $competence['ano']);
$cpfToken = Csrf::token('comida_mesa_consultar_cpf');
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
$stats = [
    ['Famílias cadastradas', $statistics['familias_cadastradas']],
    ['Beneficiárias ativas', $statistics['beneficiarias_ativas']],
    ['Em análise', $statistics['em_analise']],
    ['Lista de espera', $statistics['lista_espera']],
    ['Entregas na competência', $statistics['entregas_competencia']],
    ['Aguardando retirada', $statistics['aguardando_retirada']],
    ['Suspensas ou bloqueadas', $statistics['suspensas'] + $statistics['bloqueadas']],
    ['Polos ativos', $statistics['polos_ativos']],
];
$programStatuses = [
    'ativa' => 'Beneficiária ativa',
    'em_analise' => 'Em análise',
    'lista_espera' => 'Lista de espera',
    'suspensa' => 'Suspensa',
    'bloqueada' => 'Bloqueada',
    'encerrada' => 'Encerrada',
];
$deliveryStatuses = [
    'recebida' => 'Recebida',
    'aguardando' => 'Aguardando retirada',
    'bloqueada' => 'Bloqueada',
    'indisponivel' => 'Não disponível',
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Módulo funcional do Programa Coari Comida na Mesa.">
    <title>SIGAS Coari - Comida na Mesa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="modulo">
    <div class="app-shell">
        <aside class="app-sidebar" id="appSidebar" aria-label="Menu principal"></aside>
        <div class="app-main">
            <header class="app-topbar" id="appTopbar"></header>
            <main class="app-content">
                <section class="module-hero" aria-labelledby="moduleTitle">
                    <div class="module-hero-content">
                        <div>
                            <div class="eyebrow text-warning mb-1"><i class="bi bi-star-fill"></i>Programa prioritário</div>
                            <h1 id="moduleTitle">Coari Comida na Mesa</h1>
                            <p>Gestão de famílias beneficiárias, inscrições, entregas mensais, polos e ocorrências.</p>
                        </div>
                        <div class="module-hero-actions page-actions">
                            <button class="btn btn-light" type="button" disabled title="Disponível na próxima etapa"><i class="bi bi-download"></i><span class="optional">Exportar</span></button>
                            <button class="btn btn-light" type="button" disabled title="Disponível na próxima etapa"><i class="bi bi-basket2"></i><span class="optional">Registrar entrega</span></button>
                            <button class="btn btn-warning" type="button" data-bs-toggle="modal" data-bs-target="#newRegistrationModal"><i class="bi bi-plus-lg"></i>Nova inscrição</button>
                        </div>
                    </div>
                </section>

                <section data-tabs-group aria-label="Áreas do programa">
                    <nav class="module-tabs" aria-label="Navegação interna do módulo">
                        <button class="module-tab" type="button" data-tab-target="overview">Visão Geral</button>
                        <button class="module-tab" type="button" data-tab-target="registrations">Inscrições</button>
                        <button class="module-tab" type="button" data-tab-target="analysis">Em Análise</button>
                        <button class="module-tab active" type="button" data-tab-target="beneficiaries" aria-current="page">Beneficiários</button>
                        <button class="module-tab" type="button" data-tab-target="waiting">Lista de Espera</button>
                        <button class="module-tab" type="button" data-tab-target="deliveries">Entregas</button>
                        <button class="module-tab" type="button" data-tab-target="poles">Polos</button>
                        <button class="module-tab" type="button" data-tab-target="occurrences">Ocorrências</button>
                        <button class="module-tab" type="button" data-tab-target="reports">Relatórios</button>
                    </nav>

                    <div class="compact-stats" aria-label="Indicadores do programa">
                        <?php foreach ($stats as [$label, $value]): ?>
                            <div class="compact-stat"><span><?= e($label) ?></span><strong><?= e(number_format((int) $value, 0, ',', '.')) ?></strong></div>
                        <?php endforeach; ?>
                    </div>

                    <div data-tab-panel="beneficiaries">
                        <?php if ($competence === null): ?>
                            <div class="alert-soft warning mb-3"><i class="bi bi-calendar-x"></i><div>Nenhuma competência foi cadastrada. As inscrições continuam consultáveis, mas a situação mensal de entrega não está disponível.</div></div>
                        <?php endif; ?>
                        <?php if ($loadError): ?>
                            <div class="alert-soft danger mb-3"><i class="bi bi-exclamation-octagon"></i><div>Não foi possível carregar os dados do módulo agora.</div></div>
                        <?php endif; ?>

                        <form class="filter-bar" id="filterForm" method="get" action="modulo.php" data-server-filter aria-label="Filtros da listagem">
                            <div class="filter-row">
                                <div>
                                    <label class="form-label" for="beneficiarySearch">Pesquisa</label>
                                    <div class="search-field"><i class="bi bi-search"></i><input class="form-control" id="beneficiarySearch" name="search" type="search" value="<?= e($filter->search) ?>" placeholder="Nome, CPF, NIS ou código"></div>
                                </div>
                                <div><label class="form-label" for="competenceFilter">Competência</label><select class="form-select" id="competenceFilter" name="competencia_id"><option value="">Padrão</option><?php foreach ($competences as $item): ?><option value="<?= e($item['id']) ?>"<?= selected($filter->competenceId, $item['id']) ?>><?= e($service->formatCompetence((int) $item['mes'], (int) $item['ano'])) ?></option><?php endforeach; ?></select></div>
                                <div><label class="form-label" for="programStatusFilter">Situação no programa</label><select class="form-select" id="programStatusFilter" name="program_status"><option value="">Todas</option><?php foreach ($programStatuses as $value => $label): ?><option value="<?= e($value) ?>"<?= selected($filter->programStatus, $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                                <div class="delivery-filter"><label class="form-label" for="deliveryStatusFilter">Situação da entrega</label><select class="form-select" id="deliveryStatusFilter" name="delivery_status"><option value="">Todas</option><?php foreach ($deliveryStatuses as $value => $label): ?><option value="<?= e($value) ?>"<?= selected($filter->deliveryStatus, $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                                <div class="filter-actions"><button class="btn btn-light" type="button" data-toggle-advanced aria-expanded="<?= $hasAdvanced ? 'true' : 'false' ?>"><i class="bi bi-sliders"></i>Avançados</button><button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i>Aplicar</button></div>
                            </div>
                            <div class="advanced-filters<?= $hasAdvanced ? ' show' : '' ?>" id="advancedFilters">
                                <div><label class="form-label" for="zoneFilter">Zona</label><input class="form-control" id="zoneFilter" name="zone" value="<?= e($filter->zone) ?>"></div>
                                <div><label class="form-label" for="districtFilter">Bairro</label><input class="form-control" id="districtFilter" name="district" value="<?= e($filter->district) ?>"></div>
                                <div><label class="form-label" for="communityFilter">Comunidade</label><input class="form-control" id="communityFilter" name="community" value="<?= e($filter->community) ?>"></div>
                                <div><label class="form-label" for="poleFilter">Polo</label><select class="form-select" id="poleFilter" name="pole_id"><option value="">Todos</option><?php foreach ($poles as $pole): ?><option value="<?= e($pole['id']) ?>"<?= selected($filter->poleId, $pole['id']) ?>><?= e($pole['nome']) ?></option><?php endforeach; ?></select></div>
                                <div class="d-flex align-items-end"><a class="btn btn-light w-100" href="modulo.php"><i class="bi bi-arrow-counterclockwise"></i>Limpar filtros</a></div>
                            </div>
                        </form>

                        <section class="content-card table-card" aria-labelledby="beneficiaryListTitle">
                            <div class="table-toolbar">
                                <div><h2 class="fs-6 mb-1" id="beneficiaryListTitle">Famílias beneficiárias</h2><div class="table-toolbar-info">Exibindo <?= e(count($items)) ?> de <?= e(number_format($total, 0, ',', '.')) ?> registros - <?= e($competenceLabel) ?></div></div>
                                <div class="d-flex gap-2"><button class="btn btn-light btn-sm" type="button" disabled title="Disponível na próxima etapa"><i class="bi bi-archive"></i>Arquivar</button><button class="btn btn-light btn-sm" type="button" disabled><i class="bi bi-check2-square"></i>Ação em lote</button></div>
                            </div>

                            <div class="table-responsive">
                                <table class="data-table" id="beneficiaryTable">
                                    <thead><tr><th><input class="form-check-input" type="checkbox" disabled aria-label="Selecionar todos"></th><th>Código</th><th>Responsável familiar</th><th>Localidade</th><th>Polo</th><th>Situação no programa</th><th>Entrega</th><th>Atualização</th><th class="text-end">Ações</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($items as $row): ?>
                                            <?php [$programClass, $programIcon] = program_status_meta((string) $row['inscricao_status']); $delivery = $service->deliveryStatusForRow($row, $competence); ?>
                                            <tr>
                                                <td><input class="form-check-input" type="checkbox" disabled aria-label="Selecionar <?= e($row['responsavel_nome']) ?>"></td>
                                                <td><strong><?= e($row['familia_codigo']) ?></strong></td>
                                                <td><div class="record-person"><span class="mini-avatar"><?= e(sigas_initials((string) $row['responsavel_nome'])) ?></span><div><strong><?= e($row['responsavel_nome']) ?></strong><span>CPF: <?= e($service->maskCpf((string) $row['cpf'])) ?> · NIS <?= e($row['nis'] ?: 'Não informado') ?></span></div></div></td>
                                                <td><?= e(location_label($row)) ?></td>
                                                <td><?= e($row['polo_nome'] ?: 'Sem polo') ?></td>
                                                <td><span class="status-badge <?= e($programClass) ?>"><i class="bi bi-<?= e($programIcon) ?>"></i><?= e($service->programStatusLabel((string) $row['inscricao_status'])) ?></span></td>
                                                <td><span class="status-badge <?= e($delivery['class']) ?>"><i class="bi bi-<?= e($delivery['icon']) ?>"></i><?= e($delivery['label']) ?></span><?= $delivery['delivered_at'] ? '<br><small class="text-secondary">' . e(date_br($delivery['delivered_at'])) . '</small>' : '' ?></td>
                                                <td><?= e(date_br($row['atualizado_em'] ?: $row['data_inscricao'])) ?></td>
                                                <td><div class="table-actions justify-content-end"><button class="btn btn-light btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#newRegistrationModal" data-consult-cpf="<?= e($row['cpf']) ?>"><i class="bi bi-search"></i>Consultar</button><div class="dropdown"><button class="btn btn-light btn-icon btn-sm" type="button" data-bs-toggle="dropdown" aria-label="Ações de <?= e($row['responsavel_nome']) ?>"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" disabled title="Disponível na próxima etapa"><i class="bi bi-eye me-2"></i>Visualizar</button></li><li><button class="dropdown-item" type="button" disabled title="Disponível na próxima etapa"><i class="bi bi-pencil me-2"></i>Editar cadastro</button></li><li><button class="dropdown-item" type="button" disabled title="Disponível na próxima etapa"><i class="bi bi-basket2 me-2"></i>Registrar entrega</button></li><li><button class="dropdown-item" type="button" disabled title="Disponível na próxima etapa"><i class="bi bi-clock-history me-2"></i>Histórico</button></li></ul></div></div></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ($loadError): ?>
                                <div class="state-panel show" id="errorState"><i class="bi bi-exclamation-octagon"></i><h3>Não foi possível carregar os resultados</h3><p>Tente novamente mais tarde.</p></div>
                            <?php elseif ($items === []): ?>
                                <div class="state-panel show" id="emptyState"><i class="bi bi-search"></i><h3><?= $hasFilters ? 'Nenhum registro corresponde aos filtros informados.' : 'Nenhuma família está inscrita no programa.' ?></h3><p><?= $hasFilters ? 'Revise os termos informados ou limpe os filtros.' : 'As inscrições aparecerão aqui quando forem cadastradas.' ?></p><a class="btn btn-light btn-sm mt-3" href="modulo.php">Limpar filtros</a></div>
                            <?php endif; ?>

                            <div class="pagination-wrap"><span class="table-toolbar-info">Página <?= e($page) ?> de <?= e($totalPages) ?> - <?= e(number_format($total, 0, ',', '.')) ?> registro(s)</span><nav aria-label="Paginação"><ul class="pagination pagination-sm"><?php $start = max(1, min($page - 2, $totalPages - 4)); $end = min($totalPages, $start + 4); ?><li class="page-item<?= $registrations->hasPreviousPage() ? '' : ' disabled' ?>"><?= $registrations->hasPreviousPage() ? '<a class="page-link" href="' . e(module_url($filter, $page - 1)) . '" aria-label="Página anterior"><i class="bi bi-chevron-left"></i></a>' : '<span class="page-link"><i class="bi bi-chevron-left"></i></span>' ?></li><?php for ($number = $start; $number <= $end; $number++): ?><li class="page-item<?= $number === $page ? ' active' : '' ?>"><a class="page-link" href="<?= e(module_url($filter, $number)) ?>"><?= e($number) ?></a></li><?php endfor; ?><li class="page-item<?= $registrations->hasNextPage() ? '' : ' disabled' ?>"><?= $registrations->hasNextPage() ? '<a class="page-link" href="' . e(module_url($filter, $page + 1)) . '" aria-label="Próxima página"><i class="bi bi-chevron-right"></i></a>' : '<span class="page-link"><i class="bi bi-chevron-right"></i></span>' ?></li></ul></nav></div>
                        </section>

                        <section class="mobile-records" aria-label="Famílias beneficiárias em cartões">
                            <?php foreach ($items as $row): ?>
                                <?php [$programClass] = program_status_meta((string) $row['inscricao_status']); $delivery = $service->deliveryStatusForRow($row, $competence); ?>
                                <article class="mobile-record-card">
                                    <div class="mobile-record-head"><span class="mini-avatar"><?= e(sigas_initials((string) $row['responsavel_nome'])) ?></span><div class="item-main"><strong><?= e($row['responsavel_nome']) ?></strong><span><?= e($row['familia_codigo']) ?> · CPF <?= e($service->maskCpf((string) $row['cpf'])) ?></span></div><button class="btn btn-light btn-icon btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#newRegistrationModal" data-consult-cpf="<?= e($row['cpf']) ?>" aria-label="Consultar CPF"><i class="bi bi-search"></i></button></div>
                                    <div class="mobile-record-grid"><div class="mobile-record-meta"><span>Localidade</span><strong><?= e(location_label($row)) ?></strong></div><div class="mobile-record-meta"><span>Polo</span><strong><?= e($row['polo_nome'] ?: 'Sem polo') ?></strong></div></div>
                                    <div class="d-flex flex-wrap gap-2 mb-3"><span class="status-badge <?= e($programClass) ?>"><?= e($service->programStatusLabel((string) $row['inscricao_status'])) ?></span><span class="status-badge <?= e($delivery['class']) ?>"><?= e($delivery['label']) ?></span></div>
                                    <div class="mobile-record-actions"><button class="btn btn-primary flex-grow-1" type="button" data-bs-toggle="modal" data-bs-target="#newRegistrationModal" data-consult-cpf="<?= e($row['cpf']) ?>"><i class="bi bi-search"></i>Consultar</button><button class="btn btn-light btn-icon" type="button" disabled title="Disponível na próxima etapa"><i class="bi bi-eye"></i></button></div>
                                </article>
                            <?php endforeach; ?>
                        </section>
                    </div>

                    <?php foreach (['overview' => 'Visão geral', 'registrations' => 'Inscrições', 'analysis' => 'Em análise', 'waiting' => 'Lista de espera', 'deliveries' => 'Entregas', 'poles' => 'Polos', 'occurrences' => 'Ocorrências', 'reports' => 'Relatórios'] as $panel => $title): ?>
                        <div class="content-card" data-tab-panel="<?= e($panel) ?>" hidden><div class="state-panel show"><i class="bi bi-hourglass-split"></i><h3><?= e($title) ?></h3><p>Disponível na próxima etapa.</p></div></div>
                    <?php endforeach; ?>
                </section>
            </main>
            <footer class="app-footer"><span>Dados carregados do banco do SIGAS.</span><span>SIGAS Coari - SEMAS Coari/AM</span></footer>
        </div>
        <div id="bottomNavigation"></div>
    </div>

    <button class="btn btn-primary floating-action" type="button" data-bs-toggle="modal" data-bs-target="#newRegistrationModal" aria-label="Nova inscrição"><i class="bi bi-plus-lg"></i></button>
    <button class="btn btn-light mobile-filter-fab" type="button" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas" aria-controls="filterOffcanvas" aria-label="Abrir filtros"><i class="bi bi-funnel"></i></button>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasTitle">
        <div class="offcanvas-header"><div><div class="eyebrow mb-1"><i class="bi bi-funnel"></i>Pesquisa</div><h2 class="offcanvas-title fs-5" id="filterOffcanvasTitle">Filtrar beneficiários</h2></div><button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Fechar"></button></div>
        <div class="offcanvas-body"><form method="get" action="modulo.php" data-server-filter><div class="mb-3"><label class="form-label" for="mobileSearch">Nome, CPF, NIS ou código</label><input class="form-control" id="mobileSearch" name="search" value="<?= e($filter->search) ?>" type="search"></div><div class="mb-3"><label class="form-label" for="mobileCompetence">Competência</label><select class="form-select" id="mobileCompetence" name="competencia_id"><option value="">Padrão</option><?php foreach ($competences as $item): ?><option value="<?= e($item['id']) ?>"<?= selected($filter->competenceId, $item['id']) ?>><?= e($service->formatCompetence((int) $item['mes'], (int) $item['ano'])) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label" for="mobileProgramStatus">Situação no programa</label><select class="form-select" id="mobileProgramStatus" name="program_status"><option value="">Todas</option><?php foreach ($programStatuses as $value => $label): ?><option value="<?= e($value) ?>"<?= selected($filter->programStatus, $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label" for="mobileDeliveryStatus">Situação da entrega</label><select class="form-select" id="mobileDeliveryStatus" name="delivery_status"><option value="">Todas</option><?php foreach ($deliveryStatuses as $value => $label): ?><option value="<?= e($value) ?>"<?= selected($filter->deliveryStatus, $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="row g-3"><div class="col-6"><label class="form-label" for="mobileZone">Zona</label><input class="form-control" id="mobileZone" name="zone" value="<?= e($filter->zone) ?>"></div><div class="col-6"><label class="form-label" for="mobilePole">Polo</label><select class="form-select" id="mobilePole" name="pole_id"><option value="">Todos</option><?php foreach ($poles as $pole): ?><option value="<?= e($pole['id']) ?>"<?= selected($filter->poleId, $pole['id']) ?>><?= e($pole['nome']) ?></option><?php endforeach; ?></select></div></div><button class="btn btn-primary w-100 mt-4" type="submit"><i class="bi bi-funnel"></i>Aplicar filtros</button><a class="btn btn-light w-100 mt-2" href="modulo.php"><i class="bi bi-arrow-counterclockwise"></i>Limpar</a></form></div>
    </div>

    <div class="modal fade" id="newRegistrationModal" tabindex="-1" aria-labelledby="newRegistrationTitle" aria-hidden="true" data-comida-mesa-consulta>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-person-plus"></i>Programa prioritário</div><h2 class="modal-title fs-5" id="newRegistrationTitle">Consultar CPF para inscrição</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                <form id="cpfLookupForm" method="post" action="api/comida-mesa/consultar-cpf.php">
                    <div class="modal-body">
                        <div class="alert-soft success mb-3"><i class="bi bi-info-circle"></i><div>Cadastro será habilitado na próxima etapa.</div></div>
                        <input type="hidden" name="_csrf" value="<?= e($cpfToken) ?>">
                        <input type="hidden" name="competencia_id" value="<?= e($filter->competenceId) ?>">
                        <div class="mb-3"><label class="form-label" for="cpfLookupInput">CPF</label><input class="form-control" id="cpfLookupInput" name="cpf" type="text" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" required><div class="invalid-feedback">Informe um CPF com 11 números.</div></div>
                        <div id="cpfLookupResult" aria-live="polite"></div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit" data-cpf-submit><i class="bi bi-search"></i>Consultar</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/integration-demo.js"></script>
    <script>
window.SIGAS_CONTEXT = <?= json_encode($frontendContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/comida-mesa.js"></script>
</body>
</html>
