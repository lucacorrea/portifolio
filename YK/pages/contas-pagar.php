<?php

declare(strict_types=1);

use App\Finance\Service\AccountsPayableManagementService;

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/financial-registration-action-common.php';

function payable_filter(string $key, int $maximumLength): string
{
    $raw = $_GET[$key] ?? '';
    if (!is_string($raw)) return '';
    $value = trim($raw);
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    return $length <= $maximumLength && !str_contains($value, "\0") ? $value : '';
}

function payable_value(array $account, string $key, string $default = ''): string
{
    $value = $account[$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

function payable_date(?string $value): string
{
    if ($value === null || trim($value) === '') return '-';
    try { return (new DateTimeImmutable($value))->format('d/m/Y'); } catch (Throwable) { return '-'; }
}

function payable_status_label(string $status, string $display = ''): string
{
    if ($display !== '') return $display;
    return ['pendente'=>'Pendente','vencida'=>'Vencida','parcial'=>'Parcialmente paga','paga'=>'Paga','cancelada'=>'Cancelada'][$status] ?? ucfirst($status);
}

function payable_status_badge(string $status): string
{
    return ['pendente'=>'amber','vencida'=>'red','parcial'=>'blue','paga'=>'green','cancelada'=>'gray'][$status] ?? 'gray';
}

function payable_payment_label(string $method): string
{
    return ['dinheiro'=>'Dinheiro','pix'=>'Pix','boleto'=>'Boleto','cartao_credito'=>'Cartão de crédito','cartao_debito'=>'Cartão de débito','transferencia'=>'Transferência','cheque'=>'Cheque','outro'=>'Outro'][$method] ?? 'Não informada';
}

function payable_installment_status(array $installment): string
{
    $status = payable_value($installment, 'status', 'pendente');
    return $status === 'pendente' && payable_value($installment, 'vencimento_em') < date('Y-m-d') ? 'vencida' : $status;
}

function payable_current_installment(array $installments): ?array
{
    foreach ($installments as $installment) {
        if (is_array($installment) && payable_value($installment, 'status') === 'pendente') return $installment;
    }
    $last = end($installments);
    return is_array($last) ? $last : null;
}

function payable_payment_options(string $selected = ''): void
{
    foreach (AccountsPayableManagementService::paymentMethods() as $method) {
        ?><option value="<?= h($method) ?>" <?= $selected === $method ? 'selected' : '' ?>><?= h(payable_payment_label($method)) ?></option><?php
    }
}

function payable_status_url(array $filters, string $status): string
{
    $query = array_filter($filters, static fn(string $value): bool => $value !== '');
    if ($status === '') unset($query['status']);
    else $query['status'] = $status;
    return 'contas-pagar.php' . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
}

function payable_supplier_options(array $suppliers, string $selected = ''): void
{
    foreach ($suppliers as $supplier) {
        $id = payable_value($supplier, 'id');
        if ($id === '') continue;
        $code = payable_value($supplier, 'codigo');
        $name = payable_value($supplier, 'nome');
        ?><option value="<?= h($id) ?>" <?= $selected === $id ? 'selected' : '' ?>><?= h(trim($code . ' - ' . $name, ' -')) ?></option><?php
    }
}

function payable_form_fields(string $prefix, array $suppliers, array $data = []): void
{
    $paymentType = payable_value($data, 'tipo_pagamento', 'avista');
    $installmentCount = payable_value($data, 'quantidade_parcelas', $paymentType === 'parcelado' ? '2' : '1');
    $paymentMethod = payable_value($data, 'forma_pagamento');
    ?>
    <section class="form-section">
        <h3 class="form-section-title">Fornecedor e referência</h3>
        <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-fornecedor">Fornecedor</label><select class="form-control-os" id="<?= h($prefix) ?>-fornecedor" name="fornecedor_id" required><option value="">Selecione um fornecedor ativo</option><?php payable_supplier_options($suppliers, payable_value($data, 'fornecedor_id')); ?></select></div>
        <div class="form-row">
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-descricao">Descrição</label><input class="form-control-os" id="<?= h($prefix) ?>-descricao" name="descricao" value="<?= h(payable_value($data, 'descricao')) ?>" maxlength="255" required></div>
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-documento">Número do documento</label><input class="form-control-os" id="<?= h($prefix) ?>-documento" name="documento" value="<?= h(payable_value($data, 'documento')) ?>" maxlength="80"></div>
        </div>
    </section>
    <section class="form-section">
        <h3 class="form-section-title">Valores e vencimento</h3>
        <div class="form-row">
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-emissao">Data de emissão</label><input class="form-control-os" id="<?= h($prefix) ?>-emissao" name="data_emissao" value="<?= h(payable_value($data, 'data_emissao')) ?>" type="date"></div>
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-vencimento">Vencimento</label><input class="form-control-os" id="<?= h($prefix) ?>-vencimento" name="vencimento_em" value="<?= h(payable_value($data, 'vencimento_em')) ?>" type="date" required></div>
        </div>
        <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-valor">Valor total</label><input class="form-control-os" id="<?= h($prefix) ?>-valor" name="valor" value="<?= h(payable_value($data, 'valor')) ?>" inputmode="decimal" placeholder="0,00" maxlength="20" required></div>
    </section>
    <section class="form-section">
        <h3 class="form-section-title">Condição de pagamento</h3>
        <div class="form-row">
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-tipo-pagamento">Pagamento</label><select class="form-control-os js-payable-payment-type" id="<?= h($prefix) ?>-tipo-pagamento" name="tipo_pagamento" required><option value="avista" <?= $paymentType === 'avista' ? 'selected' : '' ?>>À vista</option><option value="parcelado" <?= $paymentType === 'parcelado' ? 'selected' : '' ?>>Parcelado</option></select></div>
            <div class="form-group js-payable-installment-count" <?= $paymentType === 'parcelado' ? '' : 'hidden' ?>><label class="form-label" for="<?= h($prefix) ?>-parcelas">Quantidade de parcelas</label><input class="form-control-os" id="<?= h($prefix) ?>-parcelas" name="quantidade_parcelas" type="number" min="2" max="60" value="<?= h($installmentCount) ?>" <?= $paymentType === 'parcelado' ? 'required' : '' ?>></div>
            <div class="form-group"><label class="form-label" for="<?= h($prefix) ?>-forma-pagamento">Forma prevista</label><select class="form-control-os" id="<?= h($prefix) ?>-forma-pagamento" name="forma_pagamento" required><option value="">Selecione</option><?php payable_payment_options($paymentMethod); ?></select></div>
        </div>
        <p class="section-note mb-0">O vencimento informado será o da primeira parcela; as demais serão geradas mês a mês.</p>
    </section>
    <section class="form-section">
        <h3 class="form-section-title">Observações</h3>
        <div class="form-group mb-0"><label class="form-label" for="<?= h($prefix) ?>-observacao">Observação</label><textarea class="form-control-os" id="<?= h($prefix) ?>-observacao" name="observacao" maxlength="1000" rows="3"><?= h(payable_value($data, 'observacao')) ?></textarea></div>
    </section>
    <?php
}

$service = $application->accountsPayableManagement();
$supplierService = $application->supplierManagement();
$filters = [
    'bucket' => payable_filter('bucket', 30),
    'supplier_id' => payable_filter('supplier_id', 20),
    'status' => payable_filter('status', 20),
    'search' => payable_filter('search', 150),
];
$allowedBuckets = ['', 'vencidos', 'hoje', 'semana', '15dias'];
$allowedStatuses = ['', 'pendente', 'vencida', 'parcial', 'paga', 'cancelada'];
if (!in_array($filters['bucket'], $allowedBuckets, true)) $filters['bucket'] = '';
if (!in_array($filters['status'], $allowedStatuses, true)) $filters['status'] = '';
if ($filters['supplier_id'] !== '' && (!ctype_digit($filters['supplier_id']) || (int) $filters['supplier_id'] < 1)) $filters['supplier_id'] = '';

$accounts = $service->listAccounts($filters);
$hasMoreAccounts = count($accounts) > 300;
$accounts = array_slice($accounts, 0, 300);
$indicators = $service->indicators();
$activeSuppliers = $supplierService->activeSuppliers();
$supplierFilters = $supplierService->supplierOptions();
$canCreate = $authorization->can('contas_pagar.criar');
$canEdit = $authorization->can('contas_pagar.editar');
$canCancel = $authorization->can('contas_pagar.cancelar');
$canSettle = $authorization->can('contas_pagar.quitar');
$canReverse = $authorization->can('contas_pagar.estornar_pagamento');
$recovery = financial_registration_consume_recovery('payable_form_recovery');
$createData = ($recovery['mode'] ?? '') === 'create' ? $recovery['data'] : [];
$editData = ($recovery['mode'] ?? '') === 'edit' ? $recovery['data'] : [];
$statusButtons = [['', 'Todos', 'all'], ['pendente', 'Pendentes', 'amber'], ['vencida', 'Vencidas', 'red'], ['parcial', 'Parciais', 'blue'], ['paga', 'Pagas', 'green'], ['cancelada', 'Canceladas', 'gray']];
?>

<div class="page-body accounts-payable-page">
<?php metric_grid([
    ['Total em aberto', money((string) ($indicators['open'] ?? '0')), 'bi-credit-card-2-front', '#2563EB', 'a pagar'],
    ['Total vencido', money((string) ($indicators['overdue'] ?? $indicators['vencido'] ?? '0')), 'bi-exclamation-triangle', '#DC2626', 'atrasado'],
    ['Vence hoje', money((string) ($indicators['today'] ?? $indicators['hoje'] ?? '0')), 'bi-calendar-day', '#D97706', 'hoje'],
    ['Próximos 7 dias', money((string) ($indicators['week'] ?? $indicators['semana'] ?? '0')), 'bi-calendar-week', '#0F766E', '7 dias'],
]); ?>

<form class="filter-bar" method="get" action="contas-pagar.php" data-live-filter="payables" data-live-regions="metrics results">
    <select class="filter-select" name="bucket" aria-label="Período de vencimento"><option value="">Todos os vencimentos</option><option value="vencidos" <?= $filters['bucket'] === 'vencidos' ? 'selected' : '' ?>>Vencidos</option><option value="hoje" <?= $filters['bucket'] === 'hoje' ? 'selected' : '' ?>>Vencem hoje</option><option value="semana" <?= $filters['bucket'] === 'semana' ? 'selected' : '' ?>>Próximos 7 dias</option><option value="15dias" <?= $filters['bucket'] === '15dias' ? 'selected' : '' ?>>Próximos 15 dias</option></select>
    <select class="filter-select" name="supplier_id" aria-label="Fornecedor"><option value="">Todos os fornecedores</option><?php foreach ($supplierFilters as $supplier): ?><option value="<?= h(payable_value($supplier, 'id')) ?>" <?= $filters['supplier_id'] === payable_value($supplier, 'id') ? 'selected' : '' ?>><?= h(payable_value($supplier, 'nome')) ?></option><?php endforeach; ?></select>
    <select class="filter-select" name="status" aria-label="Status"><option value="">Todos os status</option><?php foreach (['pendente','vencida','parcial','paga','cancelada'] as $status): ?><option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= h(payable_status_label($status)) ?></option><?php endforeach; ?></select>
    <div class="search-wrap"><i class="bi bi-search"></i><input class="search-input" type="search" name="search" value="<?= h($filters['search']) ?>" placeholder="Fornecedor, descrição ou documento" maxlength="150" aria-label="Pesquisar contas a pagar"></div>
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" href="contas-pagar.php" data-live-filter-clear><i class="bi bi-x-lg"></i> Limpar filtros</a>
</form>

<section class="panel" data-live-region="results">
    <div class="panel-header budget-panel-header">
        <div class="budget-panel-heading"><div class="panel-title"><i class="bi bi-credit-card-2-front"></i>Contas a Pagar</div><nav class="budget-status-filters" aria-label="Filtrar contas a pagar por status"><?php foreach ($statusButtons as [$status, $label, $color]): ?><a class="budget-status-filter budget-status-filter-<?= h($color) ?> js-payable-status-filter<?= $filters['status'] === $status ? ' active' : '' ?>" href="<?= h(payable_status_url($filters, $status)) ?>" data-status="<?= h($status) ?>" <?= $filters['status'] === $status ? 'aria-current="true"' : '' ?>><?= h($label) ?></a><?php endforeach; ?></nav></div>
        <?php if ($canCreate): ?><button class="btn-new-os" type="button" data-bs-toggle="modal" data-bs-target="#modal-conta-pagar"><i class="bi bi-plus-square"></i><span>Nova conta</span></button><?php endif; ?>
    </div>
    <?php if ($hasMoreAccounts): ?><div class="px-3 py-2 text-muted small border-bottom" role="status">Exibindo as primeiras 300 contas. Refine os filtros para localizar as demais.</div><?php endif; ?>
    <?php if ($accounts === []): ?><?php empty_state('Nenhuma conta encontrada', 'Cadastre manualmente uma conta de fornecedor ou ajuste os filtros.'); ?><?php else: ?>
    <div class="table-panel-wrap"><table class="os-table payable-table"><thead><tr><th>Código</th><th>Fornecedor / descrição</th><th>Pagamento</th><th>Parcela atual</th><th>Valor total</th><th>Status</th><th>Ações</th></tr></thead><tbody>
    <?php foreach ($accounts as $account):
        $payload = json_encode($account, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '{}';
        $status = payable_value($account, 'status', 'pendente');
        $displayStatus = payable_value($account, 'status_exibicao', $status);
        $locked = $status !== 'pendente' || payable_value($account, 'possui_movimentacao', '0') === '1';
        $installments = is_array($account['parcelas'] ?? null) ? $account['parcelas'] : [];
        $currentInstallment = payable_current_installment($installments);
        $rowStatus = $currentInstallment !== null ? payable_installment_status($currentInstallment) : $displayStatus;
        $currentPayload = json_encode($currentInstallment, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '{}';
    ?>
        <tr class="payable-row payable-row--<?= h($rowStatus) ?>">
            <td><strong><?= h(payable_value($account, 'codigo')) ?></strong><br><small><?= h(payable_value($account, 'documento', 'Sem documento')) ?></small></td>
            <td><strong><?= h(payable_value($account, 'fornecedor_nome')) ?></strong><br><small><?= h(payable_value($account, 'descricao')) ?></small></td>
            <td><strong><?= payable_value($account, 'tipo_pagamento') === 'parcelado' ? h(payable_value($account, 'quantidade_parcelas')) . 'x' : 'À vista' ?></strong><br><small><?= h(payable_payment_label(payable_value($account, 'forma_pagamento'))) ?></small></td>
            <td><div class="payable-installment-list"><?php if ($currentInstallment !== null): $installmentStatus = payable_installment_status($currentInstallment); ?><span class="payable-installment payable-installment--<?= h($installmentStatus) ?>" title="<?= h(payable_status_label($installmentStatus) . ' — ' . money(payable_value($currentInstallment, 'valor', '0'))) ?>"><strong><?= h(payable_value($currentInstallment, 'numero')) ?>/<?= h(payable_value($account, 'quantidade_parcelas')) ?></strong> <?= h(payable_date(payable_value($currentInstallment, 'vencimento_em'))) ?> · <?= h(payable_status_label($installmentStatus)) ?></span><?php else: ?>—<?php endif; ?></div></td>
            <td><strong><?= money(payable_value($account, 'valor', '0')) ?></strong><br><small><?= h(payable_value($account, 'parcelas_pagas', '0')) ?>/<?= h(payable_value($account, 'quantidade_parcelas', '1')) ?> quitada(s)</small></td>
            <td><span class="badge-soft badge-<?= h(payable_status_badge($displayStatus)) ?>"><?= h(payable_status_label($displayStatus)) ?></span></td>
            <td class="table-actions-cell"><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Ações da conta <?= h(payable_value($account, 'codigo')) ?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item js-payable-view" type="button" data-account="<?= h($payload) ?>" data-bs-toggle="modal" data-bs-target="#modal-conta-pagar-view"><i class="bi bi-eye"></i> Parcelas e detalhes</button></li><?php if ($canSettle && $currentInstallment !== null && payable_value($currentInstallment, 'status') === 'pendente'): ?><li><button class="dropdown-item js-payable-settle-installment" type="button" data-account="<?= h($payload) ?>" data-installment="<?= h($currentPayload) ?>"><i class="bi bi-check-circle"></i> Quitar parcela atual</button></li><?php endif; ?><?php if ($canEdit && !$locked): ?><li><button class="dropdown-item js-payable-edit" type="button" data-account="<?= h($payload) ?>" data-bs-toggle="modal" data-bs-target="#modal-conta-pagar-edit"><i class="bi bi-pencil"></i> Editar</button></li><?php endif; ?><?php if ($canCancel && !$locked): ?><li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger js-payable-cancel" type="button" data-account="<?= h($payload) ?>" data-bs-toggle="modal" data-bs-target="#modal-conta-pagar-cancel"><i class="bi bi-x-circle"></i> Cancelar</button></li><?php endif; ?></ul></div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
</div>

<?php if ($canCreate): ?><div class="modal fade" id="modal-conta-pagar" tabindex="-1" aria-hidden="true" <?= ($recovery['mode'] ?? '') === 'create' ? 'data-recovery-open="true"' : '' ?>><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/conta-pagar-salvar.php" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Nova conta a pagar</h2><p class="text-muted small mb-0">A conta será cadastrada como pendente.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="operation" value="create"><?php if (($recovery['mode'] ?? '') === 'create'): ?><div class="alert alert-danger" role="alert"><?= h((string) $recovery['error']) ?></div><?php endif; ?><?php if ($activeSuppliers === []): ?><div class="alert alert-warning" role="alert">Cadastre e ative um fornecedor antes de incluir uma conta.</div><?php endif; ?><?php payable_form_fields('create-payable', $activeSuppliers, $createData); ?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit" <?= $activeSuppliers === [] ? 'disabled' : '' ?>><i class="bi bi-check-lg"></i> Cadastrar conta</button></div></form></div></div><?php endif; ?>

<div class="modal fade" id="modal-conta-pagar-view" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content visual-modal"><div class="modal-header"><div><h2 class="modal-title fs-5">Parcelas e dados da conta</h2><p class="text-muted small mb-0" id="payable-view-subtitle"></p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body" id="payable-view-content" data-can-settle="<?= $canSettle ? '1' : '0' ?>" data-can-reverse="<?= $canReverse ? '1' : '0' ?>"></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Fechar</button></div></div></div></div>

<?php if ($canEdit): ?><div class="modal fade" id="modal-conta-pagar-edit" tabindex="-1" aria-hidden="true" <?= ($recovery['mode'] ?? '') === 'edit' ? 'data-recovery-open="true"' : '' ?>><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal" method="post" action="actions/conta-pagar-salvar.php" autocomplete="off"><div class="modal-header"><div><h2 class="modal-title fs-5">Editar conta a pagar</h2><p class="text-muted small mb-0" id="payable-edit-subtitle"><?= h(payable_value($editData, 'codigo')) ?></p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="operation" value="update"><input type="hidden" name="id" value="<?= h(payable_value($editData, 'id')) ?>"><?php if (($recovery['mode'] ?? '') === 'edit'): ?><div class="alert alert-danger" role="alert"><?= h((string) $recovery['error']) ?></div><?php endif; ?><?php payable_form_fields('edit-payable', $activeSuppliers, $editData); ?></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-lg"></i> Salvar alterações</button></div></form></div></div><?php endif; ?>

<?php if ($canCancel): ?><div class="modal fade" id="modal-conta-pagar-cancel" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/conta-pagar-cancelar.php"><div class="modal-header"><h2 class="modal-title fs-5">Cancelar conta</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id"><p id="payable-cancel-message"></p><div class="form-group mb-0"><label class="form-label" for="payable-cancel-reason">Motivo do cancelamento</label><textarea class="form-control-os" id="payable-cancel-reason" name="motivo" maxlength="255" rows="3" required></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn-modal-save" type="submit"><i class="bi bi-x-circle"></i> Confirmar cancelamento</button></div></form></div></div></div><?php endif; ?>

<?php if ($canSettle): ?><div class="modal fade" id="modal-conta-pagar-quitar" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/conta-pagar-parcela-quitar.php"><div class="modal-header"><h2 class="modal-title fs-5">Quitar parcela</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="parcela_id"><p id="payable-settle-message"></p><div class="form-group"><label class="form-label" for="payable-settle-method">Forma utilizada</label><select class="form-control-os" id="payable-settle-method" name="forma_pagamento" required><?php payable_payment_options(); ?></select></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check-circle"></i> Confirmar quitação</button></div></form></div></div></div><?php endif; ?>

<?php if ($canReverse): ?><div class="modal fade" id="modal-conta-pagar-estornar" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/conta-pagar-parcela-estornar.php"><div class="modal-header"><h2 class="modal-title fs-5">Estornar quitação</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="parcela_id"><p id="payable-reverse-message"></p><div class="form-group"><label class="form-label" for="payable-reverse-reason">Motivo do estorno</label><textarea class="form-control-os" id="payable-reverse-reason" name="motivo" maxlength="255" rows="3" required></textarea></div><div class="alert alert-warning mb-0">A quitação não será apagada: o estorno ficará registrado no histórico.</div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Voltar</button><button class="btn-modal-save" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Confirmar estorno</button></div></form></div></div></div><?php endif; ?>
