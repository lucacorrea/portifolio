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

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'client_id' => trim((string) ($_GET['client_id'] ?? '')),
    'employee_id' => trim((string) ($_GET['employee_id'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'priority' => trim((string) ($_GET['priority'] ?? '')),
];

$orders = $orderService->listOrders($filters);
$teamsByOrder = $orderService->teamMembersForOrders($orders);
$summary = $orderService->orderSummary();
$clients = $clientService->listClients();
$employees = $employeeService->listEmployees();
$products = $productService->listProducts(['status' => 'ativo']);
$services = $catalogService->listServices(['status' => 'ativo']);
$availableBudgets = $orderService->availableApprovedBudgets();
$recovery = os_consume_form_recovery();

$canCreate = $authorization->can('os.criar');
$canEdit = $authorization->can('os.editar');
$canSchedule = $authorization->can('os.agendar');
$canTeam = $authorization->can('os.alterar_equipe');
$canStatus = $authorization->can('os.alterar_status');
$canFinalize = $authorization->can('os.finalizar') && $authorization->can('os.finalizar_com_pagamento');
$canCancel = $authorization->can('os.cancelar');
$canReopen = $authorization->can('os.reabrir');
$canViewValues = $authorization->can('os.visualizar_valores');
$canConvertBudget = $authorization->can('orcamento.converter_os');
$canReceipt = $authorization->can('os.emitir_comprovante');

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

function os_money_fmt(string $value): string
{
    return money($value);
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

<form class="filter-bar" method="get" action="ordens-servico.php">
    <div class="search-wrap"><i class="bi bi-search"></i><input class="search-input" type="search" name="search" value="<?= h($filters['search']) ?>" placeholder="Pesquisar OS, cliente, local ou funcionario"></div>
    <input class="filter-select input-date" type="date" name="date_from" value="<?= h($filters['date_from']) ?>" aria-label="Data inicial">
    <input class="filter-select input-date" type="date" name="date_to" value="<?= h($filters['date_to']) ?>" aria-label="Data final">
    <select class="filter-select" name="client_id" aria-label="Cliente"><option value="">Todos os clientes</option><?php foreach ($clients as $client): ?><option value="<?= h((string) $client->id()) ?>" <?= $filters['client_id'] === (string) $client->id() ? 'selected' : '' ?>><?= h($client->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="employee_id" aria-label="Funcionario"><option value="">Todos os funcionarios</option><?php foreach ($employees as $employee): ?><option value="<?= h((string) $employee->id()) ?>" <?= $filters['employee_id'] === (string) $employee->id() ? 'selected' : '' ?>><?= h($employee->displayCode() . ' - ' . $employee->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="status" aria-label="Status"><option value="">Todos os status</option><?php foreach (['rascunho','aberta','aguardando_agendamento','agendada','em_deslocamento','em_execucao','aguardando_peca','finalizada','cancelada'] as $status): ?><option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= h(os_label_status($status)) ?></option><?php endforeach; ?></select>
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" href="ordens-servico.php"><i class="bi bi-x-lg"></i> Limpar</a>
</form>

<section class="panel">
    <div class="panel-header">
        <div class="panel-title"><i class="bi bi-wrench-adjustable-circle"></i>Ordens de Servico</div>
        <?php if ($canCreate): ?><button class="btn-filter btn-filter-primary" type="button" data-bs-toggle="modal" data-bs-target="#modal-os"><i class="bi bi-plus-lg"></i> Nova OS</button><?php endif; ?>
    </div>
    <?php if ($orders === []): ?>
        <?php empty_state('Nenhuma OS encontrada', 'Cadastre uma OS ou ajuste os filtros.'); ?>
    <?php else: ?>
        <div class="table-panel-wrap">
            <table class="os-table service-orders-table">
                <thead><tr><th>OS</th><th>Cliente</th><th>Local</th><th>Funcionarios</th><th>Data do servico</th><th>Status</th><th>Acoes</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php $team = $teamsByOrder[$order->id()] ?? []; ?>
                    <tr>
                        <td>
                            <?php if ($canEdit && !in_array($order->status(), ['finalizada','cancelada'], true)): ?>
                                <button class="table-inline-action js-os-edit" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os"><?= h($order->displayNumber()) ?></button>
                            <?php else: ?>
                                <strong><?= h($order->displayNumber()) ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($canEdit && !in_array($order->status(), ['finalizada','cancelada'], true)): ?>
                                <button class="table-inline-action js-os-edit" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os"><?= h($order->clientName()) ?></button>
                            <?php else: ?>
                                <?= h($order->clientName()) ?>
                            <?php endif; ?>
                            <br><small class="text-muted">CLI-<?= h(str_pad((string) $order->clientId(), 6, '0', STR_PAD_LEFT)) ?></small>
                        </td>
                        <td><?= h(os_location($order)) ?></td>
                        <td><?= os_team_cell($team) ?></td>
                        <td><?= os_schedule_cell($order) ?></td>
                        <td><span class="badge-soft badge-<?= h(os_badge_status($order->status())) ?>"><?= h($order->displayStatus()) ?></span></td>
                        <td class="table-actions-cell">
                            <div class="dropdown table-action-dropdown">
                                <button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acoes da OS <?= h($order->displayNumber()) ?>"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button class="dropdown-item js-os-view" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os-view"><i class="bi bi-eye"></i> Visualizar</button></li>
                                    <?php if ($canEdit && !in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item js-os-edit" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os"><i class="bi bi-pencil"></i> Editar</button></li><?php endif; ?>
                                    <?php if (($canTeam || $canSchedule) && !in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item js-os-team" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-team='<?= h(json_encode(array_map(static fn($member): array => ['employee_id' => $member->employeeId(), 'role' => $member->role(), 'primary' => $member->primary()], $team), JSON_UNESCAPED_UNICODE)) ?>' data-start="<?= h($order->scheduledStart() ?? '') ?>" data-end="<?= h($order->scheduledEnd() ?? '') ?>" data-bs-toggle="modal" data-bs-target="#modal-os-team"><i class="bi bi-people"></i> Definir equipe</button></li><?php endif; ?>
                                    <?php if ($canStatus && $order->status() === 'agendada'): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="start_travel" data-label="Iniciar deslocamento" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-truck"></i> Iniciar deslocamento</button></li><?php endif; ?>
                                    <?php if ($canStatus && in_array($order->status(), ['agendada','em_deslocamento','aguardando_peca'], true)): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="start_execution" data-label="Iniciar execucao" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-play-circle"></i> Iniciar execucao</button></li><?php endif; ?>
                                    <?php if ($canStatus && in_array($order->status(), ['agendada','em_deslocamento','em_execucao'], true)): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="wait_part" data-label="Aguardar peca" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-hourglass-split"></i> Aguardar peca</button></li><?php endif; ?>
                                    <?php if ($canFinalize && in_array($order->status(), ['agendada','em_execucao','aguardando_peca'], true)): ?><li><button class="dropdown-item js-os-finalize" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os-finalize"><i class="bi bi-check2-circle"></i> Finalizar servico</button></li><?php endif; ?>
                                    <?php if ($canCancel && !in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item text-danger js-os-cancel" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os-cancel"><i class="bi bi-x-circle"></i> Cancelar servico</button></li><?php endif; ?>
                                    <?php if ($canReopen && in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="reopen" data-label="Reabrir" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-arrow-counterclockwise"></i> Reabrir</button></li><?php endif; ?>
                                    <?php if ($canReceipt && $order->status() === 'finalizada'): ?><li><a class="dropdown-item" href="ordem-servico-comprovante.php?id=<?= h((string) $order->id()) ?>&valores=0" target="_blank"><i class="bi bi-receipt"></i> Emitir comprovante</a></li><?php endif; ?>
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
      <ul class="nav nav-tabs mb-3" role="tablist"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#os-tab-client" type="button">Cliente</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-tab-items" type="button">Itens</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-tab-team" type="button">Equipe</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-tab-values" type="button">Valores</button></li></ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="os-tab-client"><section class="form-section"><div class="form-row"><div class="form-group"><label class="form-label">Origem</label><select class="form-control-os" name="creation_mode" id="os-creation-mode"><option value="manual">Criar manualmente</option><?php if ($canConvertBudget): ?><option value="budget">Criar a partir de orcamento aprovado</option><?php endif; ?></select></div><div class="form-group"><label class="form-label">Cliente</label><select class="form-control-os" name="client_id" id="os-client" required><option value="">Selecione</option><?php os_select_options($clientOptions, '', true); ?></select></div><div class="form-group"><label class="form-label">Orcamento aprovado</label><select class="form-control-os" name="budget_id" id="os-budget-id"><option value="">Selecione</option><?php foreach ($availableBudgets as $budget): ?><option value="<?= h((string) $budget['id']) ?>" data-client-id="<?= h((string) $budget['cliente_id']) ?>" data-total="<?= h((string) $budget['total']) ?>" data-summary="<?= h((string) ($budget['servicos_resumo'] ?? '')) ?>"><?= h(($budget['numero'] ?? sprintf('ORC-%06d', (int) $budget['id'])) . ' - ' . $budget['cliente_nome'] . ' - ' . os_money_fmt((string) $budget['total'])) ?></option><?php endforeach; ?></select></div><div class="form-group"><label class="form-label">Status inicial</label><select class="form-control-os" name="status" id="os-status"><option value="rascunho">Rascunho</option><option value="aberta" selected>Aberta</option><option value="aguardando_agendamento">Aguardando agendamento</option><option value="agendada">Agendada</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Prioridade</label><select class="form-control-os" name="priority" id="os-priority"><option value="baixa">Baixa</option><option value="media" selected>Media</option><option value="alta">Alta</option><option value="urgente">Urgente</option></select></div><div class="form-group"><label class="form-label">Tipo do equipamento</label><input class="form-control-os" name="equipment_type" id="os-equipment-type"></div><div class="form-group"><label class="form-label">Marca</label><input class="form-control-os" name="equipment_brand" id="os-equipment-brand"></div><div class="form-group"><label class="form-label">Modelo</label><input class="form-control-os" name="equipment_model" id="os-equipment-model"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Capacidade</label><input class="form-control-os" name="equipment_capacity" id="os-equipment-capacity"></div><div class="form-group"><label class="form-label">Numero de serie</label><input class="form-control-os" name="equipment_serial_number" id="os-equipment-serial-number"></div><div class="form-group"><label class="form-label">Ambiente</label><input class="form-control-os" name="equipment_environment" id="os-equipment-environment"></div><div class="form-group"><label class="form-label">Local</label><input class="form-control-os" name="equipment_location" id="os-equipment-location"></div></div><div class="alert alert-info d-none" id="os-budget-preview"></div></section><section class="form-section"><h3 class="form-section-title">Diagnostico e observacoes</h3><div class="form-row"><div class="form-group"><label class="form-label">Problema relatado</label><textarea class="form-control-os" name="reported_problem" id="os-reported-problem"></textarea></div><div class="form-group"><label class="form-label">Problema identificado</label><textarea class="form-control-os" name="identified_problem" id="os-identified-problem"></textarea></div></div><div class="form-row"><div class="form-group"><label class="form-label">Diagnostico</label><textarea class="form-control-os" name="diagnosis" id="os-diagnosis"></textarea></div><div class="form-group"><label class="form-label">Solucao</label><textarea class="form-control-os" name="solution" id="os-solution"></textarea></div></div><div class="form-row"><div class="form-group"><label class="form-label">Recomendacao</label><textarea class="form-control-os" name="recommendation" id="os-recommendation"></textarea></div><div class="form-group"><label class="form-label">Observacoes internas</label><textarea class="form-control-os" name="internal_notes" id="os-internal-notes"></textarea></div></div></section></div>
        <div class="tab-pane fade" id="os-tab-items"><section class="form-section"><h3 class="form-section-title">Servicos</h3><div class="os-items" data-os-items="servico"></div><button class="btn-filter btn-filter-ghost js-os-add-item" type="button" data-type="servico"><i class="bi bi-plus-lg"></i> Adicionar servico</button></section><section class="form-section"><h3 class="form-section-title">Produtos</h3><div class="os-items" data-os-items="produto"></div><button class="btn-filter btn-filter-ghost js-os-add-item" type="button" data-type="produto"><i class="bi bi-plus-lg"></i> Adicionar produto</button></section><section class="form-section"><h3 class="form-section-title">Outros</h3><div class="os-items" data-os-items="outro"></div><button class="btn-filter btn-filter-ghost js-os-add-item" type="button" data-type="outro"><i class="bi bi-plus-lg"></i> Adicionar outro item</button></section></div>
        <div class="tab-pane fade" id="os-tab-team"><section class="form-section"><div class="os-team-members" data-team-members></div><button class="btn-filter btn-filter-ghost js-os-add-team-member" type="button"><i class="bi bi-plus-lg"></i> Adicionar funcionario</button><div class="form-row mt-3"><div class="form-group"><label class="form-label">Inicio</label><input class="form-control-os" type="datetime-local" name="agendado_inicio" id="os-scheduled-start"></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="agendado_fim" id="os-scheduled-end"></div></div></section></div>
        <div class="tab-pane fade" id="os-tab-values"><section class="form-section"><div class="form-row"><div class="form-group"><label class="form-label">Desconto</label><input class="form-control-os js-os-discount" name="discount" id="os-discount" value="0,00"></div><div class="form-group"><label class="form-label">Acrescimo</label><input class="form-control-os js-os-increase" name="increase" id="os-increase" value="0,00"></div></div><?php if ($canViewValues): ?><div class="summary-box js-os-summary"><div><span>Servicos</span><strong data-summary="servico">R$ 0,00</strong></div><div><span>Produtos</span><strong data-summary="produto">R$ 0,00</strong></div><div><span>Outros</span><strong data-summary="outro">R$ 0,00</strong></div><div class="total"><span>Total</span><strong data-summary="total">R$ 0,00</strong></div></div><?php endif; ?><div class="form-group"><label class="form-label">Observacoes externas</label><textarea class="form-control-os" name="notes" id="os-notes"></textarea></div></section></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar</button></div>
  </form></div>
</div>

<template id="os-item-template"><div class="form-row os-item-row"><input type="hidden" data-field="id"><input type="hidden" data-field="type"><input type="hidden" data-field="origin" value="manual"><input type="hidden" data-field="budget_item_id"><div class="form-group os-reference-wrap"><label class="form-label">Referencia</label><select class="form-control-os" data-field="reference_id"></select></div><div class="form-group"><label class="form-label">Descricao</label><input class="form-control-os" data-field="description" required></div><div class="form-group"><label class="form-label">Unidade</label><input class="form-control-os" data-field="unit" value="un" required></div><div class="form-group"><label class="form-label">Qtd.</label><input class="form-control-os" data-field="quantity" value="1" required></div><div class="form-group"><label class="form-label">Valor unit.</label><input class="form-control-os" data-field="unit_price" value="0,00" required></div><div class="form-group"><label class="form-label">Desconto</label><input class="form-control-os" data-field="discount" value="0,00"></div><div class="form-group"><label class="form-label">Subtotal</label><input class="form-control-os" data-field="subtotal" readonly></div><div class="form-group"><label class="form-label">&nbsp;</label><button class="btn-filter btn-filter-ghost js-os-remove-item" type="button"><i class="bi bi-trash"></i></button></div></div></template>
<template id="os-team-member-template"><div class="form-row os-team-member-row"><div class="form-group"><label class="form-label">Funcionario</label><select class="form-control-os" data-team-field="funcionario_id"><option value="">Selecione</option></select></div><div class="form-group"><label class="form-label">Funcao</label><select class="form-control-os" data-team-field="funcao"><option>Responsavel tecnico</option><option>Tecnico</option><option>Instalador</option><option>Auxiliar</option><option>Eletricista</option><option>Supervisor</option><option>Motorista</option><option>Outro</option></select></div><div class="form-group"><label class="form-label">Principal</label><div class="form-check mt-2"><input class="form-check-input" type="radio" data-team-field="principal" value="1"><span class="form-check-label">Responsavel</span></div></div><div class="form-group"><label class="form-label">&nbsp;</label><button class="btn-filter btn-filter-ghost js-os-remove-team-member" type="button"><i class="bi bi-trash"></i></button></div></div></template>

<div class="modal fade" id="modal-os-view" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content visual-modal"><div class="modal-header"><div><h2 class="modal-title fs-5">Dados da OS</h2><p class="text-muted small mb-0" id="os-view-subtitle"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><section class="form-section"><h3 class="form-section-title">Resumo</h3><div class="summary-box" id="os-view-summary"></div></section><section class="form-section"><h3 class="form-section-title">Itens</h3><div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Tipo</th><th>Descricao</th><th>Qtd.</th><th>Valor</th><th>Subtotal</th></tr></thead><tbody id="os-view-items"></tbody></table></div></section></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Fechar</button></div></div></div></div>
<div class="modal fade" id="modal-os-team" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/os-equipe-agendamento.php"><div class="modal-header"><h2 class="modal-title fs-5">Equipe e agendamento</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="os-team-id"><input type="hidden" name="team_submitted" value="1"><div class="os-team-members" data-team-members></div><button class="btn-filter btn-filter-ghost js-os-add-team-member" type="button"><i class="bi bi-plus-lg"></i> Adicionar funcionario</button><div class="form-row mt-3"><div class="form-group"><label class="form-label">Inicio</label><input class="form-control-os" type="datetime-local" name="agendado_inicio" id="os-team-start"></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="agendado_fim" id="os-team-end"></div></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>
<div class="modal fade" id="modal-os-finalize" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/os-finalizar.php"><div class="modal-header"><h2 class="modal-title fs-5">Finalizar servico</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="os-finalize-id"><section class="form-section"><h3 class="form-section-title">Execucao</h3><div class="form-row"><input type="hidden" name="execution_items[0][type]" value="servico"><div class="form-group"><label class="form-label">Servico executado</label><input class="form-control-os" name="execution_items[0][description]" required></div><div class="form-group"><label class="form-label">Quantidade</label><input class="form-control-os" name="execution_items[0][quantity]" value="1"></div><div class="form-group"><label class="form-label">Valor unitario</label><input class="form-control-os" name="execution_items[0][unit_price]" value="0,00"></div><div class="form-group"><label class="form-label">Desconto</label><input class="form-control-os" name="execution_items[0][discount]" value="0,00"></div></div></section><section class="form-section"><h3 class="form-section-title">Pagamento e saldo</h3><div class="form-row"><div class="form-group"><label class="form-label">Valor recebido</label><input class="form-control-os" name="valor_recebido" value="0,00"></div><div class="form-group"><label class="form-label">Forma</label><select class="form-control-os" name="forma_pagamento"><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="cartao_debito">Cartao de debito</option><option value="cartao_credito">Cartao de credito</option><option value="transferencia">Transferencia</option><option value="outro">Outro</option></select></div><div class="form-group"><label class="form-label">Vencimento do saldo</label><input class="form-control-os" type="date" name="vencimento_em"></div><div class="form-group"><label class="form-label">Proximo lembrete</label><input class="form-control-os" type="date" name="proximo_lembrete_em"></div></div><div class="form-group"><label class="form-label">Observacao</label><textarea class="form-control-os" name="observacao"></textarea></div></section></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Finalizar</button></div></form></div></div>
<div class="modal fade" id="modal-os-cancel" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/os-cancelar.php"><div class="modal-header"><h2 class="modal-title fs-5">Cancelar servico</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="os-cancel-id"><div class="form-row"><div class="form-group"><label class="form-label">Destino do orcamento</label><select class="form-control-os" name="opcao" required><option value="definitivo">Cancelar definitivamente</option><option value="liberar_orcamento">Cancelar e liberar o orcamento</option><option value="criar_substituta">Cancelar e criar OS substituta</option></select></div><div class="form-group"><label class="form-label">Motivo</label><input class="form-control-os" name="motivo" required></div></div><div class="form-group"><label class="form-label">Observacao</label><textarea class="form-control-os" name="observacao"></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div>
<div class="modal fade" id="modal-os-status" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/os-status.php"><div class="modal-header"><h2 class="modal-title fs-5" id="os-status-title">Alterar status</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="os-status-id"><input type="hidden" name="operation" id="os-status-operation"><p id="os-status-message"></p></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div>

<script type="application/json" id="os-page-data"><?= json_encode(['services' => $serviceOptions, 'products' => $productOptions, 'employees' => $employeeOptions, 'recoveryModal' => $recovery['modal'] ?? ($_GET['modal'] ?? null), 'recoveryData' => $recovery['data'] ?? [], 'recoveryError' => $recovery['error'] ?? null], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
