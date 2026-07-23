<?php

declare(strict_types=1);

use App\Catalog\Entity\Product;
use App\Catalog\Entity\ServiceDefinition;
use App\CRM\Entity\Client;
use App\ServiceOrder\Entity\ServiceOrder;
use App\Workforce\Entity\Employee;

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/os-action-common.php';

$orderService = $application->serviceOrderManagement();
$clientService = $application->clientManagement();
$employeeService = $application->employeeManagement();
$productService = $application->productManagement();
$catalogService = $application->serviceManagement();

$allServices = $catalogService->listServices();
$services = array_values(array_filter(
    $allServices,
    static fn(ServiceDefinition $service): bool => $service->status() === 'ativo'
));
$serviceCategories = array_values(array_unique(array_filter(array_map(
    static fn(ServiceDefinition $service): string => trim((string) $service->category()),
    $allServices
))));
sort($serviceCategories, SORT_NATURAL | SORT_FLAG_CASE);
$allowedStatusFilters = ['', 'exceto_canceladas', 'rascunho', 'aberta', 'aguardando_agendamento', 'agendada', 'em_deslocamento', 'em_execucao', 'aguardando_peca', 'finalizada', 'cancelada'];
$statusFilter = trim((string) ($_GET['status'] ?? ''));
if (!in_array($statusFilter, $allowedStatusFilters, true)) $statusFilter = '';
$serviceIdFilter = trim((string) ($_GET['service_id'] ?? ''));
$allowedServiceIds = array_map(static fn(ServiceDefinition $service): string => (string) $service->id(), $allServices);
if ($serviceIdFilter !== '' && !in_array($serviceIdFilter, $allowedServiceIds, true)) $serviceIdFilter = '';
$serviceCategoryFilter = trim((string) ($_GET['service_category'] ?? ''));
if ($serviceCategoryFilter !== '' && !in_array($serviceCategoryFilter, $serviceCategories, true)) $serviceCategoryFilter = '';

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'client_id' => trim((string) ($_GET['client_id'] ?? '')),
    'employee_id' => trim((string) ($_GET['employee_id'] ?? '')),
    'status' => $statusFilter,
    'priority' => trim((string) ($_GET['priority'] ?? '')),
    'service_id' => $serviceIdFilter,
    'service_category' => $serviceCategoryFilter,
];

$orders = $orderService->listOrders($filters);
$teamsByOrder = $orderService->teamMembersForOrders($orders);
$summary = $orderService->orderSummary();
$clients = $clientService->listClients();
$employees = $employeeService->listEmployees();
$products = $productService->listProducts(['status' => 'ativo']);
$availableBudgets = $orderService->availableApprovedBudgets();
$recovery = os_consume_form_recovery();
$postCompletionPaymentPrompt = os_consume_post_completion_payment_prompt();

$canCreate = $authorization->can('os.criar');
$canEdit = $authorization->can('os.editar');
$canSchedule = $authorization->can('os.agendar');
$canTeam = $authorization->can('os.alterar_equipe');
$canStatus = $authorization->can('os.alterar_status');
$canFinalize = $authorization->can('os.finalizar');
$canPayOrder = $authorization->can('contas_receber.registrar_pagamento') && $authorization->can('recibo.emitir');
$canCancel = $authorization->can('os.cancelar');
$canReopen = $authorization->can('os.reabrir');
$canViewValues = $authorization->can('os.visualizar_valores');
$canConvertBudget = $authorization->can('orcamento.converter_os');
$canProof = $authorization->can('os.emitir_comprovante');
$canPrint = $authorization->can('os.imprimir');
$canReverse = $authorization->can('os.estornar');
$canDelete = $authorization->can('os.excluir');
$canIssueReceipt = $authorization->can('recibo.emitir');
$canReprintReceipt = $authorization->can('recibo.reimprimir');
$paymentsByOrder = ($canIssueReceipt || $canReprintReceipt)
    ? $application->receiptService()->listActivePaymentsForOrders(array_map(
        static fn(ServiceOrder $order): int => $order->id(),
        $orders
    ))
    : [];
$receivableBalances = $canPayOrder
    ? $application->accountsReceivableManagement()->balancesForOrders(array_map(
        static fn(ServiceOrder $order): int => $order->id(),
        $orders
    ))
    : [];

function os_label_status(string $status): string
{
    return [
        'rascunho' => 'Rascunho',
        'aberta' => 'Aberta',
        'aguardando_agendamento' => 'Aguardando agendamento',
        'agendada' => 'Agendada',
        'em_deslocamento' => 'Em deslocamento',
        'em_execucao' => 'Em execucao',
        'aguardando_peca' => 'Aguardando peca',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
    ][$status] ?? $status;
}

function os_badge_status(string $status): string
{
    return [
        'rascunho' => 'gray',
        'aberta' => 'blue',
        'aguardando_agendamento' => 'amber',
        'agendada' => 'teal',
        'em_deslocamento' => 'purple',
        'em_execucao' => 'green',
        'aguardando_peca' => 'amber',
        'finalizada' => 'green',
        'cancelada' => 'red',
    ][$status] ?? 'gray';
}

function os_status_filter_url(array $filters, string $status): string
{
    $query = array_filter($filters, static fn(mixed $value): bool => is_scalar($value) && (string) $value !== '');
    if ($status === '') unset($query['status']);
    else $query['status'] = $status;
    return 'ordens-servico.php' . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
}

