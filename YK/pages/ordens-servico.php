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
$summary = $orderService->orderSummary();
$clients = $clientService->listClients();
$employees = $employeeService->listEmployees();
$products = $productService->listProducts(['status' => 'ativo']);
$services = $catalogService->listServices(['status' => 'ativo']);
$recovery = os_consume_form_recovery();

$canCreate = $authorization->can('os.criar');
$canEdit = $authorization->can('os.editar');
$canSchedule = $authorization->can('os.agendar');
$canTeam = $authorization->can('os.alterar_equipe');
$canStatus = $authorization->can('os.alterar_status');
$canFinalize = $authorization->can('os.finalizar');
$canCancel = $authorization->can('os.cancelar');
$canReopen = $authorization->can('os.reabrir');
$canPrint = $authorization->can('os.imprimir');
$canViewValues = $authorization->can('os.visualizar_valores');

function os_label_status(string $status): string { return ['rascunho'=>'Rascunho','aberta'=>'Aberta','aguardando_agendamento'=>'Aguardando agendamento','agendada'=>'Agendada','em_deslocamento'=>'Em deslocamento','em_execucao'=>'Em execução','aguardando_peca'=>'Aguardando peça','finalizada'=>'Finalizada','cancelada'=>'Cancelada'][$status] ?? 'Aberta'; }
function os_badge_status(string $status): string { return ['rascunho'=>'gray','aberta'=>'blue','aguardando_agendamento'=>'amber','agendada'=>'teal','em_deslocamento'=>'purple','em_execucao'=>'green','aguardando_peca'=>'amber','finalizada'=>'green','cancelada'=>'red'][$status] ?? 'gray'; }
function os_label_priority(string $priority): string { return ['baixa'=>'Baixa','media'=>'Média','alta'=>'Alta','urgente'=>'Urgente'][$priority] ?? 'Média'; }
function os_badge_priority(string $priority): string { return ['baixa'=>'gray','media'=>'blue','alta'=>'amber','urgente'=>'red'][$priority] ?? 'blue'; }
function os_money(string $value): string { return money($value); }
function os_dt(?string $value): string { if ($value === null || $value === '') return 'Não agendada'; try { return (new DateTimeImmutable($value))->format('d/m/Y H:i'); } catch (Throwable) { return 'Não agendada'; } }
function os_input_value(?array $recovery, string $modal, string $key, string $default = ''): string { $data = $recovery !== null && ($recovery['modal'] ?? '') === $modal ? ($recovery['data'] ?? []) : []; $value = is_array($data) ? ($data[$key] ?? $default) : $default; return is_scalar($value) ? (string) $value : $default; }

$clientOptions = array_map(static fn(Client $client): array => ['id'=>$client->id(), 'name'=>$client->name(), 'active'=>$client->status()==='ativo'], $clients);
$employeeOptions = array_map(static fn(Employee $employee): array => ['id'=>$employee->id(), 'name'=>$employee->displayCode() . ' — ' . $employee->name()], $employees);
$serviceOptions = array_map(static fn(ServiceDefinition $service): array => ['id'=>$service->id(), 'name'=>$service->name(), 'description'=>$service->description() ?? $service->name(), 'unit'=>'un', 'value'=>$service->value()], $services);
$productOptions = array_map(static fn(Product $product): array => ['id'=>$product->id(), 'name'=>$product->name(), 'description'=>$product->description() ?? $product->name(), 'unit'=>$product->unit(), 'value'=>$product->salePrice()], $products);
?>

<div class="page-body service-orders-page">
<?php metric_grid([
    ['OS abertas', (string) ($summary['open_count'] ?? 0), 'bi-clipboard-check', '#2563EB', 'rascunho e aberta'],
    ['Aguardando agendamento', (string) ($summary['waiting_schedule'] ?? 0), 'bi-clock-history', '#D97706', 'sem horário definido'],
    ['Agendadas', (string) ($summary['scheduled'] ?? 0), 'bi-calendar2-check', '#0F766E', 'com equipe e horário'],
    ['Em atendimento', (string) ($summary['in_service'] ?? 0), 'bi-tools', '#16A34A', 'deslocamento/execução'],
    ['Aguardando peça', (string) ($summary['waiting_part'] ?? 0), 'bi-box-seam', '#7C3AED', 'pendentes'],
    ['Finalizadas no mês', (string) ($summary['finished_month'] ?? 0), 'bi-check2-circle', '#15803D', 'concluídas'],
    ['Urgentes', (string) ($summary['urgent'] ?? 0), 'bi-exclamation-triangle', '#DC2626', 'prioridade urgente'],
]); ?>

