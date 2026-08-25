<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$service = $application->accountsReceivableManagement();
$filters = [
    'bucket' => trim((string) ($_GET['bucket'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'search' => trim((string) ($_GET['search'] ?? '')),
];
$allowedBuckets = ['', 'vencidos', 'hoje', 'semana', '15dias', 'sem_vencimento'];
$allowedStatuses = ['', 'pendente', 'parcial', 'vencida', 'paga', 'estornada', 'cancelada'];
if (!in_array($filters['bucket'], $allowedBuckets, true)) $filters['bucket'] = '';
if (!in_array($filters['status'], $allowedStatuses, true)) $filters['status'] = '';
$searchLength = function_exists('mb_strlen') ? mb_strlen($filters['search'], 'UTF-8') : strlen($filters['search']);
if ($searchLength > 150 || str_contains($filters['search'], "\0")) $filters['search'] = '';
$indicators = $service->indicators();
$accounts = $service->listAccounts($filters);

$canPay = $authorization->can('contas_receber.registrar_pagamento');
$canReversePayment = $authorization->can('contas_receber.estornar_pagamento');
$canEditPayment = $canPay && $canReversePayment;
$canEditAccount = $authorization->can('contas_receber.negociar')
    && $authorization->can('contas_receber.alterar_vencimento')
    && $authorization->can('contas_receber.configurar_lembrete');
$canBatch = $authorization->can('contas_receber.baixa_lote');
$canContact = $authorization->can('contas_receber.registrar_contato');
$canIssueReceipt = $authorization->can('recibo.emitir');
$canReprintReceipt = $authorization->can('recibo.reimprimir');
$canViewFiscal = $authorization->can('nota_fiscal.visualizar');
$canIssueFiscal = $authorization->can('nota_fiscal.emitir');
$fiscalDocumentsByOrder = [];
if ($canViewFiscal || $canIssueFiscal) {
    try {
        $fiscalDocumentsByOrder = $application->fiscalDocuments()->listByOrderIds(array_map(
            static fn(array $account): int => (int) $account['ordem_servico_id'],
            $accounts
        ));
    } catch (Throwable $exception) {
        error_log('Receivables fiscal summary unavailable [' . get_class($exception) . '].');
    }
}
$paymentsByOrder = ($canEditPayment || $canReversePayment || $canIssueReceipt || $canReprintReceipt)
    ? $application->receiptService()->listActivePaymentsForOrders(array_map(
        static fn(array $account): int => (int) $account['ordem_servico_id'],
        $accounts
    ))
    : [];

function cr_money(string $value): string { return money($value); }
function cr_date(?string $value): string { return $value ? h((new DateTimeImmutable($value))->format('d/m/Y')) : 'Sem vencimento'; }
function cr_status_badge(string $status): string { return ['pendente'=>'amber','parcial'=>'blue','vencida'=>'red','paga'=>'green','estornada'=>'purple','cancelada'=>'gray'][$status] ?? 'gray'; }
function cr_status_label(string $status): string { return ['pendente'=>'Pendente','parcial'=>'Parcial','vencida'=>'Vencida','paga'=>'Paga','estornada'=>'Estornada','cancelada'=>'Cancelada'][$status] ?? ucfirst($status); }
function cr_payment_label(array $payment): string
{
    $form = ucfirst(str_replace('_', ' ', (string) ($payment['forma_pagamento'] ?? 'pagamento')));
    $label = $form . ' - ' . cr_money((string) ($payment['valor'] ?? '0'));
    try {
        if (!empty($payment['recebido_em'])) {
            $label .= ' - ' . (new DateTimeImmutable((string) $payment['recebido_em']))->format('d/m/Y');
        }
    } catch (Throwable) {
    }
    return $label;
}
?>

<div class="page-body accounts-receivable-page">
<?php metric_grid([
    ['Total a receber', cr_money($indicators['total']), 'bi-wallet2', '#2563EB', 'pendente'],
    ['Total vencido', cr_money($indicators['overdue']), 'bi-exclamation-triangle', '#DC2626', 'atrasado'],
    ['Vencem hoje', cr_money($indicators['today']), 'bi-calendar-day', '#D97706', 'hoje'],
    ['Vencem na semana', cr_money($indicators['week']), 'bi-calendar-week', '#0F766E', '7 dias'],
    ['Proximos 15 dias', cr_money($indicators['next15']), 'bi-calendar-range', '#7C3AED', '15 dias'],
    ['Recebido hoje', cr_money($indicators['received']), 'bi-cash-coin', '#16A34A', 'pagamentos'],
]); ?>

<form class="filter-bar" method="get" action="contas-receber.php" data-live-filter="receivables" data-live-regions="metrics results">
    <select class="filter-select" name="bucket">
        <option value="">Todos</option>
        <option value="vencidos" <?= $filters['bucket'] === 'vencidos' ? 'selected' : '' ?>>Vencidos</option>
        <option value="hoje" <?= $filters['bucket'] === 'hoje' ? 'selected' : '' ?>>Vencem hoje</option>
        <option value="semana" <?= $filters['bucket'] === 'semana' ? 'selected' : '' ?>>Proximos 7 dias</option>
        <option value="15dias" <?= $filters['bucket'] === '15dias' ? 'selected' : '' ?>>Proximos 15 dias</option>
        <option value="sem_vencimento" <?= $filters['bucket'] === 'sem_vencimento' ? 'selected' : '' ?>>Sem vencimento</option>
    </select>
    <select class="filter-select" name="status">
        <option value="">Todos os status</option>
        <?php foreach (['pendente','parcial','vencida','paga','estornada','cancelada'] as $status): ?>
            <option value="<?= h($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= h(cr_status_label($status)) ?></option>
        <?php endforeach; ?>
    </select>
    <div class="search-wrap"><i class="bi bi-search"></i><input class="search-input" name="search" value="<?= h($filters['search']) ?>" placeholder="Cliente ou OS"></div>
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" href="contas-receber.php" data-live-filter-clear><i class="bi bi-x-lg"></i> Limpar</a>
</form>

<section class="panel" data-live-region="results">
    <div class="panel-header"><div class="panel-title"><i class="bi bi-wallet2"></i>Contas a Receber</div><?php if ($canBatch): ?><button class="btn-filter btn-filter-primary" id="cr-batch-open" type="button" data-bs-toggle="modal" data-bs-target="#modal-cr-batch" disabled><i class="bi bi-check2-square"></i> Dar baixa em lote</button><?php endif; ?></div>
    <?php if ($canBatch): ?><div class="cr-batch-selection" role="note"><i class="bi bi-check2-square" aria-hidden="true"></i><span id="cr-batch-selection" role="status" aria-live="polite">Selecione pelo menos duas contas em aberto do mesmo cliente para dar baixa de uma só vez.</span></div><?php endif; ?>
    <?php if ($accounts === []): ?>
        <?php empty_state('Nenhuma conta encontrada', 'Pagamentos e saldos de OS finalizadas aparecerão aqui.'); ?>
    <?php else: ?>
    <div class="table-panel-wrap"><table class="os-table"><thead><tr><?php if ($canBatch): ?><th class="cr-select-column"><span class="visually-hidden">Selecionar</span></th><?php endif; ?><th>Cliente</th><th>OS</th><th>Valor total</th><th>Recebido</th><th>Saldo</th><th>Vencimento</th><th>Próximo lembrete</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
    <?php foreach ($accounts as $account): ?>
        <?php $batchEligible = $canBatch && in_array((string) $account['status'], ['pendente','parcial','vencida'], true) && (float) $account['saldo'] > 0; $orderPayments = $paymentsByOrder[(int) $account['ordem_servico_id']] ?? []; $accountFiscalDocuments = $fiscalDocumentsByOrder[(int) $account['ordem_servico_id']] ?? []; $accountFiscalModels = array_map(static fn(array $document): string => (string) $document['modelo'], $accountFiscalDocuments); ?>
        <tr>
            <?php if ($canBatch): ?><td class="cr-select-column"><?php if ($batchEligible): ?><input class="form-check-input js-cr-batch-account" type="checkbox" value="<?= h((string) $account['id']) ?>" data-client-id="<?= h((string) $account['cliente_id']) ?>" data-client-name="<?= h((string) $account['cliente_nome']) ?>" data-balance="<?= h((string) $account['saldo']) ?>" data-order="<?= h((string) $account['os_numero']) ?>" aria-label="Selecionar <?= h((string) $account['os_numero']) ?> de <?= h((string) $account['cliente_nome']) ?>"><?php endif; ?></td><?php endif; ?>
            <td><?= h((string) $account['cliente_nome']) ?></td>
            <td><?= h((string) $account['os_numero']) ?></td>
            <td><?= cr_money((string) $account['valor_total']) ?></td>
            <td><?= cr_money((string) $account['valor_recebido']) ?></td>
            <td><?= cr_money((string) $account['saldo']) ?></td>
            <td><?= cr_date($account['vencimento_em'] ?? null) ?></td>
            <td><?= cr_date($account['proximo_lembrete_em'] ?? null) ?></td>
            <td><span class="badge-soft badge-<?= h(cr_status_badge((string) $account['status'])) ?>"><?= h(cr_status_label((string) $account['status'])) ?></span></td>
            <td class="table-actions-cell"><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acoes"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end">
                <?php if ($canEditAccount && !in_array((string) $account['status'], ['cancelada','estornada'], true)): ?>
                    <li><button class="dropdown-item js-cr-account-edit" type="button"
                        data-id="<?= h((string) $account['id']) ?>"
                        data-client="<?= h((string) $account['cliente_nome']) ?>"
                        data-order="<?= h((string) $account['os_numero']) ?>"
                        data-total="<?= h((string) $account['valor_total']) ?>"
                        data-received="<?= h((string) $account['valor_recebido']) ?>"
                        data-balance="<?= h((string) $account['saldo']) ?>"
                        data-due="<?= h((string) ($account['vencimento_em'] ?? '')) ?>"
                        data-reminder="<?= h((string) ($account['proximo_lembrete_em'] ?? '')) ?>"
                        data-notes="<?= h((string) ($account['observacao'] ?? '')) ?>"
                        data-status="<?= h(cr_status_label((string) $account['status'])) ?>"
                        data-bs-toggle="modal" data-bs-target="#modal-cr-account-edit"><i class="bi bi-pencil-square"></i> Editar conta</button></li>
                <?php endif; ?>
                <?php if ($canPay && in_array((string) $account['status'], ['pendente','parcial','vencida'], true)): ?><li><button class="dropdown-item js-cr-payment" type="button" data-id="<?= h((string) $account['id']) ?>" data-balance="<?= h((string) $account['saldo']) ?>" data-bs-toggle="modal" data-bs-target="#modal-cr-payment"><i class="bi bi-cash"></i> Registrar pagamento</button></li><?php endif; ?>
                <?php if ((string) $account['status'] === 'paga'): ?>
                    <?php foreach ($orderPayments as $payment): ?>
                        <?php if (!empty($payment['recibo_id']) && $payment['recibo_status'] === 'emitido' && $canReprintReceipt): ?>
                            <li><a class="dropdown-item" href="recibo-imprimir.php?id=<?= h((string) $payment['recibo_id']) ?>" target="_blank" rel="noopener"><i class="bi bi-receipt-cutoff"></i> Recibo: <?= h(cr_payment_label($payment)) ?></a></li>
                        <?php elseif (empty($payment['recibo_id']) && $canIssueReceipt): ?>
                            <li><button class="dropdown-item js-cr-receipt" type="button" data-payment-id="<?= h((string) $payment['id']) ?>" data-order-number="<?= h((string) $account['os_numero']) ?>" data-payment-label="<?= h(cr_payment_label($payment)) ?>" data-bs-toggle="modal" data-bs-target="#modal-cr-receipt"><i class="bi bi-receipt-cutoff"></i> Gerar recibo: <?= h(cr_payment_label($payment)) ?></button></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($orderPayments !== [] && ($canEditPayment || $canReversePayment)): ?>
                    <?php foreach ($orderPayments as $payment): ?>
                        <?php $paymentLabel = cr_payment_label($payment); $paymentDate = !empty($payment['recebido_em']) ? substr((string) $payment['recebido_em'], 0, 10) : ''; ?>
                        <?php if ($canEditPayment): ?>
                            <li><button class="dropdown-item js-cr-payment-edit" type="button" data-payment-id="<?= h((string) $payment['id']) ?>" data-payment-label="<?= h($paymentLabel) ?>" data-value="<?= h((string) $payment['valor']) ?>" data-form="<?= h((string) $payment['forma_pagamento']) ?>" data-date="<?= h($paymentDate) ?>" data-notes="<?= h((string) ($payment['observacao'] ?? '')) ?>" data-bs-toggle="modal" data-bs-target="#modal-cr-payment-edit"><i class="bi bi-pencil-square"></i> Editar pagamento: <?= h($paymentLabel) ?></button></li>
                        <?php endif; ?>
                        <?php if ($canReversePayment): ?>
                            <li><button class="dropdown-item text-danger js-cr-payment-delete" type="button" data-payment-id="<?= h((string) $payment['id']) ?>" data-payment-label="<?= h($paymentLabel) ?>" data-bs-toggle="modal" data-bs-target="#modal-cr-payment-delete"><i class="bi bi-trash3"></i> Excluir pagamento: <?= h($paymentLabel) ?></button></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ((string) $account['status'] === 'paga'): ?>
                    <?php foreach ($accountFiscalDocuments as $fiscalDocument): ?>
                        <?php if (($fiscalDocument['processamento_status'] ?? '') === 'autorizado' && $canViewFiscal): ?>
                            <li><a class="dropdown-item" href="nota-fiscal-imprimir.php?id=<?= h((string) $fiscalDocument['id']) ?>" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Imprimir <?= ($fiscalDocument['modelo'] ?? '') === '55' ? 'DANFE' : 'DANFCE' ?> autorizada</a></li>
                        <?php else: ?>
                            <li><span class="dropdown-item-text text-muted"><i class="bi bi-file-earmark-lock"></i> Modelo <?= h((string) ($fiscalDocument['modelo'] ?? '')) ?>: <?= h((string) ($fiscalDocument['processamento_status'] ?? 'rascunho')) ?></span></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($canIssueFiscal): ?>
                        <?php foreach (['55' => 'NF-e de peças', '65' => 'NFC-e de peças'] as $fiscalModel => $fiscalLabel): ?>
                            <?php if ($accountFiscalDocuments === []): ?><li><form method="post" action="actions/nota-fiscal-preparar.php"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="ordem_servico_id" value="<?= h((string) $account['ordem_servico_id']) ?>"><input type="hidden" name="modelo" value="<?= h($fiscalModel) ?>"><input type="hidden" name="ambiente" value="homologacao"><input type="hidden" name="idempotency_key" value="<?= h(bin2hex(random_bytes(32))) ?>"><button class="dropdown-item" type="submit"><i class="bi bi-file-earmark-check"></i> Emitir <?= h($fiscalLabel) ?> em homologação</button></form></li><?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
                <li><a class="dropdown-item" href="ordens-servico.php?search=<?= h(rawurlencode((string) $account['os_numero'])) ?>"><i class="bi bi-eye"></i> Abrir OS</a></li>
                <?php if ($canContact && !empty($account['cliente_telefone'])): ?><li><a class="dropdown-item" target="_blank" href="https://wa.me/55<?= h(preg_replace('/\D+/', '', (string) $account['cliente_telefone'])) ?>?text=<?= h(rawurlencode('Ola, ' . $account['cliente_nome'] . '. Consta um saldo pendente de ' . cr_money((string) $account['saldo']) . ' referente a ' . $account['os_numero'] . ', com vencimento em ' . cr_date($account['vencimento_em'] ?? null) . '.')) ?>"><i class="bi bi-whatsapp"></i> Abrir WhatsApp</a></li><?php endif; ?>
            </ul></div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>
</div>


<?php if ($canEditAccount): ?>
<div class="modal fade" id="modal-cr-account-edit" tabindex="-1" aria-hidden="true" aria-labelledby="cr-account-edit-title">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <form class="modal-content visual-modal" method="post" action="actions/conta-receber-editar.php" autocomplete="off" id="cr-account-edit-form">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="cr-account-edit-title">Editar conta a receber</h2>
                    <p class="text-muted small mb-0" id="cr-account-edit-subtitle"></p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?= $csrf->field() ?><?php return_to_field(); ?>
                <input type="hidden" name="id" id="cr-account-edit-id">

                <div class="alert alert-info">
                    <i class="bi bi-shield-check"></i>
                    Você pode corrigir todos os dados próprios da conta. O valor recebido continua vindo dos pagamentos registrados; saldo e situação são recalculados automaticamente para manter Caixa e histórico consistentes.
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="cr-account-edit-client">Cliente</label>
                        <input class="form-control-os" id="cr-account-edit-client" type="text" readonly aria-readonly="true">
                        <small class="form-text text-muted">O cliente pertence à OS de origem.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cr-account-edit-order">Ordem de serviço</label>
                        <input class="form-control-os" id="cr-account-edit-order" type="text" readonly aria-readonly="true">
                        <small class="form-text text-muted">O vínculo com a OS não é trocado pelo financeiro.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cr-account-edit-status">Situação atual</label>
                        <input class="form-control-os" id="cr-account-edit-status" type="text" readonly aria-readonly="true">
                        <small class="form-text text-muted">Será recalculada ao salvar.</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="cr-account-edit-total">Valor total</label>
                        <input class="form-control-os" id="cr-account-edit-total" name="valor_total" inputmode="decimal" required>
                        <small class="form-text text-muted" id="cr-account-edit-total-help"></small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cr-account-edit-received">Valor recebido</label>
                        <input class="form-control-os" id="cr-account-edit-received" type="text" readonly aria-readonly="true">
                        <small class="form-text text-muted">Para corrigir este valor, edite ou exclua os pagamentos da conta.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cr-account-edit-balance">Saldo após alteração</label>
                        <input class="form-control-os" id="cr-account-edit-balance" type="text" readonly aria-readonly="true">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="cr-account-edit-due">Vencimento</label>
                        <input class="form-control-os" id="cr-account-edit-due" name="vencimento_em" type="date">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cr-account-edit-reminder">Próximo lembrete</label>
                        <input class="form-control-os" id="cr-account-edit-reminder" name="proximo_lembrete_em" type="date">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="cr-account-edit-notes">Observação</label>
                    <textarea class="form-control-os" id="cr-account-edit-notes" name="observacao" maxlength="2000" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn-modal-save" type="submit"><i class="bi bi-check2"></i> Salvar alterações</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="modal-cr-payment" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/conta-receber-pagamento.php"><div class="modal-header"><h2 class="modal-title fs-5">Registrar pagamento</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="cr-payment-id"><div class="form-row"><div class="form-group"><label class="form-label">Valor recebido</label><input class="form-control-os" name="valor" id="cr-payment-value" required></div><div class="form-group"><label class="form-label">Forma</label><select class="form-control-os" name="forma_pagamento" required><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="cartao_debito">Cartao de debito</option><option value="cartao_credito">Cartao de credito</option><option value="transferencia">Transferencia</option><option value="outro">Outro</option></select></div><div class="form-group"><label class="form-label" for="cr-payment-date">Data do pagamento</label><input class="form-control-os" id="cr-payment-date" name="data_pagamento" type="date" max="<?= h(date('Y-m-d')) ?>" required><small class="form-text text-muted">Escolha a data real do recebimento.</small></div></div><div class="form-group"><label class="form-label">Observacao</label><textarea class="form-control-os" name="observacao" maxlength="255"></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Registrar</button></div></form></div></div>

<?php if ($canEditPayment): ?>
<div class="modal fade" id="modal-cr-payment-edit" tabindex="-1" aria-hidden="true" aria-labelledby="cr-payment-edit-title">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content visual-modal" method="post" action="actions/conta-receber-pagamento-editar.php" autocomplete="off">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="cr-payment-edit-title">Editar pagamento</h2>
                    <p class="text-muted small mb-0" id="cr-payment-edit-label"></p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?= $csrf->field() ?><?php return_to_field(); ?>
                <input type="hidden" name="pagamento_id" id="cr-payment-edit-id">
                <div class="alert alert-info"><i class="bi bi-shield-check"></i> Se valor, forma ou data forem alterados, o sistema fará uma correção financeira segura: estorna o lançamento anterior e cria o pagamento corrigido.</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="cr-payment-edit-value">Valor recebido</label>
                        <input class="form-control-os" id="cr-payment-edit-value" name="valor" inputmode="decimal" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cr-payment-edit-form">Forma de pagamento</label>
                        <select class="form-control-os" id="cr-payment-edit-form" name="forma_pagamento" required>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="pix">Pix</option>
                            <option value="boleto">Boleto compensado</option>
                            <option value="cartao_debito">Cartão de débito</option>
                            <option value="cartao_credito">Cartão de crédito</option>
                            <option value="transferencia">Transferência</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="cr-payment-edit-date">Data do pagamento</label>
                        <input class="form-control-os" id="cr-payment-edit-date" name="data_pagamento" type="date" max="<?= h(date('Y-m-d')) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="cr-payment-edit-notes">Observação</label>
                    <textarea class="form-control-os" id="cr-payment-edit-notes" name="observacao" maxlength="255" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn-modal-save" type="submit"><i class="bi bi-check2"></i> Salvar alterações</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canReversePayment): ?>
<div class="modal fade" id="modal-cr-payment-delete" tabindex="-1" aria-hidden="true" aria-labelledby="cr-payment-delete-title">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content visual-modal" method="post" action="actions/conta-receber-pagamento-excluir.php" autocomplete="off">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="cr-payment-delete-title">Excluir pagamento</h2>
                    <p class="text-muted small mb-0">O lançamento será estornado sem apagar o histórico financeiro.</p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?= $csrf->field() ?><?php return_to_field(); ?>
                <input type="hidden" name="pagamento_id" id="cr-payment-delete-id">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Você está excluindo <strong id="cr-payment-delete-label"></strong>. O saldo da conta e o Caixa serão corrigidos automaticamente.
                </div>
                <div class="form-group">
                    <label class="form-label" for="cr-payment-delete-reason">Motivo da exclusão</label>
                    <textarea class="form-control-os" id="cr-payment-delete-reason" name="motivo" maxlength="255" rows="3" required placeholder="Ex.: pagamento lançado com valor ou data incorreta"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger" type="submit"><i class="bi bi-trash3"></i> Excluir pagamento</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php if ($canIssueReceipt): ?><div class="modal fade" id="modal-cr-receipt" tabindex="-1" aria-hidden="true" aria-labelledby="cr-receipt-title"><div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="actions/recibo-emitir.php" target="_blank"><div class="modal-header"><div><h2 class="modal-title fs-5" id="cr-receipt-title">Gerar recibo</h2><p class="text-muted small mb-0" id="cr-receipt-order-number"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="pagamento_id" id="cr-receipt-payment-id"><p>Gerar recibo referente a <strong id="cr-receipt-payment-label"></strong>?</p><p class="text-muted small mb-0">Depois escolha entre impressão térmica de 80 mm ou A4 em impressora comum.</p></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Gerar e escolher impressão</button></div></form></div></div><?php endif; ?>

<?php if ($canBatch): ?><div class="modal fade" id="modal-cr-batch" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content visual-modal" id="cr-batch-form" method="post" action="actions/contas-receber-baixa-lote.php"><div class="modal-header"><div><h2 class="modal-title fs-5">Dar baixa em lote</h2><p class="text-muted small mb-0">As contas selecionadas serão quitadas integralmente.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><div id="cr-batch-hidden-ids"></div><div class="alert alert-info" id="cr-batch-summary"></div><div class="cr-batch-account-list" id="cr-batch-account-list"></div><div class="form-row mt-3"><div class="form-group"><label class="form-label" for="cr-batch-form-payment">Forma de pagamento</label><select class="form-control-os" id="cr-batch-form-payment" name="forma_pagamento" required><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="cartao_debito">Cartão de débito</option><option value="cartao_credito">Cartão de crédito</option><option value="transferencia">Transferência</option><option value="outro">Outro</option></select></div><div class="form-group"><label class="form-label" for="cr-batch-payment-date">Data do pagamento</label><input class="form-control-os" id="cr-batch-payment-date" name="data_pagamento" type="date" max="<?= h(date('Y-m-d')) ?>" required><small class="form-text text-muted">A mesma data será aplicada às contas desta baixa em lote.</small></div></div><div class="form-group"><label class="form-label" for="cr-batch-notes">Observação</label><textarea class="form-control-os" id="cr-batch-notes" name="observacao" maxlength="255" rows="3"></textarea></div><div class="alert alert-warning mb-0"><i class="bi bi-shield-check"></i> A operação é transacional: se uma conta falhar, nenhuma baixa será registrada.</div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check2-all"></i> Confirmar baixa em lote</button></div></form></div></div><?php endif; ?>