$statusFilterButtons = [
    ['', 'Todos', 'all', ''],
    ['rascunho', 'Rascunho', 'gray', ''],
    ['aberta', 'Aberta', 'blue', ''],
    ['aguardando_agendamento', 'Aguardando agendamento', 'amber', ''],
    ['agendada', 'Agendada', 'teal', '--status-filter-color:var(--teal);--status-filter-active:var(--teal);--status-filter-bg:#e6fffb'],
    ['em_deslocamento', 'Em deslocamento', 'purple', '--status-filter-color:var(--purple);--status-filter-active:var(--purple);--status-filter-bg:#f4efff'],
    ['em_execucao', 'Em execução', 'green', ''],
    ['aguardando_peca', 'Aguardando peça', 'amber', ''],
    ['finalizada', 'Finalizada', 'green', ''],
    ['cancelada', 'Cancelada', 'red', ''],
];

function os_money_fmt(string $value): string
{
    return money($value);
}

/** @param array<string,mixed> $payment */
function os_payment_label(array $payment): string
{
    $form = str_replace('_', ' ', (string) ($payment['forma_pagamento'] ?? 'pagamento'));
    return ucfirst($form) . ' - ' . os_money_fmt((string) ($payment['valor'] ?? '0'));
}

function os_location(ServiceOrder $order): string
{
    $address = trim(implode(', ', array_filter([
        $order->clientAddress(),
        $order->clientNumber(),
        $order->clientDistrict(),
        $order->clientCity(),
        $order->clientState(),
    ], static fn(?string $value): bool => $value !== null && $value !== '')));

    return $order->equipmentLocation() ?: ($address !== '' ? $address : 'Nao informado');
}

function os_schedule_cell(ServiceOrder $order): string
{
    if ($order->scheduledStart() === null || $order->scheduledEnd() === null) {
        return 'Nao agendado';
    }
    try {
        return h((new DateTimeImmutable($order->scheduledStart()))->format('d/m/Y'))
            . '<br><small>'
            . h((new DateTimeImmutable($order->scheduledStart()))->format('H:i'))
            . ' as '
            . h((new DateTimeImmutable($order->scheduledEnd()))->format('H:i'))
            . '</small>';
    } catch (Throwable) {
        return 'Nao agendado';
    }
}

function os_team_cell(array $members): string
{
    if ($members === []) {
        return '<span class="text-muted">Equipe nao definida</span>';
    }
    return implode('', array_map(static fn($member): string => '<div>' . h($member->displayLine()) . '</div>', $members));
}

function os_contact_phone(ServiceOrder $order): ?string
{
    $whatsapp = trim((string) $order->clientWhatsapp());
    if ($whatsapp !== '') return $whatsapp;
    $phone = trim((string) $order->clientPhone());
    return $phone !== '' ? $phone : null;
}

function os_whatsapp_url(ServiceOrder $order): ?string
{
    $phone = os_contact_phone($order);
    if ($phone === null) return null;
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) === 10 || strlen($digits) === 11) {
        $digits = '55' . $digits;
    }
    if (!str_starts_with($digits, '55') || !in_array(strlen($digits), [12, 13], true)) {
        return null;
    }
    $message = 'Olá, ' . $order->clientName() . '. Entramos em contato sobre a ordem de serviço ' . $order->displayNumber() . '.';
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}

function os_select_options(array $items, string $selected = '', bool $onlyActive = false): void
{
    foreach ($items as $item) {
        if ($onlyActive && empty($item['active']) && (string) $item['id'] !== $selected) {
            continue;
        }
        echo '<option value="' . h((string) $item['id']) . '" ' . ($selected === (string) $item['id'] ? 'selected' : '') . '>' . h((string) $item['name']) . '</option>';
    }
}

$clientOptions = array_map(static fn(Client $client): array => ['id' => $client->id(), 'name' => $client->name(), 'active' => $client->status() === 'ativo'], $clients);
$employeeOptions = array_map(static fn(Employee $employee): array => ['id' => $employee->id(), 'name' => $employee->displayCode() . ' - ' . $employee->name()], $employees);
$serviceOptions = array_map(static fn(ServiceDefinition $service): array => ['id' => $service->id(), 'name' => $service->name(), 'description' => $service->description() ?? $service->name(), 'unit' => 'un', 'value' => $service->value()], $services);
$productOptions = array_map(static fn(Product $product): array => ['id' => $product->id(), 'name' => $product->name(), 'description' => $product->description() ?? $product->name(), 'unit' => $product->unit(), 'value' => $product->salePrice()], $products);
?>

<div class="page-body service-orders-page">
<?php metric_grid([
    ['OS abertas', (string) ($summary['open_count'] ?? 0), 'bi-clipboard-check', '#2563EB', 'rascunho e aberta'],
    ['Aguardando agendamento', (string) ($summary['waiting_schedule'] ?? 0), 'bi-clock-history', '#D97706', 'sem horario'],
    ['Agendadas', (string) ($summary['scheduled'] ?? 0), 'bi-calendar2-check', '#0F766E', 'com equipe e horario'],
    ['Em atendimento', (string) ($summary['in_service'] ?? 0), 'bi-tools', '#16A34A', 'deslocamento/execucao'],
    ['Aguardando peca', (string) ($summary['waiting_part'] ?? 0), 'bi-box-seam', '#7C3AED', 'pendentes'],
    ['Finalizadas no mes', (string) ($summary['finished_month'] ?? 0), 'bi-check2-circle', '#15803D', 'concluidas'],
]); ?>