<form class="filter-bar" method="get" action="ordens-servico.php">
    <div class="search-wrap"><i class="bi bi-search"></i><input class="search-input" type="search" name="search" value="<?= h($filters['search']) ?>" placeholder="Pesquisar OS, cliente, serviço ou equipamento"></div>
    <input class="filter-select input-date" type="date" name="date_from" value="<?= h($filters['date_from']) ?>" aria-label="Data inicial">
    <input class="filter-select input-date" type="date" name="date_to" value="<?= h($filters['date_to']) ?>" aria-label="Data final">
    <select class="filter-select" name="client_id" aria-label="Cliente"><option value="">Todos os clientes</option><?php foreach ($clients as $client): ?><option value="<?= h((string) $client->id()) ?>" <?= $filters['client_id'] === (string) $client->id() ? 'selected' : '' ?>><?= h($client->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="employee_id" aria-label="Funcionário"><option value="">Todos os funcionários</option><?php foreach ($employees as $employee): ?><option value="<?= h((string) $employee->id()) ?>" <?= $filters['employee_id'] === (string) $employee->id() ? 'selected' : '' ?>><?= h($employee->displayCode() . ' — ' . $employee->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="status" aria-label="Status"><option value="">Todos os status</option><?php foreach (['rascunho','aberta','aguardando_agendamento','agendada','em_deslocamento','em_execucao','aguardando_peca','finalizada','cancelada'] as $status): ?><option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= h(os_label_status($status)) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="priority" aria-label="Prioridade"><option value="">Todas as prioridades</option><?php foreach (['baixa','media','alta','urgente'] as $priority): ?><option value="<?= h($priority) ?>" <?= $filters['priority'] === $priority ? 'selected' : '' ?>><?= h(os_label_priority($priority)) ?></option><?php endforeach; ?></select>
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" href="ordens-servico.php"><i class="bi bi-x-lg"></i> Limpar filtros</a>
</form>

<section class="panel">
    <div class="panel-header"><div class="panel-title"><i class="bi bi-wrench-adjustable-circle"></i>Ordens de Serviço</div></div>
    <?php if ($orders === []): ?><?php empty_state('Nenhuma OS encontrada', 'Cadastre uma OS ou ajuste os filtros.'); ?><?php else: ?>
    <div class="table-panel-wrap"><table class="os-table service-orders-table"><thead><tr><th>Número</th><th>Cliente</th><th>Equipamento</th><th>Serviço principal</th><th>Dupla</th><th>Agendamento</th><th>Prioridade</th><th>Status</th><?php if ($canViewValues): ?><th>Valor</th><?php endif; ?><th>Ações</th></tr></thead><tbody>
    <?php foreach ($orders as $order): ?>
        <tr>
            <td><strong><?= h($order->displayNumber()) ?></strong></td>
            <td><?= h($order->clientName()) ?><br><small class="text-muted">CLI-<?= h(str_pad((string) $order->clientId(), 6, '0', STR_PAD_LEFT)) ?></small></td>
            <td><?= h($order->displayEquipment()) ?></td>
            <td><?= h($order->mainService() ?? '-') ?></td>
            <td><?php if ($order->primaryEmployeeId() === null && $order->supportEmployeeId() === null): ?>Sem equipe<?php else: ?><small>Principal: <?= h($order->displayPrimaryEmployee() ?? '-') ?></small><br><small>Apoio: <?= h($order->displaySupportEmployee() ?? '-') ?></small><?php endif; ?></td>
            <td><?= h($order->displaySchedule()) ?></td>
            <td><span class="badge-soft badge-<?= h(os_badge_priority($order->priority())) ?>"><?= h($order->displayPriority()) ?></span></td>
            <td><span class="badge-soft badge-<?= h(os_badge_status($order->status())) ?>"><?= h($order->displayStatus()) ?></span></td>
            <?php if ($canViewValues): ?><td><?= h(os_money($order->total())) ?></td><?php endif; ?>
            <td class="table-actions-cell"><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações da OS <?= h($order->displayNumber()) ?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end">
                <li><button class="dropdown-item js-os-view" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os-view"><i class="bi bi-eye"></i> Visualizar</button></li>
                <?php if ($canEdit && !in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item js-os-edit" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-os"><i class="bi bi-pencil"></i> Editar</button></li><?php endif; ?>
                <?php if (($canTeam || $canSchedule) && !in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item js-os-team" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-primary-id="<?= h((string) ($order->primaryEmployeeId() ?? '')) ?>" data-support-id="<?= h((string) ($order->supportEmployeeId() ?? '')) ?>" data-start="<?= h($order->scheduledStart() ?? '') ?>" data-end="<?= h($order->scheduledEnd() ?? '') ?>" data-bs-toggle="modal" data-bs-target="#modal-os-team"><i class="bi bi-people"></i> Definir equipe e agendamento</button></li><?php endif; ?>
                <?php if ($canStatus && $order->status() === 'agendada'): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="start_travel" data-label="Iniciar deslocamento" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-truck"></i> Iniciar deslocamento</button></li><?php endif; ?>
                <?php if ($canStatus && in_array($order->status(), ['agendada','em_deslocamento','aguardando_peca'], true)): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="start_execution" data-label="Iniciar execução" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-play-circle"></i> Iniciar execução</button></li><?php endif; ?>
                <?php if ($canStatus && in_array($order->status(), ['agendada','em_deslocamento','em_execucao'], true)): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="wait_part" data-label="Aguardar peça" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-hourglass-split"></i> Aguardar peça</button></li><?php endif; ?>
                <?php if ($canFinalize && $order->status() === 'em_execucao'): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="finalize" data-label="Finalizar" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-check2-circle"></i> Finalizar</button></li><?php endif; ?>
                <?php if ($canCancel && !in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item js-os-status text-danger" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="cancel" data-label="Cancelar" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-x-circle"></i> Cancelar</button></li><?php endif; ?>
                <?php if ($canReopen && in_array($order->status(), ['finalizada','cancelada'], true)): ?><li><button class="dropdown-item js-os-status" type="button" data-order-id="<?= h((string) $order->id()) ?>" data-operation="reopen" data-label="Reabrir" data-bs-toggle="modal" data-bs-target="#modal-os-status"><i class="bi bi-arrow-counterclockwise"></i> Reabrir</button></li><?php endif; ?>
            </ul></div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
</div>

<?php
function os_select_options(array $items, string $selected = '', bool $onlyActive = false): void {
    foreach ($items as $item) {
        if ($onlyActive && empty($item['active']) && (string) $item['id'] !== $selected) continue;
        echo '<option value="' . h((string) $item['id']) . '" ' . ($selected === (string) $item['id'] ? 'selected' : '') . '>' . h((string) $item['name']) . '</option>';
    }
}
?>

<div class="modal fade" id="modal-os" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal js-os-form" method="post" action="actions/os-salvar.php" autocomplete="off">
    <div class="modal-header"><div><h2 class="modal-title fs-5" id="modal-os-title">Nova OS</h2><p class="text-muted small mb-0">O número é gerado automaticamente.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body"><?= $csrf->field() ?><input type="hidden" name="id" id="os-id"><div class="alert alert-danger <?= $recovery === null ? 'd-none' : '' ?>" data-os-form-error role="alert"><?= h($recovery['error'] ?? '') ?></div>
      <ul class="nav nav-tabs mb-3" role="tablist"><li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#os-tab-client" type="button">Cliente e equipamento</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-tab-diagnosis" type="button">Diagnóstico</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-tab-items" type="button">Itens</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-tab-team" type="button">Equipe e agendamento</button></li><li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#os-tab-values" type="button">Valores e observações</button></li></ul>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="os-tab-client"><section class="form-section"><div class="form-row"><div class="form-group"><label class="form-label">Cliente</label><select class="form-control-os" name="client_id" id="os-client" required><option value="">Selecione</option><?php os_select_options($clientOptions, '', true); ?></select></div><div class="form-group"><label class="form-label">Orçamento</label><input class="form-control-os" name="budget_id" id="os-budget-id" inputmode="numeric"></div><div class="form-group"><label class="form-label">Status inicial</label><select class="form-control-os" name="status" id="os-status"><option value="rascunho">Rascunho</option><option value="aberta" selected>Aberta</option><option value="aguardando_agendamento">Aguardando agendamento</option><option value="agendada">Agendada</option></select></div><div class="form-group"><label class="form-label">Prioridade</label><select class="form-control-os" name="priority" id="os-priority"><option value="baixa">Baixa</option><option value="media" selected>Média</option><option value="alta">Alta</option><option value="urgente">Urgente</option></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Tipo</label><input class="form-control-os" name="equipment_type" id="os-equipment-type"></div><div class="form-group"><label class="form-label">Marca</label><input class="form-control-os" name="equipment_brand" id="os-equipment-brand"></div><div class="form-group"><label class="form-label">Modelo</label><input class="form-control-os" name="equipment_model" id="os-equipment-model"></div><div class="form-group"><label class="form-label">Capacidade</label><input class="form-control-os" name="equipment_capacity" id="os-equipment-capacity"></div></div><div class="form-row"><div class="form-group"><label class="form-label">Número de série</label><input class="form-control-os" name="equipment_serial_number" id="os-equipment-serial-number"></div><div class="form-group"><label class="form-label">Ambiente</label><input class="form-control-os" name="equipment_environment" id="os-equipment-environment"></div><div class="form-group"><label class="form-label">Local</label><input class="form-control-os" name="equipment_location" id="os-equipment-location"></div></div></section></div>
        <div class="tab-pane fade" id="os-tab-diagnosis"><section class="form-section"><div class="form-row"><div class="form-group"><label class="form-label">Problema relatado</label><textarea class="form-control-os" name="reported_problem" id="os-reported-problem"></textarea></div><div class="form-group"><label class="form-label">Problema identificado</label><textarea class="form-control-os" name="identified_problem" id="os-identified-problem"></textarea></div></div><div class="form-row"><div class="form-group"><label class="form-label">Diagnóstico</label><textarea class="form-control-os" name="diagnosis" id="os-diagnosis"></textarea></div><div class="form-group"><label class="form-label">Solução</label><textarea class="form-control-os" name="solution" id="os-solution"></textarea></div></div><div class="form-group"><label class="form-label">Recomendação</label><textarea class="form-control-os" name="recommendation" id="os-recommendation"></textarea></div><div class="form-group"><label class="form-label">Observações internas</label><textarea class="form-control-os" name="internal_notes" id="os-internal-notes"></textarea></div></section></div>
        <div class="tab-pane fade" id="os-tab-items"><section class="form-section"><h3 class="form-section-title">Serviços</h3><div class="os-items" data-os-items="servico"></div><button class="btn-filter btn-filter-ghost js-os-add-item" type="button" data-type="servico"><i class="bi bi-plus-lg"></i> Adicionar serviço</button></section><section class="form-section"><h3 class="form-section-title">Produtos</h3><div class="os-items" data-os-items="produto"></div><button class="btn-filter btn-filter-ghost js-os-add-item" type="button" data-type="produto"><i class="bi bi-plus-lg"></i> Adicionar produto</button></section><section class="form-section"><h3 class="form-section-title">Outros itens</h3><div class="os-items" data-os-items="outro"></div><button class="btn-filter btn-filter-ghost js-os-add-item" type="button" data-type="outro"><i class="bi bi-plus-lg"></i> Adicionar outro item</button></section></div>
        <div class="tab-pane fade" id="os-tab-team"><section class="form-section"><div class="form-row"><div class="form-group"><label class="form-label">Funcionário principal</label><select class="form-control-os js-primary-employee" name="funcionario_principal_id" id="os-primary"><option value="">Sem equipe</option><?php os_select_options($employeeOptions); ?></select></div><div class="form-group"><label class="form-label">Funcionário de apoio</label><select class="form-control-os js-support-employee" name="funcionario_apoio_id" id="os-support"><option value="">Sem equipe</option><?php os_select_options($employeeOptions); ?></select></div><div class="form-group"><label class="form-label">Início</label><input class="form-control-os" type="datetime-local" name="agendado_inicio" id="os-scheduled-start"></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="agendado_fim" id="os-scheduled-end"></div></div></section></div>
        <div class="tab-pane fade" id="os-tab-values"><section class="form-section"><div class="form-row"><div class="form-group"><label class="form-label">Desconto</label><input class="form-control-os js-os-discount" name="discount" value="0,00"></div><div class="form-group"><label class="form-label">Acréscimo</label><input class="form-control-os js-os-increase" name="increase" value="0,00"></div></div><?php if ($canViewValues): ?><div class="summary-box js-os-summary"><div><span>Serviços</span><strong data-summary="servico">R$ 0,00</strong></div><div><span>Produtos</span><strong data-summary="produto">R$ 0,00</strong></div><div><span>Outros</span><strong data-summary="outro">R$ 0,00</strong></div><div><span>Desconto</span><strong data-summary="discount">R$ 0,00</strong></div><div><span>Acréscimo</span><strong data-summary="increase">R$ 0,00</strong></div><div class="total"><span>Total</span><strong data-summary="total">R$ 0,00</strong></div></div><?php endif; ?><div class="form-group"><label class="form-label">Observações</label><textarea class="form-control-os" name="notes" id="os-notes"></textarea></div></section></div>
      </div>
    </div>
    <div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar</button></div>
  </form></div>
</div>

<template id="os-item-template"><div class="form-row os-item-row"><input type="hidden" data-field="type"><div class="form-group os-reference-wrap"><label class="form-label">Referência</label><select class="form-control-os" data-field="reference_id"></select></div><div class="form-group"><label class="form-label">Descrição</label><input class="form-control-os" data-field="description" required></div><div class="form-group"><label class="form-label">Unidade</label><input class="form-control-os" data-field="unit" value="un" required></div><div class="form-group"><label class="form-label">Qtd.</label><input class="form-control-os" data-field="quantity" value="1" required></div><div class="form-group"><label class="form-label">Valor unit.</label><input class="form-control-os" data-field="unit_price" value="0,00" required></div><div class="form-group"><label class="form-label">Desconto</label><input class="form-control-os" data-field="discount" value="0,00"></div><div class="form-group"><label class="form-label">Subtotal</label><input class="form-control-os" data-field="subtotal" readonly></div><div class="form-group"><label class="form-label">&nbsp;</label><button class="btn-filter btn-filter-ghost js-os-remove-item" type="button"><i class="bi bi-trash"></i></button></div></div></template>

<div class="modal fade" id="modal-os-view" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content visual-modal"><div class="modal-header"><div><h2 class="modal-title fs-5">Dados da OS</h2><p class="text-muted small mb-0" id="os-view-subtitle"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><section class="form-section"><h3 class="form-section-title">Resumo</h3><div class="summary-box" id="os-view-summary"></div></section><section class="form-section"><h3 class="form-section-title">Itens</h3><div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Tipo</th><th>Descrição</th><th>Qtd.</th><th>Valor</th><th>Subtotal</th></tr></thead><tbody id="os-view-items"></tbody></table></div></section></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Fechar</button></div></div></div></div>

<div class="modal fade" id="modal-os-team" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/os-equipe-agendamento.php"><div class="modal-header"><h2 class="modal-title fs-5">Equipe e agendamento</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><input type="hidden" name="id" id="os-team-id"><div class="form-row"><div class="form-group"><label class="form-label">Funcionário principal</label><select class="form-control-os js-primary-employee" name="funcionario_principal_id" id="os-team-primary" required><option value="">Selecione</option><?php os_select_options($employeeOptions); ?></select></div><div class="form-group"><label class="form-label">Funcionário de apoio</label><select class="form-control-os js-support-employee" name="funcionario_apoio_id" id="os-team-support" required><option value="">Selecione</option><?php os_select_options($employeeOptions); ?></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Início</label><input class="form-control-os" type="datetime-local" name="agendado_inicio" id="os-team-start"></div><div class="form-group"><label class="form-label">Fim</label><input class="form-control-os" type="datetime-local" name="agendado_fim" id="os-team-end"></div></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Salvar</button></div></form></div></div>

<div class="modal fade" id="modal-os-status" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/os-status.php"><div class="modal-header"><h2 class="modal-title fs-5" id="os-status-title">Alterar status</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><input type="hidden" name="id" id="os-status-id"><input type="hidden" name="operation" id="os-status-operation"><p id="os-status-message"></p></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div>

<script type="application/json" id="os-page-data"><?= json_encode(['services'=>$serviceOptions,'products'=>$productOptions,'recoveryModal'=>$recovery['modal'] ?? ($_GET['modal'] ?? null),'recoveryData'=>$recovery['data'] ?? []], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
