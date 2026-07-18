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
$canBatch = $authorization->can('contas_receber.baixa_lote');
$canContact = $authorization->can('contas_receber.registrar_contato');

function cr_money(string $value): string { return money($value); }
function cr_date(?string $value): string { return $value ? h((new DateTimeImmutable($value))->format('d/m/Y')) : 'Sem vencimento'; }
function cr_status_badge(string $status): string { return ['pendente'=>'amber','parcial'=>'blue','vencida'=>'red','paga'=>'green','estornada'=>'purple','cancelada'=>'gray'][$status] ?? 'gray'; }
function cr_status_label(string $status): string { return ['pendente'=>'Pendente','parcial'=>'Parcial','vencida'=>'Vencida','paga'=>'Paga','estornada'=>'Estornada','cancelada'=>'Cancelada'][$status] ?? ucfirst($status); }
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
    <?php if ($canBatch): ?><div class="cr-batch-selection" id="cr-batch-selection" role="status" aria-live="polite">Selecione pelo menos duas contas em aberto do mesmo cliente.</div><?php endif; ?>
    <?php if ($accounts === []): ?>
        <?php empty_state('Nenhuma conta encontrada', 'Pagamentos e saldos de OS finalizadas aparecerão aqui.'); ?>
    <?php else: ?>
    <div class="table-panel-wrap"><table class="os-table"><thead><tr><?php if ($canBatch): ?><th class="cr-select-column"><span class="visually-hidden">Selecionar</span></th><?php endif; ?><th>Cliente</th><th>OS</th><th>Valor total</th><th>Recebido</th><th>Saldo</th><th>Vencimento</th><th>Próximo lembrete</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
    <?php foreach ($accounts as $account): ?>
        <?php $batchEligible = $canBatch && in_array((string) $account['status'], ['pendente','parcial','vencida'], true) && (float) $account['saldo'] > 0; ?>
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
                <?php if ($canPay && in_array((string) $account['status'], ['pendente','parcial','vencida'], true)): ?><li><button class="dropdown-item js-cr-payment" type="button" data-id="<?= h((string) $account['id']) ?>" data-balance="<?= h((string) $account['saldo']) ?>" data-bs-toggle="modal" data-bs-target="#modal-cr-payment"><i class="bi bi-cash"></i> Registrar pagamento</button></li><?php endif; ?>
                <li><a class="dropdown-item" href="ordens-servico.php?search=<?= h(rawurlencode((string) $account['os_numero'])) ?>"><i class="bi bi-eye"></i> Abrir OS</a></li>
                <?php if ($canContact && !empty($account['cliente_telefone'])): ?><li><a class="dropdown-item" target="_blank" href="https://wa.me/55<?= h(preg_replace('/\D+/', '', (string) $account['cliente_telefone'])) ?>?text=<?= h(rawurlencode('Ola, ' . $account['cliente_nome'] . '. Consta um saldo pendente de ' . cr_money((string) $account['saldo']) . ' referente a ' . $account['os_numero'] . ', com vencimento em ' . cr_date($account['vencimento_em'] ?? null) . '.')) ?>"><i class="bi bi-whatsapp"></i> Abrir WhatsApp</a></li><?php endif; ?>
            </ul></div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</section>
</div>

<div class="modal fade" id="modal-cr-payment" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content visual-modal" method="post" action="actions/conta-receber-pagamento.php"><div class="modal-header"><h2 class="modal-title fs-5">Registrar pagamento</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><input type="hidden" name="id" id="cr-payment-id"><div class="form-row"><div class="form-group"><label class="form-label">Valor recebido</label><input class="form-control-os" name="valor" id="cr-payment-value" required></div><div class="form-group"><label class="form-label">Forma</label><select class="form-control-os" name="forma_pagamento"><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="cartao_debito">Cartao de debito</option><option value="cartao_credito">Cartao de credito</option><option value="transferencia">Transferencia</option><option value="outro">Outro</option></select></div></div><div class="form-group"><label class="form-label">Observacao</label><textarea class="form-control-os" name="observacao"></textarea></div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit">Registrar</button></div></form></div></div>

<?php if ($canBatch): ?><div class="modal fade" id="modal-cr-batch" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content visual-modal" id="cr-batch-form" method="post" action="actions/contas-receber-baixa-lote.php"><div class="modal-header"><div><h2 class="modal-title fs-5">Dar baixa em lote</h2><p class="text-muted small mb-0">As contas selecionadas serão quitadas integralmente.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><div id="cr-batch-hidden-ids"></div><div class="alert alert-info" id="cr-batch-summary"></div><div class="cr-batch-account-list" id="cr-batch-account-list"></div><div class="form-row mt-3"><div class="form-group"><label class="form-label" for="cr-batch-form-payment">Forma de pagamento</label><select class="form-control-os" id="cr-batch-form-payment" name="forma_pagamento" required><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="cartao_debito">Cartão de débito</option><option value="cartao_credito">Cartão de crédito</option><option value="transferencia">Transferência</option><option value="outro">Outro</option></select></div></div><div class="form-group"><label class="form-label" for="cr-batch-notes">Observação</label><textarea class="form-control-os" id="cr-batch-notes" name="observacao" maxlength="255" rows="3"></textarea></div><div class="alert alert-warning mb-0"><i class="bi bi-shield-check"></i> A operação é transacional: se uma conta falhar, nenhuma baixa será registrada.</div></div><div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check2-all"></i> Confirmar baixa em lote</button></div></form></div></div><?php endif; ?>