<form class="filter-bar" method="get" action="ordens-servico.php" data-live-filter="service-orders" data-live-regions="metrics results">
    <div class="search-wrap"><i class="bi bi-search"></i><input class="search-input" type="search" name="search" value="<?= h($filters['search']) ?>" placeholder="Pesquisar OS, cliente, local ou funcionario"></div>
    <input class="filter-select input-date" type="date" name="date_from" value="<?= h($filters['date_from']) ?>" aria-label="Data inicial">
    <input class="filter-select input-date" type="date" name="date_to" value="<?= h($filters['date_to']) ?>" aria-label="Data final">
    <select class="filter-select" name="client_id" aria-label="Cliente"><option value="">Todos os clientes</option><?php foreach ($clients as $client): ?><option value="<?= h((string) $client->id()) ?>" <?= $filters['client_id'] === (string) $client->id() ? 'selected' : '' ?>><?= h($client->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="employee_id" aria-label="Técnico"><option value="">Todos os técnicos</option><?php foreach ($employees as $employee): ?><option value="<?= h((string) $employee->id()) ?>" <?= $filters['employee_id'] === (string) $employee->id() ? 'selected' : '' ?>><?= h($employee->displayCode() . ' - ' . $employee->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="service_category" aria-label="Natureza do serviço"><option value="">Todas as naturezas</option><?php foreach ($serviceCategories as $category): ?><option value="<?= h($category) ?>" <?= $filters['service_category'] === $category ? 'selected' : '' ?>><?= h($category) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="service_id" aria-label="Serviço"><option value="">Todos os serviços</option><?php foreach ($allServices as $service): ?><option value="<?= h((string) $service->id()) ?>" <?= $filters['service_id'] === (string) $service->id() ? 'selected' : '' ?>><?= h($service->displayCode() . ' - ' . $service->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="status" aria-label="Status"><option value="">Todos os status</option><option value="exceto_canceladas" <?= $filters['status'] === 'exceto_canceladas' ? 'selected' : '' ?>>Todos exceto canceladas</option><?php foreach (['rascunho','aberta','aguardando_agendamento','agendada','em_deslocamento','em_execucao','aguardando_peca','finalizada','cancelada'] as $status): ?><option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= h(os_label_status($status)) ?></option><?php endforeach; ?></select>
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" href="ordens-servico.php" data-live-filter-clear><i class="bi bi-x-lg"></i> Limpar</a>
</form>

<section class="panel" data-live-region="results">
    <div class="panel-header budget-panel-header">
        <div class="budget-panel-heading">
            <div class="panel-title"><i class="bi bi-wrench-adjustable-circle"></i>Ordens de Serviço</div>
            <nav class="budget-status-filters" aria-label="Filtrar ordens de serviço por status">
                <?php foreach ($statusFilterButtons as [$statusValue, $statusLabel, $statusClass, $statusStyle]): ?>
                    <?php $isActiveStatus = $filters['status'] === $statusValue; ?>
                    <a
                        class="budget-status-filter budget-status-filter-<?= h($statusClass) ?> js-os-status-filter<?= $isActiveStatus ? ' active' : '' ?>"
                        href="<?= h(os_status_filter_url($filters, $statusValue)) ?>"
                        data-status="<?= h($statusValue) ?>"
                        <?= $statusStyle !== '' ? 'style="' . h($statusStyle) . '"' : '' ?>
                        <?= $isActiveStatus ? 'aria-current="true"' : '' ?>
                    ><?= h($statusLabel) ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php if ($canCreate): ?><button class="btn-filter btn-filter-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-os"><i class="bi bi-plus-lg"></i> Nova OS</button><?php endif; ?>
    </div>
    <?php if ($orders === []): ?>
        <?php empty_state('Nenhuma OS encontrada', 'Cadastre uma OS ou ajuste os filtros.'); ?>
    <?php else: ?>
        <div class="table-panel-wrap">
            <table class="os-table service-orders-table">
                <thead><tr><th>OS</th><th>Cliente</th><th>Contato</th><th>Local</th><th>Funcionarios</th><th>Data do servico</th><?php if ($canViewValues): ?><th>Valor total</th><?php endif; ?><th>Status</th><th>Acoes</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php $team = $teamsByOrder[$order->id()] ?? []; $contactPhone = os_contact_phone($order); $whatsappUrl = os_whatsapp_url($order); $orderPayments = $paymentsByOrder[$order->id()] ?? []; $receivable = $receivableBalances[$order->id()] ?? null; ?>
                    <tr>
                        <td>
                            <strong><?= h($order->displayNumber()) ?></strong>
                        </td>
                        <td>
                            <?= h($order->clientName()) ?>
                            <br><small class="text-muted">CLI-<?= h(str_pad((string) $order->clientId(), 6, '0', STR_PAD_LEFT)) ?></small>
                        </td>
                        <td class="os-contact-cell">
                            <?php if ($contactPhone === null): ?>-
                            <?php else: ?><?= h($contactPhone) ?><?php endif; ?>
                        </td>
                        <td><?= h(os_location($order)) ?></td>
                        <td><?= os_team_cell($team) ?></td>
                        <td><?= os_schedule_cell($order) ?></td>
                        <?php if ($canViewValues): ?><td><strong><?= h(os_money_fmt($order->total())) ?></strong></td><?php endif; ?>
                        <td><span class="badge-soft badge-<?= h(os_badge_status($order->status())) ?>"><?= h($order->displayStatus()) ?></span></td>
                        <td class="table-actions-cell">
                            <div class="dropdown table-action-dropdown">
                                <button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acoes da OS <?= h($order->displayNumber()) ?>"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button class="dropdown-item js-os-view" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os-view"><i class="bi bi-eye"></i> Visualizar</button></li>
                                    <?php if ($whatsappUrl !== null): ?><li><a class="dropdown-item" href="<?= h($whatsappUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp" aria-hidden="true"></i> Chamar no WhatsApp</a></li><?php endif; ?>
                                    <?php if ($canEdit && !in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item js-os-edit" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os"><i class="bi bi-pencil"></i> Editar</button></li><?php endif; ?>
                                    <?php if (($canTeam || $canSchedule) && !in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item js-os-team" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-team='<?= h(json_encode(array_map(static fn($member): array => ['employee_id' => $member->employeeId(), 'role' => $member->role(), 'primary' => $member->primary()], $team), JSON_UNESCAPED_UNICODE)) ?>' data-start="<?= h($order->scheduledStart() ?? '') ?>" data-end="<?= h($order->scheduledEnd() ?? '') ?>" data-bs-toggle="modal" data-bs-target="#modal-os-team"><i class="bi bi-people"></i> Definir equipe</button></li><?php endif; ?>
                                    <?php if ($canStatus && $order->status() === 'agendada'): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="start_travel" data-label="Iniciar deslocamento" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-truck"></i> Iniciar deslocamento</button></li><?php endif; ?>
                                    <?php if ($canStatus && in_array($order->status(), ['agendada','em_deslocamento','aguardando_peca'], true)): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="start_execution" data-label="Iniciar execucao" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-play-circle"></i> Iniciar execucao</button></li><?php endif; ?>
                                    <?php if ($canStatus && in_array($order->status(), ['agendada','em_deslocamento','em_execucao'], true)): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="wait_part" data-label="Aguardar peca" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-hourglass-split"></i> Aguardar peca</button></li><?php endif; ?>
                                    <?php if ($canFinalize && in_array($order->status(), ['agendada','em_execucao','aguardando_peca'], true)): ?><li><button class="dropdown-item js-os-finalize" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-order-number="<?= h($order->displayNumber()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os-finalize"><i class="bi bi-check2-circle"></i> Finalizar OS</button></li><?php endif; ?>
                                    <?php if ($canPayOrder && $order->status() === 'finalizada' && is_array($receivable) && in_array((string) ($receivable['status'] ?? ''), ['pendente','parcial','vencida'], true) && (float) ($receivable['saldo'] ?? 0) > 0): ?><li><button class="dropdown-item js-os-pay" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-order-number="<?= h($order->displayNumber()) ?>" data-order-total="<?= h((string) $receivable['saldo']) ?>" data-bs-toggle="modal" data-bs-target="#modal-os-pay"><i class="bi bi-cash-coin"></i> Pagar OS</button></li><?php endif; ?>
                                    <?php if ($canCancel && !in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item text-danger js-os-cancel" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os-cancel"><i class="bi bi-x-circle"></i> Cancelar servico</button></li><?php endif; ?>
                                    <?php if ($canReopen && $order->status() === 'cancelada'): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="reopen" data-label="Reabrir" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-arrow-counterclockwise"></i> Reabrir</button></li><?php endif; ?>
                                    <?php if ($canPrint): ?><li><a class="dropdown-item" href="ordem-servico-imprimir.php?id=<?= h((string) $order->id()) ?>&valores=<?= $canViewValues ? '1' : '0' ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Imprimir / reimprimir OS</a></li><?php endif; ?>
                                    <?php if ($canProof && $order->status() === 'finalizada'): ?><li><a class="dropdown-item" href="ordem-servico-comprovante.php?id=<?= h((string) $order->id()) ?>&valores=0" target="_blank" rel="noopener"><i class="bi bi-file-earmark-text"></i> Comprovante de servico</a></li><?php endif; ?>
                                    <?php foreach ($orderPayments as $payment): ?>
                                        <?php if (!empty($payment['recibo_id']) && $payment['recibo_status'] === 'emitido' && $canReprintReceipt): ?>
                                            <li><a class="dropdown-item" href="recibo-imprimir.php?id=<?= h((string) $payment['recibo_id']) ?>" target="_blank" rel="noopener"><i class="bi bi-receipt-cutoff"></i> Reimprimir recibo: <?= h(os_payment_label($payment)) ?></a></li>
                                        <?php elseif (empty($payment['recibo_id']) && $canIssueReceipt): ?>
                                            <li><button class="dropdown-item js-os-receipt" type="button" data-payment-id="<?= h((string) $payment['id']) ?>" data-order-number="<?= h($order->displayNumber()) ?>" data-payment-label="<?= h(os_payment_label($payment)) ?>" data-bs-toggle="modal" data-bs-target="#modal-os-receipt"><i class="bi bi-receipt-cutoff"></i> Gerar recibo: <?= h(os_payment_label($payment)) ?></button></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if ($canReverse && $order->status() === 'finalizada'): ?><li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger js-os-reverse" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-order-number="<?= h($order->displayNumber()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os-reverse"><i class="bi bi-arrow-counterclockwise"></i> Estornar OS</button></li><?php endif; ?>
                                    <?php if ($canDelete && $order->status() !== 'finalizada'): ?><li><button class="dropdown-item text-danger js-os-delete" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-order-number="<?= h($order->displayNumber()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os-delete"><i class="bi bi-trash3"></i> Excluir OS</button></li><?php endif; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
</div>

<div class="modal fade" id="modal-os" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal js-os-form" method="post" action="actions/os-salvar.php" autocomplete="off">
    <div class="modal-header"><div><h2 class="modal-title fs-5" id="modal-os-title">Nova OS</h2><p class="text-muted small mb-0">Valores e itens sao recalculados no servidor.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="os-id"><div class="alert alert-danger <?= $recovery === null ? 'd-none' : '' ?>" data-os-form-error role="alert"><?= h($recovery['error'] ?? '') ?></div>
      <ul class="nav nav-tabs mb-3" role="tablist"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#os-tab-client" type="button">Cliente</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-tab-items" type="button">Itens</button></li><?php if ($canTeam || $canSchedule): ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-tab-team" type="button">Equipe</button></li><?php endif; ?><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-tab-values" type="button">Valores</button></li></ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="os-tab-client"><section class="form-section"><div class="form-row"><div class="form-group"><label class="form-label">Origem</label><select class="form-control-os" name="creation_mode" id="os-creation-mode"><option value="manual">Criar manualmente</option><?php if ($canConvertBudget): ?><option value="budget">Criar a partir de orcamento aprovado</option><?php endif; ?></select></div><div class="form-group"><label class="form-label">Cliente</label><select class="form-control-os" name="client_id" id="os-client" required><option value="">Selecione</option><?php os_select_options($clientOptions, '', true); ?></select></div><div class="form-group"><label class="form-label">Orcamento aprovado</label><select class="form-control-os" name="budget_id" id="os-budget-id"><option value="">Selecione</option><?php foreach ($availableBudgets as $budget): ?><option value="<?= h((string) $budget['id']) ?>" data-client-id="<?= h((string) $budget['cliente_id']) ?>" data-total="<?= h((string) $budget['total']) ?>" data-summary="<?= h((string) ($budget['servicos_resumo'] ?? '')) ?>"><?= h(($budget['numero'] ?? sprintf('ORC-%06d', (int) $budget['id'])) . ' - ' . $budget['cliente_nome'] . ' - ' . os_money_fmt((string) $budget['total'])) ?></option><?php endforeach; ?></select></div><div class="form-group"><label class="form-label">Status inicial</label><select class="form-control-os" name="status" id="os-status"><option value="rascunho">Rascunho</option><option value="aberta" selected>Aberta</option><option value="aguardando_agendamento">Aguardando agendamento</option><option value="agendada">Agendada</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Prioridade</label><select class="form-control-os" name="priority" id="os-priority"><option value="baixa">Baixa</option><option value="media" selected>Media</option><option value="alta">Alta</option><option value="urgente">Urgente</option></select></div><div class="form-group"><label class="form-label">Tipo do equipamento</label><input class="form-control-os" name="equipment_type" id="os-equipment-type"></div><div class="form-group"><label class="form-label">Marca</label><input class="form-control-os" name="equipment_brand" id="os-equipment-brand"></div><div class="form-group"><label class="form-label">Modelo</label><input class="form-control-os" name="equipment_model" id="os-equipment-model"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Capacidade</label><input class="form-control-os" name="equipment_capacity" id="os-equipment-capacity"></div><div class="form-group"><label class="form-label">Numero de serie</label><input class="form-control-os" name="equipment_serial_number" id="os-equipment-serial-number"></div><div class="form-group"><label class="form-label">Ambiente</label><input class="form-control-os" name="equipment_environment" id="os-equipment-environment"></div><div class="form-group"><label class="form-label">Local</label><input class="form-control-os" name="equipment_location" id="os-equipment-location"></div></div><div class="alert alert-info d-none" id="os-budget-preview"></div></section><section class="form-section"><h3 class="form-section-title">Diagnostico e observacoes</h3><div class="form-row"><div class="form-group"><label class="form-label">Problema relatado</label><textarea class="form-control-os" name="reported_problem" id="os-reported-problem"></textarea></div><div class="form-group"><label class="form-label">Problema identificado</label><textarea class="form-control-os" name="identified_problem" id="os-identified-problem"></textarea></div></div><div class="form-row"><div class="form-group"><label class="form-label">Diagnostico</label><textarea class="form-control-os" name="diagnosis" id="os-diagnosis"></textarea></div><div class="form-group"><label class="form-label">Solucao</label><textarea class="form-control-os" name="solution" id="os-solution"></textarea></div></div><div class="form-row"><div class="form-group"><label class="form-label">Recomendacao</label><textarea class="form-control-os" name="recommendation" id="os-recommendation"></textarea></div><div class="form-group"><label class="form-label">Observacoes internas</label><textarea class="form-control-os" name="internal_notes" id="os-internal-notes"></textarea></div></div></section></div>
        <div class="tab-pane fade" id="os-tab-items"><section class="form-section"><h3 class="form-section-title">Servicos</h3><div class="os-items" data-os-items="servico"></div><button class="btn-filter btn-filter-ghost js-os-add-item" type="button" data-type="servico"><i class="bi bi-plus-lg"></i> Adicionar servico</button></section><section class="form-section"><h3 class="form-section-title">Produtos</h3><div class="os-items" data-os-items="produto"></div><button class="btn-filter btn-filter-ghost js-os-add-item" type="button" data-type="produto"><i class="bi bi-plus-lg"></i> Adicionar produto</button></section><section class="form-section"><h3 class="form-section-title">Outros</h3><div class="os-items" data-os-items="outro"></div><button class="btn-filter btn-filter-ghost js-os-add-item" type="button" data-type="outro"><i class="bi bi-plus-lg"></i> Adicionar outro item</button></section></div>
        <?php if ($canTeam || $canSchedule): ?><div class="tab-pane fade" id="os-tab-team"><section class="form-section"><?php if ($canTeam): ?><input type="hidden" name="team_submitted" value="1"><?php endif; ?><div class="os-team-members" data-team-members data-team-editable="<?= $canTeam ? '1' : '0' ?>"></div><?php if ($canTeam): ?><button class="btn-filter btn-filter-ghost js-os-add-team-member" type="button"><i class="bi bi-plus-lg"></i> Adicionar funcionário</button><?php endif; ?><div class="form-row mt-3"><div class="form-group"><label class="form-label">Início</label><input class="form-control-os" type="datetime-local" name="agendado_inicio" id="os-scheduled-start" <?= $canSchedule ? '' : 'disabled' ?>></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="agendado_fim" id="os-scheduled-end" <?= $canSchedule ? '' : 'disabled' ?>></div></div></section></div><?php endif; ?>
        <div class="tab-pane fade" id="os-tab-values"><section class="form-section"><div class="form-row"><div class="form-group"><label class="form-label">Desconto</label><input class="form-control-os js-os-discount" name="discount" id="os-discount" value="0,00"></div><div class="form-group"><label class="form-label">Acrescimo</label><input class="form-control-os js-os-increase" name="increase" id="os-increase" value="0,00"></div></div><?php if ($canViewValues): ?><div class="summary-box js-os-summary"><div><span>Servicos</span><strong data-summary="servico">R$ 0,00</strong></div><div><span>Produtos</span><strong data-summary="produto">R$ 0,00</strong></div><div><span>Outros</span><strong data-summary="outro">R$ 0,00</strong></div><div class="total"><span>Total</span><strong data-summary="total">R$ 0,00</strong></div></div><?php endif; ?><div class="form-group"><label class="form-label">Observacoes externas</label><textarea class="form-control-os" name="notes" id="os-notes"></textarea></div></section></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar</button></div>
  </form></div>
</div>

<template id="os-item-template"><div class="form-row os-item-row"><input type="hidden" data-field="id"><input type="hidden" data-field="type"><input type="hidden" data-field="origin" value="manual"><input type="hidden" data-field="budget_item_id"><div class="form-group os-reference-wrap"><label class="form-label">Referencia</label><select class="form-control-os" data-field="reference_id"></select></div><div class="form-group"><label class="form-label">Descricao</label><input class="form-control-os" data-field="description" required></div><div class="form-group"><label class="form-label">Unidade</label><input class="form-control-os" data-field="unit" value="un" required></div><div class="form-group"><label class="form-label">Qtd.</label><input class="form-control-os" data-field="quantity" value="1" required></div><div class="form-group"><label class="form-label">Valor unit.</label><input class="form-control-os" data-field="unit_price" value="0,00" required></div><div class="form-group"><label class="form-label">Desconto</label><input class="form-control-os" data-field="discount" value="0,00"></div><div class="form-group"><label class="form-label">Subtotal</label><input class="form-control-os" data-field="subtotal" readonly></div><div class="form-group"><label class="form-label">&nbsp;</label><button class="btn-filter btn-filter-ghost js-os-remove-item" type="button"><i class="bi bi-trash"></i></button></div></div></template>
<template id="os-team-member-template"><div class="form-row os-team-member-row"><div class="form-group"><label class="form-label">Funcionário</label><select class="form-control-os" data-team-field="funcionario_id"><option value="">Selecione</option></select></div><div class="form-group"><label class="form-label">Função</label><select class="form-control-os" data-team-field="funcao"><option value="Responsável técnico">Responsável técnico</option><option value="Técnico">Técnico</option><option value="Instalador">Instalador</option><option value="Auxiliar">Auxiliar</option><option value="Eletricista">Eletricista</option><option value="Supervisor">Supervisor</option><option value="Motorista">Motorista</option><option value="Outro">Outro</option></select></div><div class="form-group"><label class="form-label">Principal</label><div class="form-check mt-2"><input class="form-check-input" type="radio" data-team-field="principal" value="1"><span class="form-check-label">Responsável</span></div></div><div class="form-group"><label class="form-label">&nbsp;</label><button class="btn-filter btn-filter-ghost js-os-remove-team-member" type="button" aria-label="Remover funcionário"><i class="bi bi-trash"></i></button></div></div></template>

<div class="modal fade" id="modal-os-view" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content visual-modal"><div class="modal-header"><div><h2 class="modal-title fs-5">Dados da OS</h2><p class="text-muted small mb-0" id="os-view-subtitle"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><section class="form-section"><h3 class="form-section-title">Resumo</h3><div class="summary-box" id="os-view-summary"></div></section><section class="form-section"><h3 class="form-section-title">Itens</h3><div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Tipo</th><th>Descricao</th><th>Qtd.</th><th>Valor</th><th>Subtotal</th></tr></thead><tbody id="os-view-items"></tbody></table></div></section></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Fechar</button></div></div></div></div>
<div class="modal fade" id="modal-os-team" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/os-equipe-agendamento.php"><div class="modal-header"><h2 class="modal-title fs-5">Equipe e agendamento</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="os-team-id"><?php if ($canTeam): ?><input type="hidden" name="team_submitted" value="1"><?php endif; ?><div class="os-team-members" data-team-members data-team-editable="<?= $canTeam ? '1' : '0' ?>"></div><?php if ($canTeam): ?><button class="btn-filter btn-filter-ghost js-os-add-team-member" type="button"><i class="bi bi-plus-lg"></i> Adicionar funcionário</button><?php endif; ?><div class="form-row mt-3"><div class="form-group"><label class="form-label">Início</label><input class="form-control-os" type="datetime-local" name="agendado_inicio" id="os-team-start" <?= $canSchedule ? '' : 'disabled' ?>></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="agendado_fim" id="os-team-end" <?= $canSchedule ? '' : 'disabled' ?>></div></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>
<div class="modal fade" id="modal-os-finalize" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/os-finalizar.php"><div class="modal-header"><div><h2 class="modal-title fs-5">Finalizar OS</h2><p class="text-muted small mb-0">Confirme a execução. O valor ficará em Contas a Receber para pagamento posterior.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="os-finalize-id"><div class="alert alert-info" role="note"><i class="bi bi-info-circle"></i> Finalizar não registra dinheiro no Caixa. Use <strong>Pagar OS</strong> depois da finalização.</div><section class="form-section"><h3 class="form-section-title">Execução</h3><div class="form-row"><input type="hidden" name="execution_items[0][type]" value="servico"><div class="form-group"><label class="form-label">Serviço executado</label><input class="form-control-os" name="execution_items[0][description]" required></div><div class="form-group"><label class="form-label">Quantidade</label><input class="form-control-os" name="execution_items[0][quantity]" value="1" inputmode="decimal" required></div><div class="form-group"><label class="form-label">Valor unitário</label><input class="form-control-os" name="execution_items[0][unit_price]" value="0,00" inputmode="decimal" required></div><div class="form-group"><label class="form-label">Desconto</label><input class="form-control-os" name="execution_items[0][discount]" value="0,00" inputmode="decimal"></div></div></section><section class="form-section"><h3 class="form-section-title">Cobrança</h3><div class="form-row"><div class="form-group"><label class="form-label">Vencimento</label><input class="form-control-os" type="date" name="vencimento_em"></div><div class="form-group"><label class="form-label">Próximo lembrete</label><input class="form-control-os" type="date" name="proximo_lembrete_em"></div></div><div class="form-group"><label class="form-label">Observação da finalização</label><textarea class="form-control-os" name="observacao" maxlength="1000"></textarea></div><div class="form-group"><label class="form-label">Observação do saldo</label><textarea class="form-control-os" name="saldo_observacao" maxlength="1000"></textarea></div></section></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check2-circle"></i> Finalizar OS</button></div></form></div></div>
<?php if ($canPayOrder): ?><div class="modal fade" id="modal-os-pay" tabindex="-1" aria-hidden="true" aria-labelledby="os-pay-title" aria-describedby="os-pay-subtitle"><div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/os-pagar.php" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5" id="os-pay-title">Pagamento da OS</h2><p class="text-muted small mb-0" id="os-pay-subtitle">A conclusão já foi salva. Agora informe se o cliente pagou.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="os-pay-id"><input type="hidden" name="payment_token" id="os-pay-token"><div class="alert alert-success" id="os-pay-completion" role="status" hidden></div><div id="os-pay-question" hidden><p class="fs-5 mb-2">O cliente já pagou este serviço?</p><p class="text-muted mb-0">Se ainda não pagou, a OS continuará concluída e o valor ficará pendente em Contas a Receber.</p></div><div id="os-pay-fields"><div class="alert alert-info" id="os-pay-summary" role="status"></div><div class="alert alert-danger" id="os-pay-error" role="alert" hidden></div><div class="form-row"><div class="form-group"><label class="form-label" for="os-pay-value">Valor recebido</label><input class="form-control-os" id="os-pay-value" name="valor" inputmode="decimal" required></div><div class="form-group"><label class="form-label" for="os-pay-method">Forma de pagamento</label><select class="form-control-os" id="os-pay-method" name="forma_pagamento" required><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="cartao_debito">Cartão de débito</option><option value="cartao_credito">Cartão de crédito</option><option value="boleto">Boleto já compensado</option><option value="transferencia">Transferência</option><option value="outro">Outro</option></select></div><div class="form-group" id="os-pay-installments-group" hidden><label class="form-label" for="os-pay-installments">Quantidade de parcelas</label><input class="form-control-os" id="os-pay-installments" name="quantidade_parcelas" type="number" min="1" max="60" value="1"></div></div><div class="alert alert-warning py-2" id="os-pay-boleto-warning" role="note" hidden><i class="bi bi-exclamation-triangle"></i> Registre boleto somente quando o pagamento já estiver compensado. Boleto emitido ou aguardando retorno deve permanecer como não pago.</div><div class="form-group mb-0"><label class="form-label" for="os-pay-notes">Observação</label><textarea class="form-control-os" id="os-pay-notes" name="observacao" maxlength="255" rows="3"></textarea></div></div></div><div class="modal-footer" id="os-pay-question-actions" hidden><button class="btn-modal-cancel" id="os-pay-leave-pending" type="button" data-bs-dismiss="modal">Não, deixar pendente</button><button class="btn-modal-save" id="os-pay-confirm-paid" type="button"><i class="bi bi-cash-coin"></i> Sim, registrar pagamento</button></div><div class="modal-footer" id="os-pay-form-actions"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-cash-coin"></i> Confirmar pagamento e gerar recibo</button></div></form></div></div></div><?php endif; ?>
<div class="modal fade" id="modal-os-cancel" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/os-cancelar.php"><div class="modal-header"><h2 class="modal-title fs-5">Cancelar servico</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="os-cancel-id"><div class="form-row"><div class="form-group"><label class="form-label">Destino do orcamento</label><select class="form-control-os" name="opcao" required><option value="definitivo">Cancelar definitivamente</option><option value="liberar_orcamento">Cancelar e liberar o orcamento</option><option value="criar_substituta">Cancelar e criar OS substituta</option></select></div><div class="form-group"><label class="form-label">Motivo</label><input class="form-control-os" name="motivo" required></div></div><div class="form-group"><label class="form-label">Observacao</label><textarea class="form-control-os" name="observacao"></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div>
<div class="modal fade" id="modal-os-status" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/os-status.php"><div class="modal-header"><h2 class="modal-title fs-5" id="os-status-title">Alterar status</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="os-status-id"><input type="hidden" name="operation" id="os-status-operation"><p id="os-status-message"></p></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div>

<?php if ($canReverse): ?><div class="modal fade" id="modal-os-reverse" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/os-estornar.php"><div class="modal-header"><h2 class="modal-title fs-5">Estornar OS</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="os-reverse-id"><p>O estorno da <strong id="os-reverse-number"></strong> desfará a finalização e criará lançamentos compensatórios de estoque e caixa, preservando o histórico.</p><div class="form-group"><label class="form-label" for="os-reverse-reason">Motivo do estorno</label><textarea class="form-control-os" id="os-reverse-reason" name="motivo" maxlength="255" required></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Confirmar estorno</button></div></form></div></div><?php endif; ?>

<?php if ($canDelete): ?><div class="modal fade" id="modal-os-delete" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/os-excluir.php"><div class="modal-header"><h2 class="modal-title fs-5">Excluir OS</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="os-delete-id"><p>A <strong id="os-delete-number"></strong> será removida das telas por exclusão lógica. OS finalizada precisa ser estornada antes.</p><div class="form-group"><label class="form-label" for="os-delete-reason">Motivo da exclusão</label><textarea class="form-control-os" id="os-delete-reason" name="motivo" maxlength="255" required></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Excluir OS</button></div></form></div></div><?php endif; ?>

<?php if ($canIssueReceipt): ?><div class="modal fade" id="modal-os-receipt" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/recibo-emitir.php" target="_blank"><div class="modal-header"><h2 class="modal-title fs-5">Gerar recibo de pagamento</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><input type="hidden" name="pagamento_id" id="os-receipt-payment-id"><p>Gerar recibo da <strong id="os-receipt-order-number"></strong> referente a <strong id="os-receipt-payment-label"></strong>?</p><p class="text-muted small mb-0">O documento registra uma fotografia dos dados da empresa, do cliente e do pagamento.</p></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Gerar recibo</button></div></form></div></div><?php endif; ?>

<script type="application/json" id="os-page-data"><?= json_encode(['services' => $serviceOptions, 'products' => $productOptions, 'employees' => $employeeOptions, 'recoveryModal' => $recovery['modal'] ?? ($_GET['modal'] ?? null), 'recoveryData' => $recovery['data'] ?? [], 'recoveryError' => $recovery['error'] ?? null, 'postCompletionPaymentPrompt' => $canPayOrder ? $postCompletionPaymentPrompt : null], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
<script>
document.addEventListener('click', function (event) {
    const statusFilterButton = event.target.closest('.js-os-status-filter');
    if (!statusFilterButton) return;
    event.preventDefault();
    const statusSelect = document.querySelector('form[data-live-filter="service-orders"] select[name="status"]');
    if (!statusSelect) {
        window.location.assign(statusFilterButton.href);
        return;
    }
    statusSelect.value = statusFilterButton.dataset.status || '';
    statusSelect.dispatchEvent(new Event('change', { bubbles: true }));
});
</script>
