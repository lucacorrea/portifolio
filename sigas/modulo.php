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
$currentCompetenceId = $competence === null ? null : (int) $competence['id'];
$competenceLabel = $competence === null ? 'Sem competência' : $service->formatCompetence((int) $competence['mes'], (int) $competence['ano']);
$cpfToken = Csrf::token('comida_mesa_consultar_cpf');
$canConsultCpf = $authorization->can($user, 'comida_mesa.consultar_cpf');
$canCreate = $authorization->can($user, 'comida_mesa.cadastrar');
$canEdit = $authorization->can($user, 'comida_mesa.editar');
$canDeliver = $authorization->can($user, 'comida_mesa.entregar');
$canCancelDelivery = $authorization->can($user, 'comida_mesa.cancelar_entrega');
$canViewDocuments = $authorization->can($user, 'comida_mesa.documentos_visualizar');
$canSendDocuments = $authorization->can($user, 'comida_mesa.documentos_enviar');
$canViewHistory = $authorization->can($user, 'comida_mesa.historico_visualizar');
$canManageCompetences = $authorization->can($user, 'comida_mesa.competencias_gerenciar');
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
        'salvarCadastro' => Csrf::token('comida_mesa_salvar_cadastro'),
        'salvarCompetencia' => Csrf::token('comida_mesa_salvar_competencia'),
        'registrarEntrega' => Csrf::token('comida_mesa_registrar_entrega'),
        'cancelarEntrega' => Csrf::token('comida_mesa_cancelar_entrega'),
        'enviarDocumento' => Csrf::token('comida_mesa_enviar_documento'),
    ],
    'comidaMesa' => [
        'competenciaId' => $currentCompetenceId,
        'competenceLabel' => $competenceLabel,
        'competences' => array_map(static fn (array $item): array => [
            'id' => (int) $item['id'],
            'month' => (int) $item['mes'],
            'year' => (int) $item['ano'],
            'status' => (string) $item['status'],
            'startsAt' => $item['inicio_entregas'] ?? '',
            'endsAt' => $item['fim_entregas'] ?? '',
            'observation' => $item['observacao'] ?? '',
        ], $competences),
        'permissions' => [
            'consultCpf' => $canConsultCpf,
            'create' => $canCreate,
            'edit' => $canEdit,
            'deliver' => $canDeliver,
            'cancelDelivery' => $canCancelDelivery,
            'viewDocuments' => $canViewDocuments,
            'sendDocuments' => $canSendDocuments,
            'viewHistory' => $canViewHistory,
            'manageCompetences' => $canManageCompetences,
        ],
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
                            <button class="btn btn-light" type="button"<?= $canManageCompetences ? ' data-open-new-competence' : ' disabled title="Sem permissão para gerenciar competências"' ?>><i class="bi bi-calendar-plus"></i><span class="optional">Nova competência</span></button>
                            <button class="btn btn-light" type="button"<?= ($canManageCompetences && $currentCompetenceId !== null) ? ' data-open-edit-competence' : ' disabled title="Selecione uma competência para editar"' ?>><i class="bi bi-calendar-check"></i><span class="optional">Editar competência</span></button>
                            <button class="btn btn-warning" type="button"<?= ($canConsultCpf && $canCreate) ? ' data-bs-toggle="modal" data-bs-target="#newRegistrationModal"' : ' disabled title="Sem permissão para nova inscrição"' ?>><i class="bi bi-plus-lg"></i>Nova inscrição</button>
                        </div>
                    </div>
                </section>

                <section data-tabs-group aria-label="Áreas do programa">
                    <nav class="module-tabs" aria-label="Navegação interna do módulo">
                        <button class="module-tab active" type="button" data-tab-target="beneficiaries" aria-current="page">Beneficiários</button>
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
                                <div><label class="form-label" for="competenceFilter">Competência</label><div class="input-group"><select class="form-select" id="competenceFilter" name="competencia_id"><option value="">Padrão</option><?php foreach ($competences as $item): ?><option value="<?= e($item['id']) ?>"<?= selected($currentCompetenceId, $item['id']) ?>><?= e($service->formatCompetence((int) $item['mes'], (int) $item['ano'])) ?></option><?php endforeach; ?></select><button class="btn btn-light" type="button"<?= ($canManageCompetences && $currentCompetenceId !== null) ? ' data-open-edit-competence' : ' disabled title="Selecione uma competência"' ?> aria-label="Editar competência"><i class="bi bi-gear"></i></button></div></div>
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
                                <div class="d-flex gap-2"><button class="btn btn-light btn-sm" type="button"<?= $canManageCompetences ? ' data-open-new-competence' : ' disabled title="Sem permissão"' ?>><i class="bi bi-calendar-plus"></i>Nova</button><button class="btn btn-light btn-sm" type="button"<?= ($canManageCompetences && $currentCompetenceId !== null) ? ' data-open-edit-competence' : ' disabled title="Selecione uma competência"' ?>><i class="bi bi-pencil"></i>Editar</button></div>
                            </div>

                            <div class="table-responsive">
                                <table class="data-table" id="beneficiaryTable">
                                    <thead><tr><th><input class="form-check-input" type="checkbox" disabled aria-label="Selecionar todos"></th><th>Código</th><th>Responsável familiar</th><th>Localidade</th><th>Polo</th><th>Situação no programa</th><th>Entrega</th><th>Atualização</th><th class="text-end">Ações</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($items as $row): ?>
                                            <?php
                                            [$programClass, $programIcon] = program_status_meta((string) $row['inscricao_status']);
                                            $delivery = $service->deliveryStatusForRow($row, $competence);
                                            $rowDelivery = empty($row['entrega_id']) ? null : ['status' => (string) $row['entrega_status']];
                                            $eligibility = $service->deliveryEligibility(
                                                [
                                                    'status' => (string) $row['inscricao_status'],
                                                    'polo_id' => $row['polo_id'] ?? null,
                                                    'polo_ativo' => $row['polo_ativo'] ?? null,
                                                ],
                                                $competence,
                                                $rowDelivery
                                            );
                                            $deliveryAction = (string) $eligibility['action'];
                                            $canDeliverRow = $canDeliver && (bool) $eligibility['allowed'] && in_array($deliveryAction, ['register', 'reactivate'], true);
                                            $canCancelRow = $canCancelDelivery && (bool) $eligibility['allowed'] && $deliveryAction === 'cancel';
                                            $deliveryTitle = $eligibility['reason'] ?? 'Sem permissão para esta ação.';
                                            $deliveryButtonLabel = $deliveryAction === 'reactivate' ? 'Reativar entrega' : 'Registrar entrega';
                                            $deliveryDateLabel = $delivery['delivered_at'] ? date_br($delivery['delivered_at']) : 'Não informado';
                                            $deliveryOperatorLabel = $row['entrega_operador_nome'] ?: 'Não informado';
                                            ?>
                                            <tr>
                                                <td><input class="form-check-input" type="checkbox" disabled aria-label="Selecionar <?= e($row['responsavel_nome']) ?>"></td>
                                                <td><strong><?= e($row['familia_codigo']) ?></strong></td>
                                                <td><div class="record-person"><span class="mini-avatar"><?= e(sigas_initials((string) $row['responsavel_nome'])) ?></span><div><strong><?= e($row['responsavel_nome']) ?></strong><span>CPF: <?= e($service->maskCpf((string) $row['cpf'])) ?> · NIS <?= e($row['nis'] ?: 'Não informado') ?></span></div></div></td>
                                                <td><?= e(location_label($row)) ?></td>
                                                <td><?= e($row['polo_nome'] ?: 'Sem polo') ?></td>
                                                <td><span class="status-badge <?= e($programClass) ?>"><i class="bi bi-<?= e($programIcon) ?>"></i><?= e($service->programStatusLabel((string) $row['inscricao_status'])) ?></span></td>
                                                <td><span class="status-badge <?= e($delivery['class']) ?>"><i class="bi bi-<?= e($delivery['icon']) ?>"></i><?= e($delivery['label']) ?></span><?= $delivery['delivered_at'] ? '<br><small class="text-secondary">' . e(date_br($delivery['delivered_at'])) . '</small>' : '' ?></td>
                                                <td><?= e(date_br($row['atualizado_em'] ?: $row['data_inscricao'])) ?></td>
                                                <td><div class="table-actions justify-content-end"><button class="btn btn-light btn-sm" type="button" data-open-detail data-registration-id="<?= e($row['inscricao_id']) ?>"><i class="bi bi-eye"></i>Visualizar</button><div class="dropdown"><button class="btn btn-light btn-icon btn-sm" type="button" data-bs-toggle="dropdown" aria-label="Ações de <?= e($row['responsavel_nome']) ?>"><i class="bi bi-three-dots"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button"<?= $canEdit ? ' data-open-edit data-registration-id="' . e($row['inscricao_id']) . '"' : ' disabled title="Sem permissão"' ?>><i class="bi bi-pencil me-2"></i>Editar cadastro</button></li><li><button class="dropdown-item" type="button"<?= $canDeliverRow ? ' data-open-delivery data-registration-id="' . e($row['inscricao_id']) . '" data-registration-name="' . e($row['responsavel_nome']) . '" data-family-code="' . e($row['familia_codigo']) . '" data-pole-name="' . e($row['polo_nome'] ?: 'Sem polo') . '" data-delivery-action="' . e($deliveryAction) . '"' : ' disabled title="' . e($deliveryTitle) . '"' ?>><i class="bi bi-basket2 me-2"></i><?= e($deliveryButtonLabel) ?></button></li><li><button class="dropdown-item" type="button"<?= $canCancelRow ? ' data-open-cancel data-registration-id="' . e($row['inscricao_id']) . '" data-registration-name="' . e($row['responsavel_nome']) . '" data-family-code="' . e($row['familia_codigo']) . '" data-pole-name="' . e($row['polo_nome'] ?: 'Sem polo') . '" data-delivery-date="' . e($deliveryDateLabel) . '" data-delivery-operator="' . e($deliveryOperatorLabel) . '"' : ' disabled title="Cancelamento indisponível"' ?>><i class="bi bi-x-circle me-2"></i>Cancelar entrega</button></li><li><button class="dropdown-item" type="button"<?= $canSendDocuments ? ' data-open-document data-registration-id="' . e($row['inscricao_id']) . '"' : ' disabled title="Sem permissão"' ?>><i class="bi bi-paperclip me-2"></i>Enviar documento</button></li><?php if ($canViewHistory): ?><li><button class="dropdown-item" type="button" data-open-detail data-registration-id="<?= e($row['inscricao_id']) ?>" data-detail-section="history"><i class="bi bi-clock-history me-2"></i>Abrir histórico</button></li><?php endif; ?></ul></div></div></td>
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
                                <?php
                                [$programClass] = program_status_meta((string) $row['inscricao_status']);
                                $delivery = $service->deliveryStatusForRow($row, $competence);
                                $rowDelivery = empty($row['entrega_id']) ? null : ['status' => (string) $row['entrega_status']];
                                $eligibility = $service->deliveryEligibility(
                                    [
                                        'status' => (string) $row['inscricao_status'],
                                        'polo_id' => $row['polo_id'] ?? null,
                                        'polo_ativo' => $row['polo_ativo'] ?? null,
                                    ],
                                    $competence,
                                    $rowDelivery
                                );
                                $deliveryAction = (string) $eligibility['action'];
                                $canDeliverRow = $canDeliver && (bool) $eligibility['allowed'] && in_array($deliveryAction, ['register', 'reactivate'], true);
                                $canCancelRow = $canCancelDelivery && (bool) $eligibility['allowed'] && $deliveryAction === 'cancel';
                                $deliveryTitle = $eligibility['reason'] ?? 'Sem permissão para esta ação.';
                                $deliveryButtonLabel = $deliveryAction === 'reactivate' ? 'Reativar entrega' : 'Registrar entrega';
                                $deliveryDateLabel = $delivery['delivered_at'] ? date_br($delivery['delivered_at']) : 'Não informado';
                                $deliveryOperatorLabel = $row['entrega_operador_nome'] ?: 'Não informado';
                                ?>
                                <article class="mobile-record-card">
                                    <div class="mobile-record-head"><span class="mini-avatar"><?= e(sigas_initials((string) $row['responsavel_nome'])) ?></span><div class="item-main"><strong><?= e($row['responsavel_nome']) ?></strong><span><?= e($row['familia_codigo']) ?> · CPF <?= e($service->maskCpf((string) $row['cpf'])) ?></span></div><button class="btn btn-light btn-icon btn-sm" type="button" data-open-detail data-registration-id="<?= e($row['inscricao_id']) ?>" aria-label="Visualizar"><i class="bi bi-eye"></i></button></div>
                                    <div class="mobile-record-grid"><div class="mobile-record-meta"><span>Localidade</span><strong><?= e(location_label($row)) ?></strong></div><div class="mobile-record-meta"><span>Polo</span><strong><?= e($row['polo_nome'] ?: 'Sem polo') ?></strong></div></div>
                                    <div class="d-flex flex-wrap gap-2 mb-3"><span class="status-badge <?= e($programClass) ?>"><?= e($service->programStatusLabel((string) $row['inscricao_status'])) ?></span><span class="status-badge <?= e($delivery['class']) ?>"><?= e($delivery['label']) ?></span></div>
                                    <div class="mobile-record-actions"><button class="btn btn-primary flex-grow-1" type="button" data-open-detail data-registration-id="<?= e($row['inscricao_id']) ?>"><i class="bi bi-eye"></i>Visualizar</button><div class="dropdown"><button class="btn btn-light btn-icon" type="button" data-bs-toggle="dropdown" aria-label="Ações de <?= e($row['responsavel_nome']) ?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button"<?= $canEdit ? ' data-open-edit data-registration-id="' . e($row['inscricao_id']) . '"' : ' disabled title="Sem permissão"' ?>><i class="bi bi-pencil me-2"></i>Editar</button></li><li><button class="dropdown-item" type="button"<?= $canDeliverRow ? ' data-open-delivery data-registration-id="' . e($row['inscricao_id']) . '" data-registration-name="' . e($row['responsavel_nome']) . '" data-family-code="' . e($row['familia_codigo']) . '" data-pole-name="' . e($row['polo_nome'] ?: 'Sem polo') . '" data-delivery-action="' . e($deliveryAction) . '"' : ' disabled title="' . e($deliveryTitle) . '"' ?>><i class="bi bi-basket2 me-2"></i><?= e($deliveryButtonLabel) ?></button></li><li><button class="dropdown-item" type="button"<?= $canCancelRow ? ' data-open-cancel data-registration-id="' . e($row['inscricao_id']) . '" data-registration-name="' . e($row['responsavel_nome']) . '" data-family-code="' . e($row['familia_codigo']) . '" data-pole-name="' . e($row['polo_nome'] ?: 'Sem polo') . '" data-delivery-date="' . e($deliveryDateLabel) . '" data-delivery-operator="' . e($deliveryOperatorLabel) . '"' : ' disabled title="Cancelamento indisponível"' ?>><i class="bi bi-x-circle me-2"></i>Cancelar entrega</button></li><li><button class="dropdown-item" type="button"<?= $canSendDocuments ? ' data-open-document data-registration-id="' . e($row['inscricao_id']) . '"' : ' disabled title="Sem permissão"' ?>><i class="bi bi-paperclip me-2"></i>Enviar documento</button></li><?php if ($canViewHistory): ?><li><button class="dropdown-item" type="button" data-open-detail data-registration-id="<?= e($row['inscricao_id']) ?>" data-detail-section="history"><i class="bi bi-clock-history me-2"></i>Abrir histórico</button></li><?php endif; ?></ul></div></div>
                                </article>
                            <?php endforeach; ?>
                        </section>
                    </div>

                </section>
            </main>
            <footer class="app-footer"><span>Dados carregados do banco do SIGAS.</span><span>SIGAS Coari - SEMAS Coari/AM</span></footer>
        </div>
        <div id="bottomNavigation"></div>
    </div>

    <button class="btn btn-primary floating-action" type="button"<?= ($canConsultCpf && $canCreate) ? ' data-bs-toggle="modal" data-bs-target="#newRegistrationModal"' : ' disabled title="Sem permissão para nova inscrição"' ?> aria-label="Nova inscrição"><i class="bi bi-plus-lg"></i></button>
    <button class="btn btn-light mobile-filter-fab" type="button" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas" aria-controls="filterOffcanvas" aria-label="Abrir filtros"><i class="bi bi-funnel"></i></button>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasTitle">
        <div class="offcanvas-header"><div><div class="eyebrow mb-1"><i class="bi bi-funnel"></i>Pesquisa</div><h2 class="offcanvas-title fs-5" id="filterOffcanvasTitle">Filtrar beneficiários</h2></div><button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Fechar"></button></div>
        <div class="offcanvas-body"><form method="get" action="modulo.php" data-server-filter><div class="mb-3"><label class="form-label" for="mobileSearch">Nome, CPF, NIS ou código</label><input class="form-control" id="mobileSearch" name="search" value="<?= e($filter->search) ?>" type="search"></div><div class="mb-3"><label class="form-label" for="mobileCompetence">Competência</label><select class="form-select" id="mobileCompetence" name="competencia_id"><option value="">Padrão</option><?php foreach ($competences as $item): ?><option value="<?= e($item['id']) ?>"<?= selected($currentCompetenceId, $item['id']) ?>><?= e($service->formatCompetence((int) $item['mes'], (int) $item['ano'])) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label" for="mobileProgramStatus">Situação no programa</label><select class="form-select" id="mobileProgramStatus" name="program_status"><option value="">Todas</option><?php foreach ($programStatuses as $value => $label): ?><option value="<?= e($value) ?>"<?= selected($filter->programStatus, $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label" for="mobileDeliveryStatus">Situação da entrega</label><select class="form-select" id="mobileDeliveryStatus" name="delivery_status"><option value="">Todas</option><?php foreach ($deliveryStatuses as $value => $label): ?><option value="<?= e($value) ?>"<?= selected($filter->deliveryStatus, $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="row g-3"><div class="col-6"><label class="form-label" for="mobileZone">Zona</label><input class="form-control" id="mobileZone" name="zone" value="<?= e($filter->zone) ?>"></div><div class="col-6"><label class="form-label" for="mobilePole">Polo</label><select class="form-select" id="mobilePole" name="pole_id"><option value="">Todos</option><?php foreach ($poles as $pole): ?><option value="<?= e($pole['id']) ?>"<?= selected($filter->poleId, $pole['id']) ?>><?= e($pole['nome']) ?></option><?php endforeach; ?></select></div></div><button class="btn btn-primary w-100 mt-4" type="submit"><i class="bi bi-funnel"></i>Aplicar filtros</button><a class="btn btn-light w-100 mt-2" href="modulo.php"><i class="bi bi-arrow-counterclockwise"></i>Limpar</a></form></div>
    </div>

    <div class="modal fade" id="newRegistrationModal" tabindex="-1" aria-labelledby="newRegistrationTitle" aria-hidden="true" data-comida-mesa-consulta>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-person-plus"></i>Programa prioritário</div><h2 class="modal-title fs-5" id="newRegistrationTitle">Consultar CPF para inscrição</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                <form id="cpfLookupForm" method="post" action="api/comida-mesa/consultar-cpf.php">
                    <div class="modal-body">
                        <div class="alert-soft success mb-3"><i class="bi bi-info-circle"></i><div>Consulte o CPF para localizar inscrição existente ou iniciar cadastro.</div></div>
                        <input type="hidden" name="_csrf" value="<?= e($cpfToken) ?>">
                        <input type="hidden" name="competencia_id" value="<?= e($currentCompetenceId) ?>">
                        <div class="mb-3"><label class="form-label" for="cpfLookupInput">CPF</label><input class="form-control" id="cpfLookupInput" name="cpf" type="text" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00" required><div class="invalid-feedback">Informe um CPF com 11 números.</div></div>
                        <div id="cpfLookupResult" aria-live="polite"></div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit" data-cpf-submit><i class="bi bi-search"></i>Consultar</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="registrationFormModal" tabindex="-1" aria-labelledby="registrationFormTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form id="registrationForm" action="api/comida-mesa/salvar-cadastro.php" method="post" novalidate>
            <div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-person-vcard"></i>Inscrição</div><h2 class="modal-title fs-5" id="registrationFormTitle">Nova inscrição</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
            <div class="modal-body">
                <input type="hidden" name="_csrf" value="<?= e($frontendContext['csrf']['salvarCadastro']) ?>"><input type="hidden" name="inscricao_id"><input type="hidden" name="versao_atualizacao">
                <div data-form-alert></div>
                <h3 class="fs-6">Responsável</h3><div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label">Nome</label><input class="form-control" name="nome" required></div><div class="col-md-3"><label class="form-label">CPF</label><input class="form-control" name="cpf" inputmode="numeric" required></div><div class="col-md-3"><label class="form-label">Telefone</label><input class="form-control" name="telefone" inputmode="tel" required></div><div class="col-md-3"><label class="form-label">NIS</label><input class="form-control" name="nis"></div><div class="col-md-3"><label class="form-label">RG</label><input class="form-control" name="rg"></div><div class="col-md-3"><label class="form-label">Nascimento</label><input class="form-control" name="data_nascimento" type="date"></div><div class="col-md-3"><label class="form-label">E-mail</label><input class="form-control" name="email" type="email"></div></div>
                <h3 class="fs-6">Família</h3><div class="row g-3 mb-3"><div class="col-md-3"><label class="form-label">Zona</label><select class="form-select" name="zona"><option value="urbana">Urbana</option><option value="rural">Rural</option></select></div><div class="col-md-5"><label class="form-label">Logradouro</label><input class="form-control" name="logradouro"></div><div class="col-md-2"><label class="form-label">Número</label><input class="form-control" name="numero"></div><div class="col-md-2"><label class="form-label">CEP</label><input class="form-control" name="cep"></div><div class="col-md-4"><label class="form-label">Bairro</label><input class="form-control" name="bairro"></div><div class="col-md-4"><label class="form-label">Comunidade</label><input class="form-control" name="comunidade"></div><div class="col-md-4"><label class="form-label">Complemento</label><input class="form-control" name="complemento"></div><div class="col-md-6"><label class="form-label">Ponto de referência</label><input class="form-control" name="ponto_referencia"></div><div class="col-md-3"><label class="form-label">Membros</label><input class="form-control" name="quantidade_membros" type="number" min="1" value="1"></div><div class="col-md-3"><label class="form-label">Renda familiar</label><input class="form-control" name="renda_familiar" inputmode="decimal"></div></div>
                <h3 class="fs-6">Inscrição</h3><div class="row g-3"><div class="col-md-3"><label class="form-label">Polo</label><select class="form-select" name="polo_id"><option value="">Sem polo</option><?php foreach ($poles as $pole): ?><option value="<?= e($pole['id']) ?>"><?= e($pole['nome']) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Situação</label><select class="form-select" name="status"><?php foreach ($programStatuses as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Prioridade</label><select class="form-select" name="prioridade"><option value="normal">Normal</option><option value="alta">Alta</option><option value="baixa">Baixa</option></select></div><div class="col-md-3"><label class="form-label">Data da inscrição</label><input class="form-control" name="data_inscricao" type="date" value="<?= e(date('Y-m-d')) ?>"></div><div class="col-md-6"><label class="form-label">Motivo suspensão/bloqueio</label><input class="form-control" name="motivo_suspensao"></div><div class="col-md-6"><label class="form-label">Observação</label><textarea class="form-control" name="observacao" rows="2"></textarea></div></div>
            </div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i>Salvar</button></div>
        </form></div></div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailTitle" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-eye"></i>Detalhes</div><h2 class="modal-title fs-5" id="detailTitle">Detalhes da família</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body" data-detail-content><div class="state-panel show"><i class="bi bi-hourglass-split"></i><h3>Carregando</h3></div></div></div></div></div>

    <div class="modal fade" id="deliveryModal" tabindex="-1" aria-labelledby="deliveryTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form id="deliveryForm" action="api/comida-mesa/registrar-entrega.php" method="post" novalidate>
                    <div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-basket2"></i>Entrega</div><h2 class="modal-title fs-5" id="deliveryTitle">Registrar entrega</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="_csrf" value="<?= e($frontendContext['csrf']['registrarEntrega']) ?>">
                        <input type="hidden" name="inscricao_id">
                        <input type="hidden" name="competencia_id" value="<?= e($currentCompetenceId) ?>">
                        <div data-form-alert></div>
                        <div class="alert-soft info mb-3"><i class="bi bi-info-circle"></i><div><strong data-delivery-family>Família</strong><br><span data-delivery-summary><?= e($competenceLabel) ?></span></div></div>
                        <dl class="small mb-3">
                            <dt>Responsável</dt><dd data-delivery-responsible>Não informado</dd>
                            <dt>Código familiar</dt><dd data-delivery-code>Não informado</dd>
                            <dt>Competência</dt><dd><?= e($competenceLabel) ?></dd>
                            <dt>Polo</dt><dd data-delivery-pole>Não informado</dd>
                        </dl>
                        <div class="mb-3"><label class="form-label">Nome do recebedor</label><input class="form-control" name="recebedor_nome" required></div>
                        <div class="mb-3"><label class="form-label">CPF do recebedor</label><input class="form-control" name="recebedor_cpf" inputmode="numeric"></div>
                        <div class="mb-3"><label class="form-label">Parentesco</label><input class="form-control" name="recebedor_parentesco"></div>
                        <div class="mb-3"><label class="form-label">Observação</label><textarea class="form-control" name="observacao" rows="2"></textarea></div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit" data-delivery-submit><i class="bi bi-check2"></i>Confirmar entrega</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cancelDeliveryModal" tabindex="-1" aria-labelledby="cancelDeliveryTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="cancelDeliveryForm" action="api/comida-mesa/cancelar-entrega.php" method="post" novalidate>
                    <div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-x-circle"></i>Cancelamento</div><h2 class="modal-title fs-5" id="cancelDeliveryTitle">Cancelar entrega</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="_csrf" value="<?= e($frontendContext['csrf']['cancelarEntrega']) ?>">
                        <input type="hidden" name="inscricao_id">
                        <input type="hidden" name="competencia_id" value="<?= e($currentCompetenceId) ?>">
                        <div data-form-alert></div>
                        <dl class="small mb-3">
                            <dt>Responsável</dt><dd data-cancel-responsible>Não informado</dd>
                            <dt>Código familiar</dt><dd data-cancel-code>Não informado</dd>
                            <dt>Competência</dt><dd><?= e($competenceLabel) ?></dd>
                            <dt>Polo</dt><dd data-cancel-pole>Não informado</dd>
                            <dt>Data da entrega</dt><dd data-cancel-date>Não informado</dd>
                            <dt>Operador</dt><dd data-cancel-operator>Não informado</dd>
                        </dl>
                        <label class="form-label">Motivo</label>
                        <textarea class="form-control" name="motivo" rows="4" minlength="10" maxlength="255" required></textarea>
                    </div>
                    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn btn-danger" type="submit"><i class="bi bi-x-lg"></i>Cancelar entrega</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="competenceModal" tabindex="-1" aria-labelledby="competenceTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="competenceForm" action="api/comida-mesa/salvar-competencia.php" method="post" novalidate>
                    <div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-calendar-event"></i>Competência</div><h2 class="modal-title fs-5" id="competenceTitle">Nova competência</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="_csrf" value="<?= e($frontendContext['csrf']['salvarCompetencia']) ?>">
                        <input type="hidden" name="competencia_id" value="">
                        <div data-form-alert></div>
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label">Mês</label><input class="form-control" name="mes" type="number" min="1" max="12" value="<?= e(date('n')) ?>"></div>
                            <div class="col-6"><label class="form-label">Ano</label><input class="form-control" name="ano" type="number" min="2020" value="<?= e(date('Y')) ?>"></div>
                            <div class="col-12"><label class="form-label">Situação</label><select class="form-select" name="status"><option value="planejada">Planejada</option><option value="aberta">Aberta</option><option value="encerrada">Encerrada</option><option value="cancelada">Cancelada</option></select></div>
                            <div class="col-6"><label class="form-label">Início</label><input class="form-control" name="inicio_entregas" type="date"></div>
                            <div class="col-6"><label class="form-label">Fim</label><input class="form-control" name="fim_entregas" type="date"></div>
                            <div class="col-12"><label class="form-label">Observação</label><textarea class="form-control" name="observacao" rows="2"></textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i>Salvar</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="documentModal" tabindex="-1" aria-labelledby="documentTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form id="documentForm" action="api/comida-mesa/enviar-documento.php" method="post" enctype="multipart/form-data" novalidate><div class="modal-header"><div><div class="eyebrow mb-1"><i class="bi bi-paperclip"></i>Documento</div><h2 class="modal-title fs-5" id="documentTitle">Enviar documento</h2></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><input type="hidden" name="_csrf" value="<?= e($frontendContext['csrf']['enviarDocumento']) ?>"><input type="hidden" name="inscricao_id"><div data-form-alert></div><div class="mb-3"><label class="form-label">Tipo</label><input class="form-control" name="tipo" required></div><div class="mb-3"><label class="form-label">Descrição</label><input class="form-control" name="descricao"></div><div class="mb-3"><label class="form-label">Arquivo</label><input class="form-control" name="arquivo" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" required></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-upload"></i>Enviar</button></div></form></div></div></div>

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
window.SIGAS_CONTEXT = <?= json_encode($frontendContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/comida-mesa.js"></script>
</body>
</html>
