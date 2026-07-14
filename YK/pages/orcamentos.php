<?php

declare(strict_types=1);

use App\Catalog\Entity\Product;
use App\Catalog\Entity\ServiceDefinition;
use App\CRM\Entity\Client;
use App\Sales\Entity\Budget;

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/orcamento-action-common.php';

$budgetService = $application->budgetManagement();
$clientService = $application->clientManagement();
$productService = $application->productManagement();
$serviceService = $application->serviceManagement();

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
    'client_id' => trim((string) ($_GET['client_id'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
];

$budgets = $budgetService->listBudgets($filters);
$ordersByBudget = $budgetService->operationalOrdersByBudget($budgets);
$summary = $budgetService->budgetSummary();
$clients = $clientService->listClients();
$activeClients = array_values(array_filter($clients, static fn(Client $client): bool => $client->status() === 'ativo'));
$products = $productService->listProducts(['status' => 'ativo']);
$services = $serviceService->listServices(['status' => 'ativo']);

$canCreate = $authorization->can('orcamento.criar');
$canEdit = $authorization->can('orcamento.editar');
$canApprove = $authorization->can('orcamento.aprovar');
$canReject = $authorization->can('orcamento.recusar');
$canPrint = $authorization->can('orcamento.imprimir');
$recovery = budget_consume_form_recovery();

function budget_data(?array $recovery, string $modal): array { return $recovery !== null && ($recovery['modal'] ?? '') === $modal && is_array($recovery['data'] ?? null) ? $recovery['data'] : []; }
function budget_error(?array $recovery, string $modal): ?string { return $recovery !== null && ($recovery['modal'] ?? '') === $modal && is_string($recovery['error'] ?? null) ? $recovery['error'] : null; }
function budget_value(array $data, string $key, string $default = ''): string { $value = $data[$key] ?? $default; return is_scalar($value) ? (string) $value : $default; }
function budget_money(string $value): string { return money($value); }
function budget_date(string $value): string { try { return (new DateTimeImmutable($value))->format('d/m/Y'); } catch (Throwable) { return '-'; } }
function budget_status_label(string $status): string { return ['rascunho' => 'Rascunho', 'enviado' => 'Enviado', 'aguardando_aprovacao' => 'Aguardando aprovação', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado', 'vencido' => 'Vencido'][$status] ?? 'Rascunho'; }
function budget_status_class(string $status): string { return ['rascunho' => 'gray', 'enviado' => 'blue', 'aguardando_aprovacao' => 'amber', 'aprovado' => 'green', 'recusado' => 'red', 'vencido' => 'red'][$status] ?? 'gray'; }

$createData = budget_data($recovery, 'create');
$editData = budget_data($recovery, 'edit');
$createError = budget_error($recovery, 'create');
$editError = budget_error($recovery, 'edit');
?>

<div class="page-body budgets-page">
<?php metric_grid([
    ['Rascunhos', (string) ($summary['draft'] ?? 0), 'bi-pencil-square', '#64748B', 'em elaboração'],
    ['Enviados', (string) ($summary['sent'] ?? 0), 'bi-send', '#2563EB', 'com cliente'],
    ['Aguardando aprovação', (string) ($summary['waiting'] ?? 0), 'bi-hourglass-split', '#D97706', 'pendentes'],
    ['Aprovados', (string) ($summary['approved'] ?? 0), 'bi-check2-circle', '#16A34A', 'fechados'],
    ['Vencidos', (string) ($summary['expired'] ?? 0), 'bi-exclamation-circle', '#DC2626', 'fora da validade'],
    ['Valor aprovado', budget_money((string) ($summary['approved_value'] ?? '0')), 'bi-cash-stack', '#7C3AED', 'total aprovado'],
]); ?>

<form class="filter-bar" method="get" action="orcamentos.php">
    <div class="search-wrap"><i class="bi bi-search"></i><input class="search-input" type="search" name="search" value="<?= h($filters['search']) ?>" placeholder="Buscar número ou cliente" maxlength="150"></div>
    <input class="filter-select input-date" type="date" name="date_from" value="<?= h($filters['date_from']) ?>" aria-label="Período inicial">
    <input class="filter-select input-date" type="date" name="date_to" value="<?= h($filters['date_to']) ?>" aria-label="Período final">
    <select class="filter-select" name="client_id" aria-label="Cliente"><option value="">Todos os clientes</option><?php foreach ($clients as $client): ?><option value="<?= h((string) $client->id()) ?>" <?= $filters['client_id'] === (string) $client->id() ? 'selected' : '' ?>><?= h($client->name()) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="status" aria-label="Status"><option value="">Todos os status</option><?php foreach (['rascunho','enviado','aguardando_aprovacao','aprovado','recusado','vencido'] as $status): ?><option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= h(budget_status_label($status)) ?></option><?php endforeach; ?></select>
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button><a class="btn-filter btn-filter-ghost" href="orcamentos.php"><i class="bi bi-x-lg"></i> Limpar filtros</a>
</form>

<section class="panel">
    <div class="panel-header"><div class="panel-title"><i class="bi bi-file-earmark-text"></i>Orçamentos</div><?php if ($canCreate): ?><button class="btn-new-os" type="button" data-bs-toggle="modal" data-bs-target="#modal-orcamento"><i class="bi bi-file-earmark-plus"></i><span>Novo orçamento</span></button><?php endif; ?></div>
    <?php if ($budgets === []): ?><?php empty_state('Nenhum orçamento encontrado', 'Cadastre o primeiro orçamento ou ajuste os filtros.'); ?><?php else: ?>
    <div class="table-panel-wrap"><table class="os-table budgets-table"><thead><tr><th>Número</th><th>Cliente</th><th>Emissão</th><th>Validade</th><th>Itens</th><th>Valor</th><th>Status</th><th>Ações</th></tr></thead><tbody>
        <?php foreach ($budgets as $budget): ?>
            <?php $displayStatus = $budget->displayStatus(); ?>
            <tr>
                <td>
                    <?php if ($canEdit && !in_array($budget->status(), ['aprovado', 'recusado'], true)): ?>
                        <button class="table-inline-action js-budget-edit" type="button" data-budget-id="<?= h((string) $budget->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-orcamento-edit"><?= h($budget->displayNumber()) ?></button>
                    <?php else: ?>
                        <strong><?= h($budget->displayNumber()) ?></strong>
                    <?php endif; ?>
                    <?php if (isset($ordersByBudget[$budget->id()])): ?><br><small class="text-muted">OS criada: <?= h($ordersByBudget[$budget->id()]['numero']) ?></small><?php endif; ?>
                </td><td>
                    <?php if ($canEdit && !in_array($budget->status(), ['aprovado', 'recusado'], true)): ?>
                        <button class="table-inline-action js-budget-edit" type="button" data-budget-id="<?= h((string) $budget->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-orcamento-edit"><?= h($budget->clientName()) ?></button>
                    <?php else: ?>
                        <?= h($budget->clientName()) ?>
                    <?php endif; ?>
                    <br><small class="text-muted"><?= h($budget->clientCode()) ?></small>
                </td><td><?= h(budget_date($budget->issueDate())) ?></td><td><?= h(budget_date($budget->validUntil())) ?></td><td><?= h((string) $budget->itemsCount()) ?></td><td><?= h(budget_money($budget->total())) ?></td><td><span class="badge-soft badge-<?= h(budget_status_class($displayStatus)) ?>"><?= h(budget_status_label($displayStatus)) ?></span></td>
                <td class="table-actions-cell"><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações do orçamento <?= h($budget->displayNumber()) ?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end">
                    <li><button class="dropdown-item js-budget-view" type="button" data-budget-id="<?= h((string) $budget->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-orcamento-view"><i class="bi bi-eye"></i> Visualizar</button></li>
                    <?php if (isset($ordersByBudget[$budget->id()])): ?><li><a class="dropdown-item" href="ordens-servico.php?search=<?= h(rawurlencode($ordersByBudget[$budget->id()]['numero'])) ?>"><i class="bi bi-clipboard2-check"></i> Abrir Ordem de Serviço</a></li><?php endif; ?>
                    <?php if ($canEdit && !in_array($budget->status(), ['aprovado', 'recusado'], true)): ?><li><button class="dropdown-item js-budget-edit" type="button" data-budget-id="<?= h((string) $budget->id()) ?>" data-bs-toggle="modal" data-bs-target="#modal-orcamento-edit"><i class="bi bi-pencil"></i> Editar</button></li><?php endif; ?>
                    <?php if ($canApprove && !in_array($budget->status(), ['aprovado', 'recusado'], true)): ?><li><button class="dropdown-item js-budget-status" type="button" data-operation="approve" data-budget-id="<?= h((string) $budget->id()) ?>" data-budget-number="<?= h($budget->displayNumber()) ?>" data-bs-toggle="modal" data-bs-target="#modal-orcamento-status"><i class="bi bi-check2-circle"></i> Aprovar</button></li><?php endif; ?>
                    <?php if ($canReject && !in_array($budget->status(), ['aprovado', 'recusado'], true)): ?><li><button class="dropdown-item js-budget-status text-danger" type="button" data-operation="reject" data-budget-id="<?= h((string) $budget->id()) ?>" data-budget-number="<?= h($budget->displayNumber()) ?>" data-bs-toggle="modal" data-bs-target="#modal-orcamento-status"><i class="bi bi-x-circle"></i> Recusar</button></li><?php endif; ?>
                    <?php if ($canPrint): ?><li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="orcamento-imprimir.php?id=<?= h((string) $budget->id()) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Imprimir</a></li><?php endif; ?>
                </ul></div></td>
            </tr>
        <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
</div>

<?php
$clientOptions = array_map(static fn(Client $client): array => ['id' => $client->id(), 'name' => $client->name(), 'active' => $client->status() === 'ativo'], $clients);
$serviceOptions = array_map(static fn(ServiceDefinition $service): array => ['id' => $service->id(), 'name' => $service->name(), 'description' => $service->description() ?? $service->name(), 'value' => $service->value()], $services);
$productOptions = array_map(static fn(Product $product): array => ['id' => $product->id(), 'name' => $product->name(), 'unit' => $product->unit(), 'value' => $product->salePrice()], $products);

function budget_select_options(array $items, string $selected = '', bool $onlyActive = false): void {
    foreach ($items as $item) {
        if ($onlyActive && empty($item['active']) && (string) $item['id'] !== $selected) continue;
        echo '<option value="' . h((string) $item['id']) . '" ' . ($selected === (string) $item['id'] ? 'selected' : '') . '>' . h((string) $item['name']) . '</option>';
    }
}

function budget_modal_form(string $id, string $title, array $data, ?string $error, array $clientOptions, bool $edit = false): void {
?>
<div class="modal fade" id="<?= h($id) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal js-budget-form" method="post" action="actions/orcamento-salvar.php" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5"><?= h($title) ?></h2><p class="text-muted small mb-0"><?= $edit ? 'O número é imutável.' : 'O número será gerado automaticamente.' ?></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $GLOBALS['csrf']->field() ?><?php return_to_field(); ?><div class="alert alert-danger <?= $error === null ? 'd-none' : '' ?>" role="alert"><?= h($error ?? '') ?></div><?php if ($edit): ?><input type="hidden" name="id" id="edit-budget-id" value="<?= h(budget_value($data, 'id')) ?>"><section class="form-section"><h3 class="form-section-title">Número</h3><input class="form-control-os" id="edit-budget-number" value="<?= h(budget_value($data, 'number')) ?>" readonly></section><?php endif; ?><section class="form-section"><h3 class="form-section-title">Dados do orçamento</h3><div class="form-row"><div class="form-group"><label class="form-label">Cliente</label><select class="form-control-os" name="client_id" id="<?= $edit ? 'edit' : 'create' ?>-budget-client" required><option value="">Selecione</option><?php budget_select_options($clientOptions, budget_value($data, 'client_id'), !$edit); ?></select></div><div class="form-group"><label class="form-label">Data de emissão</label><input class="form-control-os" type="date" name="issue_date" id="<?= $edit ? 'edit' : 'create' ?>-budget-issue-date" value="<?= h(budget_value($data, 'issue_date', date('Y-m-d'))) ?>" required></div><div class="form-group"><label class="form-label">Validade</label><input class="form-control-os" type="date" name="valid_until" id="<?= $edit ? 'edit' : 'create' ?>-budget-valid-until" value="<?= h(budget_value($data, 'valid_until', date('Y-m-d', strtotime('+7 days')))) ?>" required></div><div class="form-group"><label class="form-label">Status</label><select class="form-control-os" name="status" id="<?= $edit ? 'edit' : 'create' ?>-budget-status"><option value="rascunho" <?= budget_value($data, 'status', 'rascunho') === 'rascunho' ? 'selected' : '' ?>>Rascunho</option><option value="enviado" <?= budget_value($data, 'status') === 'enviado' ? 'selected' : '' ?>>Enviado</option><option value="aguardando_aprovacao" <?= budget_value($data, 'status') === 'aguardando_aprovacao' ? 'selected' : '' ?>>Aguardando aprovação</option></select></div></div></section><section class="form-section"><h3 class="form-section-title">Serviços</h3><div class="budget-items" data-budget-items="servico"></div><button class="btn-filter btn-filter-ghost js-add-budget-item" type="button" data-type="servico"><i class="bi bi-plus-lg"></i> Adicionar serviço</button></section><section class="form-section"><h3 class="form-section-title">Produtos</h3><div class="budget-items" data-budget-items="produto"></div><button class="btn-filter btn-filter-ghost js-add-budget-item" type="button" data-type="produto"><i class="bi bi-plus-lg"></i> Adicionar produto</button></section><section class="form-section"><h3 class="form-section-title">Outros itens</h3><div class="budget-items" data-budget-items="outro"></div><button class="btn-filter btn-filter-ghost js-add-budget-item" type="button" data-type="outro"><i class="bi bi-plus-lg"></i> Adicionar outro item</button></section><section class="form-section"><h3 class="form-section-title">Valores e observações</h3><div class="form-row"><div class="form-group"><label class="form-label">Desconto geral</label><input class="form-control-os js-budget-discount" name="discount" value="<?= h(budget_value($data, 'discount', '0,00')) ?>"></div><div class="form-group"><label class="form-label">Acréscimo</label><input class="form-control-os js-budget-increase" name="increase" value="<?= h(budget_value($data, 'increase', '0,00')) ?>"></div></div><div class="summary-box js-budget-summary"><div><span>Subtotal de serviços</span><strong data-summary="servico">R$ 0,00</strong></div><div><span>Subtotal de produtos</span><strong data-summary="produto">R$ 0,00</strong></div><div><span>Subtotal de outros</span><strong data-summary="outro">R$ 0,00</strong></div><div><span>Desconto geral</span><strong data-summary="discount">R$ 0,00</strong></div><div><span>Acréscimo</span><strong data-summary="increase">R$ 0,00</strong></div><div class="total"><span>Total</span><strong data-summary="total">R$ 0,00</strong></div></div><div class="form-group"><label class="form-label">Observações</label><textarea class="form-control-os" name="notes" id="<?= $edit ? 'edit' : 'create' ?>-budget-notes" rows="3"><?= h(budget_value($data, 'notes')) ?></textarea></div></section></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar</button></div></form></div></div>
<?php } ?>

<?php if ($canCreate) budget_modal_form('modal-orcamento', 'Novo orçamento', $createData, $createError, $clientOptions); ?>
<?php if ($canEdit) budget_modal_form('modal-orcamento-edit', 'Editar orçamento', $editData, $editError, $clientOptions, true); ?>

<div class="modal fade" id="modal-orcamento-view" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content visual-modal"><div class="modal-header"><div><h2 class="modal-title fs-5">Dados do orçamento</h2><p class="text-muted small mb-0" id="view-budget-subtitle"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><section class="form-section"><h3 class="form-section-title">Resumo</h3><div class="summary-box" id="view-budget-summary"></div></section><section class="form-section"><h3 class="form-section-title">Itens</h3><div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Tipo</th><th>Descrição</th><th>Unidade</th><th>Qtd.</th><th>Valor unit.</th><th>Desconto</th><th>Subtotal</th></tr></thead><tbody id="view-budget-items"></tbody></table></div></section></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Fechar</button></div></div></div></div>

<div class="modal fade" id="modal-orcamento-status" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/orcamento-status.php"><div class="modal-header"><h2 class="modal-title fs-5" id="budget-status-title">Alterar status</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="status-budget-id"><input type="hidden" name="operation" id="status-budget-operation"><p id="budget-status-message"></p><div class="form-group d-none" id="budget-reject-reason-wrap"><label class="form-label">Motivo da recusa</label><textarea class="form-control-os" name="reason" rows="3"></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Confirmar</button></div></form></div></div>

<template id="budget-item-template"><div class="form-row budget-item-row"><input type="hidden" data-field="type"><div class="form-group budget-reference-wrap"><label class="form-label">Referência</label><select class="form-control-os" data-field="reference_id"></select></div><div class="form-group"><label class="form-label">Descrição</label><input class="form-control-os" data-field="description" maxlength="255" required></div><div class="form-group"><label class="form-label">Unidade</label><input class="form-control-os" data-field="unit" value="un" maxlength="20" required></div><div class="form-group"><label class="form-label">Quantidade</label><input class="form-control-os" data-field="quantity" value="1" required></div><div class="form-group"><label class="form-label">Valor unitário</label><input class="form-control-os" data-field="unit_price" value="0,00" required></div><div class="form-group"><label class="form-label">Desconto</label><input class="form-control-os" data-field="discount" value="0,00"></div><div class="form-group"><label class="form-label">Subtotal</label><input class="form-control-os" data-field="subtotal" value="R$ 0,00" readonly></div><div class="form-group"><label class="form-label">&nbsp;</label><button class="btn-filter btn-filter-ghost js-remove-budget-item" type="button"><i class="bi bi-trash"></i></button></div></div></template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const serviceOptions = <?= json_encode($serviceOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const productOptions = <?= json_encode($productOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const recoveryModal = <?= json_encode($recovery['modal'] ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const recoveryData = <?= json_encode($recovery['data'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    function parseNumber(value) { value = String(value || '0').replace(/\s/g, ''); if (value.includes(',')) value = value.replace(/\./g, '').replace(',', '.'); return Math.max(0, Number.parseFloat(value) || 0); }
    function money(value) { return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }
    function field(row, name) { return row.querySelector('[data-field="' + name + '"]'); }
    function setName(row, type, index) { row.querySelectorAll('[data-field]').forEach(function (input) { input.name = (type === 'servico' ? 'services' : type === 'produto' ? 'products' : 'others') + '[' + index + '][' + input.dataset.field + ']'; }); }
    function optionsFor(type) { return type === 'servico' ? serviceOptions : type === 'produto' ? productOptions : []; }
    function recalc(form) { let sums = { servico: 0, produto: 0, outro: 0 }; form.querySelectorAll('.budget-item-row').forEach(function (row) { const type = field(row, 'type').value; const subtotal = Math.max(0, parseNumber(field(row, 'quantity').value) * parseNumber(field(row, 'unit_price').value) - parseNumber(field(row, 'discount').value)); field(row, 'subtotal').value = money(subtotal); sums[type] += subtotal; }); const discount = parseNumber(form.querySelector('.js-budget-discount')?.value); const increase = parseNumber(form.querySelector('.js-budget-increase')?.value); const total = Math.max(0, sums.servico + sums.produto + sums.outro - discount + increase); Object.entries({ servico: sums.servico, produto: sums.produto, outro: sums.outro, discount, increase, total }).forEach(function ([key, value]) { const target = form.querySelector('[data-summary="' + key + '"]'); if (target) target.textContent = money(value); }); }
    function addRow(form, type, item) { const template = document.getElementById('budget-item-template'); const row = template.content.firstElementChild.cloneNode(true); const container = form.querySelector('[data-budget-items="' + type + '"]'); const index = container.children.length; field(row, 'type').value = type; setName(row, type, index); const select = field(row, 'reference_id'); const referenceWrap = row.querySelector('.budget-reference-wrap'); if (type === 'outro') { referenceWrap.classList.add('d-none'); select.appendChild(new Option('Personalizado', '')); } else { select.appendChild(new Option('Selecione', '')); optionsFor(type).forEach(function (option) { const opt = new Option(option.name, option.id); opt.dataset.description = option.description || option.name; opt.dataset.unit = option.unit || 'un'; opt.dataset.value = option.value || '0.00'; select.appendChild(opt); }); } if (item) { select.value = item.reference_id || ''; field(row, 'description').value = item.description || ''; field(row, 'unit').value = item.unit || 'un'; field(row, 'quantity').value = item.quantity || '1'; field(row, 'unit_price').value = item.unit_price || '0,00'; field(row, 'discount').value = item.discount || '0,00'; } select.addEventListener('change', function () { const opt = select.selectedOptions[0]; if (!opt) return; if (!field(row, 'description').value) field(row, 'description').value = opt.dataset.description || opt.textContent; field(row, 'unit').value = opt.dataset.unit || field(row, 'unit').value || 'un'; field(row, 'unit_price').value = opt.dataset.value || '0,00'; recalc(form); }); row.addEventListener('input', function () { recalc(form); }); row.querySelector('.js-remove-budget-item').addEventListener('click', function () { row.remove(); recalc(form); }); container.appendChild(row); recalc(form); }
    function hasRows(data) { return ['services', 'products', 'others'].some(function (key) { return Array.isArray(data[key]) && data[key].length > 0; }); }
    function restoreRows(form, data) { form.querySelectorAll('.budget-items').forEach(function (box) { box.replaceChildren(); }); (data.services || []).forEach(function (item) { addRow(form, 'servico', item); }); (data.products || []).forEach(function (item) { addRow(form, 'produto', item); }); (data.others || []).forEach(function (item) { addRow(form, 'outro', item); }); recalc(form); }
    document.querySelectorAll('.js-budget-form').forEach(function (form) { form.querySelectorAll('.js-add-budget-item').forEach(function (button) { button.addEventListener('click', function () { addRow(form, button.dataset.type); }); }); form.querySelectorAll('.js-budget-discount,.js-budget-increase').forEach(function (input) { input.addEventListener('input', function () { recalc(form); }); }); if (!form.closest('#modal-orcamento-edit') && !(recoveryModal === 'create' && hasRows(recoveryData))) addRow(form, 'servico'); });
    async function loadBudget(id, mode) { const response = await fetch('actions/orcamento-detalhes.php?id=' + encodeURIComponent(id) + (mode ? '&mode=' + mode : ''), { headers: { Accept: 'application/json' } }); if (!response.ok) throw new Error('Falha ao carregar orçamento.'); return response.json(); }
    document.querySelectorAll('.js-budget-view').forEach(function (button) { button.addEventListener('click', async function () { const data = await loadBudget(button.dataset.budgetId); const budget = data.budget; document.getElementById('view-budget-subtitle').textContent = budget.number; const summary = document.getElementById('view-budget-summary'); summary.replaceChildren(); [['Número', budget.number], ['Cliente', budget.client_name + ' · ' + (budget.client_document || '-')], ['Emissão', budget.issue_date], ['Validade', budget.valid_until], ['Status', budget.display_status], ['Subtotal serviços', money(parseNumber(budget.services_subtotal))], ['Subtotal produtos', money(parseNumber(budget.products_subtotal))], ['Subtotal outros', money(parseNumber(budget.others_subtotal))], ['Desconto', money(parseNumber(budget.discount))], ['Acréscimo', money(parseNumber(budget.increase))], ['Total', money(parseNumber(budget.total))], ['Observações', budget.notes || '-']].forEach(function (pair) { const div = document.createElement('div'); const span = document.createElement('span'); span.textContent = pair[0]; const strong = document.createElement('strong'); strong.textContent = pair[1]; div.append(span, strong); summary.appendChild(div); }); const tbody = document.getElementById('view-budget-items'); tbody.replaceChildren(); data.items.forEach(function (item) { const row = document.createElement('tr'); [item.type, item.description, item.unit, item.quantity, money(parseNumber(item.unit_price)), money(parseNumber(item.discount)), money(parseNumber(item.subtotal))].forEach(function (value) { const cell = document.createElement('td'); cell.textContent = value; row.appendChild(cell); }); tbody.appendChild(row); }); }); });
    document.querySelectorAll('.js-budget-edit').forEach(function (button) { button.addEventListener('click', async function () { const data = await loadBudget(button.dataset.budgetId, 'edit'); const budget = data.budget; const modal = document.getElementById('modal-orcamento-edit'); const form = modal.querySelector('form'); form.querySelectorAll('.budget-items').forEach(function (box) { box.replaceChildren(); }); [['id', budget.id], ['number', budget.number], ['client', budget.client_id], ['issue-date', budget.issue_date], ['valid-until', budget.valid_until], ['status', budget.status], ['notes', budget.notes], ['discount', budget.discount], ['increase', budget.increase]].forEach(function (pair) { const el = document.getElementById('edit-budget-' + pair[0]) || form.querySelector(pair[0] === 'discount' ? '.js-budget-discount' : pair[0] === 'increase' ? '.js-budget-increase' : ''); if (el) el.value = pair[1] || ''; }); data.items.forEach(function (item) { addRow(form, item.type, item); }); recalc(form); }); });
    document.querySelectorAll('.js-budget-status').forEach(function (button) { button.addEventListener('click', function () { const reject = button.dataset.operation === 'reject'; document.getElementById('status-budget-id').value = button.dataset.budgetId; document.getElementById('status-budget-operation').value = button.dataset.operation; document.getElementById('budget-status-title').textContent = reject ? 'Recusar orçamento' : 'Aprovar orçamento'; document.getElementById('budget-status-message').textContent = (reject ? 'Deseja recusar ' : 'Deseja aprovar ') + button.dataset.budgetNumber + '?'; document.getElementById('budget-reject-reason-wrap').classList.toggle('d-none', !reject); }); });
    const targets = { create: 'modal-orcamento', edit: 'modal-orcamento-edit' }; if (recoveryModal && targets[recoveryModal] && window.bootstrap) { const modal = document.getElementById(targets[recoveryModal]); if (modal) { const form = modal.querySelector('form'); if (form && hasRows(recoveryData)) restoreRows(form, recoveryData); bootstrap.Modal.getOrCreateInstance(modal).show(); } }
});
</script>
