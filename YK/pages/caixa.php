<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$cash = $application->cashManagement();
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date'] ?? '')) === 1 ? (string) $_GET['date'] : date('Y-m-d');
$currentSession = $cash->currentSession();
$sessionSummary = $cash->sessionSummary($currentSession === null ? null : (int) $currentSession['id']);
$movements = $cash->listByDate($date);
$sales = $authorization->can('venda_avulsa.visualizar') ? $cash->listSalesByDate($date) : [];
$sessions = $cash->recentSessions();
$canOpen = $authorization->can('caixa.abrir');
$canClose = $authorization->can('caixa.fechar');
$canWithdrawal = $authorization->can('caixa.sangria');
$canSupply = $authorization->can('caixa.suprimento');
$canSell = $authorization->can('caixa.registrar_venda');
$canReverseSale = $authorization->can('venda_avulsa.estornar');
$canSeeBalance = $authorization->can('caixa.visualizar_saldo');
$products = $canSell && $currentSession !== null ? $cash->availableProducts() : [];
$canSearchClients = $authorization->can('cliente.visualizar');

$daily = ['entrada' => 0.0, 'saida' => 0.0, 'estorno_entrada' => 0.0, 'estorno_saida' => 0.0];
foreach ($movements as $movement) {
    $type = (string) $movement['tipo'];
    if (isset($daily[$type])) $daily[$type] += (float) $movement['valor'];
}
$dailyBalance = $daily['entrada'] - $daily['saida'] - $daily['estorno_entrada'] + $daily['estorno_saida'];

function cash_label(string $value): string
{
    return match ($value) {
        'entrada' => 'Entrada', 'saida' => 'Saída', 'estorno_entrada' => 'Estorno de entrada',
        'estorno_saida' => 'Estorno de saída', 'dinheiro' => 'Dinheiro', 'pix' => 'Pix',
        'boleto' => 'Boleto', 'cartao_debito' => 'Cartão de débito',
        'cartao_credito' => 'Cartão de crédito', 'transferencia' => 'Transferência',
        'cheque' => 'Cheque', 'outro' => 'Outro', 'emitida' => 'Emitida',
        'estornada' => 'Estornada', 'cancelada' => 'Cancelada', 'aberta' => 'Aberta',
        'fechada' => 'Fechada', default => ucfirst(str_replace('_', ' ', $value)),
    };
}

function cash_datetime(?string $value): string
{
    if ($value === null || $value === '') return '-';
    try { return (new DateTimeImmutable($value))->format('d/m/Y H:i'); } catch (Throwable) { return '-'; }
}

function cash_modal(string $id, string $title, string $action, string $body, string $submit, string $icon = 'bi-check-lg'): void
{
    global $csrf;
    ?>
    <div class="modal fade" id="<?= h($id) ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered"><form class="modal-content visual-modal" method="post" action="<?= h($action) ?>">
        <div class="modal-header"><h2 class="modal-title fs-5"><?= h($title) ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><?= $body ?></div>
        <div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi <?= h($icon) ?>"></i> <?= h($submit) ?></button></div>
      </form></div>
    </div>
    <?php
}
?>

<div class="page-body cash-page">
  <section class="cash-session-card <?= $currentSession === null ? 'is-closed' : 'is-open' ?>">
    <div class="cash-session-main">
      <span class="cash-session-icon"><i class="bi <?= $currentSession === null ? 'bi-lock' : 'bi-unlock' ?>"></i></span>
      <div>
        <span class="cash-eyebrow">Sessão operacional</span>
        <h2><?= $currentSession === null ? 'Caixa fechado' : h((string) $currentSession['codigo']) . ' aberto' ?></h2>
        <p><?= $currentSession === null ? 'Abra uma sessão para receber, pagar, vender ou estornar.' : 'Aberto por ' . h((string) $currentSession['aberto_por_nome']) . ' em ' . h(cash_datetime((string) $currentSession['aberto_em'])) ?></p>
      </div>
    </div>
    <div class="cash-session-values">
      <div><span>Fundo inicial</span><strong><?= $currentSession !== null && $canSeeBalance ? money((string) $currentSession['valor_abertura']) : '—' ?></strong></div>
      <div><span>Dinheiro esperado</span><strong><?= $currentSession !== null && $canSeeBalance ? money($sessionSummary['dinheiro_esperado']) : '—' ?></strong></div>
    </div>
    <div class="cash-session-actions">
      <?php if ($currentSession === null && $canOpen): ?><button class="btn-filter btn-filter-primary" type="button" data-bs-toggle="modal" data-bs-target="#cash-open-modal"><i class="bi bi-unlock"></i> Abrir Caixa</button><?php endif; ?>
      <?php if ($currentSession !== null && $canSell): ?><button class="btn-filter btn-filter-primary" type="button" data-bs-toggle="modal" data-bs-target="#cash-pos-modal"><i class="bi bi-cart-plus"></i> Nova venda</button><?php endif; ?>
      <?php if ($currentSession !== null && $canWithdrawal): ?><button class="btn-filter btn-filter-ghost" type="button" data-bs-toggle="modal" data-bs-target="#cash-withdrawal-modal"><i class="bi bi-box-arrow-up"></i> Sangria</button><?php endif; ?>
      <?php if ($currentSession !== null && $canSupply): ?><button class="btn-filter btn-filter-ghost" type="button" data-bs-toggle="modal" data-bs-target="#cash-supply-modal"><i class="bi bi-box-arrow-in-down"></i> Suprimento</button><?php endif; ?>
      <?php if ($currentSession !== null && $canClose): ?><button class="btn-filter btn-filter-danger" type="button" data-bs-toggle="modal" data-bs-target="#cash-close-modal"><i class="bi bi-lock"></i> Fechar Caixa</button><?php endif; ?>
    </div>
  </section>

  <?php metric_grid([
      ['Entradas', money(number_format($daily['entrada'], 2, '.', '')), 'bi-arrow-down-circle', '#16A34A', 'na data filtrada'],
      ['Saídas', money(number_format($daily['saida'], 2, '.', '')), 'bi-arrow-up-circle', '#DC2626', 'na data filtrada'],
      ['Estornos', money(number_format($daily['estorno_entrada'] + $daily['estorno_saida'], 2, '.', '')), 'bi-arrow-counterclockwise', '#D97706', 'compensações'],
      ['Saldo líquido', $canSeeBalance ? money(number_format($dailyBalance, 2, '.', '')) : 'Restrito', 'bi-cash-coin', '#2563EB', 'na data filtrada'],
  ]); ?>

  <form class="filter-bar" method="get" action="caixa.php" data-live-filter="cash" data-live-regions="metrics cash-results sales-results sessions-results">
    <input class="filter-select input-date" type="date" name="date" value="<?= h($date) ?>" aria-label="Data do Caixa">
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" href="caixa.php"><i class="bi bi-calendar-check"></i> Hoje</a>
  </form>

  <div class="cash-dashboard-grid">
    <section class="panel" data-live-region="cash-results">
      <div class="panel-header"><div><div class="panel-title"><i class="bi bi-arrow-left-right"></i>Movimentações</div><small class="text-muted"><?= h((new DateTimeImmutable($date))->format('d/m/Y')) ?></small></div></div>
      <?php if ($movements === []): ?><?php empty_state('Nenhuma movimentação', 'As operações auditáveis do dia aparecerão aqui.'); ?>
      <?php else: ?><div class="table-panel-wrap"><table class="os-table cash-movements-table"><thead><tr><th>Horário</th><th>Sessão</th><th>Tipo / origem</th><th>Descrição</th><th>Forma</th><th>Valor</th><th>Usuário</th></tr></thead><tbody>
      <?php foreach ($movements as $movement): $negative = in_array((string) $movement['tipo'], ['saida', 'estorno_entrada'], true); ?>
        <tr class="cash-movement cash-movement--<?= h((string) $movement['tipo']) ?>">
          <td><?= h((new DateTimeImmutable((string) $movement['data_movimento']))->format('H:i')) ?></td>
          <td><?= h((string) ($movement['sessao_codigo'] ?? 'Histórico')) ?></td>
          <td><strong><?= h(cash_label((string) $movement['tipo'])) ?></strong><small><?= h(cash_label((string) $movement['origem_tipo'])) ?><?= $movement['origem_id'] === null ? '' : ' #' . h((string) $movement['origem_id']) ?></small></td>
          <td><?= h((string) $movement['descricao']) ?></td><td><?= h(cash_label((string) ($movement['forma_pagamento'] ?? 'outro'))) ?></td>
          <td class="cash-value"><?= $negative ? '− ' : '+ ' ?><?= money((string) $movement['valor']) ?></td><td><?= h((string) $movement['usuario_nome']) ?></td>
        </tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>

    <?php if ($authorization->can('venda_avulsa.visualizar')): ?>
    <section class="panel" data-live-region="sales-results">
      <div class="panel-header"><div class="panel-title"><i class="bi bi-receipt"></i>Vendas do PDV</div><?php if ($currentSession !== null && $canSell): ?><button class="btn-filter btn-filter-primary" type="button" data-bs-toggle="modal" data-bs-target="#cash-pos-modal"><i class="bi bi-plus-lg"></i> Venda</button><?php endif; ?></div>
      <?php if ($sales === []): ?><?php empty_state('Nenhuma venda nesta data', 'Use o PDV durante uma sessão aberta.'); ?>
      <?php else: ?><div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Número</th><th>Cliente</th><th>Itens</th><th>Pagamento</th><th>Total</th><th>Status</th><th>Ações</th></tr></thead><tbody>
      <?php foreach ($sales as $sale): ?><tr class="cash-sale-row cash-sale-row--<?= h((string) $sale['status']) ?>">
        <td><strong><?= h((string) $sale['numero']) ?></strong><small><?= h(cash_datetime((string) $sale['criada_em'])) ?></small></td>
        <td><?= h((string) ($sale['cliente_nome'] ?? 'Consumidor final')) ?></td><td><?= h((string) $sale['itens']) ?></td>
        <td><?= h(cash_label((string) $sale['forma_pagamento'])) ?></td><td><strong><?= money((string) $sale['total']) ?></strong></td><td><?= ui_badge(cash_label((string) $sale['status'])) ?></td>
        <td class="table-actions-cell"><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-label="Ações da venda <?= h((string) $sale['numero']) ?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end">
          <?php if ($canReverseSale && $currentSession !== null && $sale['status'] === 'emitida'): ?><li><button class="dropdown-item text-danger js-reverse-sale" type="button" data-bs-toggle="modal" data-bs-target="#cash-sale-reversal-modal" data-sale-id="<?= h((string) $sale['id']) ?>" data-sale-number="<?= h((string) $sale['numero']) ?>"><i class="bi bi-arrow-counterclockwise"></i> Estornar venda</button></li><?php else: ?><li><span class="dropdown-item-text text-muted">Sem ações disponíveis</span></li><?php endif; ?>
        </ul></div></td>
      </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </section>
    <?php endif; ?>
  </div>

  <section class="panel" data-live-region="sessions-results">
    <div class="panel-header"><div class="panel-title"><i class="bi bi-clock-history"></i>Histórico de sessões</div></div>
    <div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Sessão</th><th>Status</th><th>Abertura</th><th>Fundo</th><th>Fechamento</th><th>Esperado</th><th>Informado</th><th>Diferença</th></tr></thead><tbody>
    <?php foreach ($sessions as $sessionRow): ?><tr>
      <td><strong><?= h((string) $sessionRow['codigo']) ?></strong><small><?= h((string) $sessionRow['aberto_por_nome']) ?></small></td><td><?= ui_badge(cash_label((string) $sessionRow['status'])) ?></td>
      <td><?= h(cash_datetime((string) $sessionRow['aberto_em'])) ?></td><td><?= $canSeeBalance ? money((string) $sessionRow['valor_abertura']) : '—' ?></td>
      <td><?= h(cash_datetime($sessionRow['fechado_em'] === null ? null : (string) $sessionRow['fechado_em'])) ?><?php if ($sessionRow['fechado_por_nome'] !== null): ?><small><?= h((string) $sessionRow['fechado_por_nome']) ?></small><?php endif; ?></td>
      <td><?= $canSeeBalance && $sessionRow['saldo_esperado'] !== null ? money((string) $sessionRow['saldo_esperado']) : '—' ?></td><td><?= $canSeeBalance && $sessionRow['saldo_informado'] !== null ? money((string) $sessionRow['saldo_informado']) : '—' ?></td>
      <td class="<?= (float) ($sessionRow['diferenca'] ?? 0) === 0.0 ? '' : 'text-danger fw-bold' ?>"><?= $canSeeBalance && $sessionRow['diferenca'] !== null ? money((string) $sessionRow['diferenca']) : '—' ?></td>
    </tr><?php endforeach; ?></tbody></table></div>
  </section>
</div>

<?php if ($currentSession === null && $canOpen):
  ob_start(); ?><div class="form-group"><label class="form-label" for="cash-opening-value">Fundo inicial em dinheiro</label><input class="form-control-os" id="cash-opening-value" name="valor_abertura" inputmode="decimal" value="0,00" required></div><div class="form-group"><label class="form-label" for="cash-opening-notes">Observação</label><textarea class="form-control-os" id="cash-opening-notes" name="observacao" rows="2" maxlength="255"></textarea></div><?php cash_modal('cash-open-modal', 'Abrir Caixa', 'actions/caixa-abrir.php', (string) ob_get_clean(), 'Abrir Caixa', 'bi-unlock'); endif; ?>

<?php if ($currentSession !== null && $canClose):
  ob_start(); ?><div class="cash-close-summary"><span>Dinheiro esperado</span><strong><?= $canSeeBalance ? money($sessionSummary['dinheiro_esperado']) : 'Valor restrito' ?></strong><small>Conte somente o dinheiro físico da gaveta. Pix, cartões e transferências não entram nesta conferência.</small></div><div class="form-group"><label class="form-label" for="cash-counted-value">Dinheiro contado</label><input class="form-control-os" id="cash-counted-value" name="saldo_informado" inputmode="decimal" required></div><div class="form-group"><label class="form-label" for="cash-closing-notes">Observação do fechamento</label><textarea class="form-control-os" id="cash-closing-notes" name="observacao" rows="2" maxlength="255"></textarea></div><?php cash_modal('cash-close-modal', 'Conferir e fechar Caixa', 'actions/caixa-fechar.php', (string) ob_get_clean(), 'Fechar Caixa', 'bi-lock'); endif; ?>

<?php if ($currentSession !== null && $canWithdrawal): ob_start(); ?><div class="alert alert-warning">A sangria retira apenas dinheiro físico e não pode superar o saldo disponível.</div><div class="form-group"><label class="form-label" for="cash-withdrawal-value">Valor</label><input class="form-control-os" id="cash-withdrawal-value" name="valor" inputmode="decimal" required></div><div class="form-group"><label class="form-label" for="cash-withdrawal-reason">Motivo</label><textarea class="form-control-os" id="cash-withdrawal-reason" name="motivo" rows="2" maxlength="220" required></textarea></div><?php cash_modal('cash-withdrawal-modal', 'Registrar sangria', 'actions/caixa-sangria.php', (string) ob_get_clean(), 'Confirmar sangria', 'bi-box-arrow-up'); endif; ?>

<?php if ($currentSession !== null && $canSupply): ob_start(); ?><div class="form-group"><label class="form-label" for="cash-supply-value">Valor em dinheiro</label><input class="form-control-os" id="cash-supply-value" name="valor" inputmode="decimal" required></div><div class="form-group"><label class="form-label" for="cash-supply-reason">Origem / motivo</label><textarea class="form-control-os" id="cash-supply-reason" name="motivo" rows="2" maxlength="220" required></textarea></div><?php cash_modal('cash-supply-modal', 'Registrar suprimento', 'actions/caixa-suprimento.php', (string) ob_get_clean(), 'Confirmar suprimento', 'bi-box-arrow-in-down'); endif; ?>

<?php if ($currentSession !== null && $canSell): ?>
<div class="modal fade" id="cash-pos-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form class="modal-content visual-modal" id="cash-pos-form" method="post" action="actions/caixa-venda-salvar.php">
  <div class="modal-header"><div><h2 class="modal-title fs-5">PDV · Nova venda</h2><p class="text-muted small mb-0">Preço e estoque são confirmados novamente no servidor.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
  <div class="modal-body"><?= $csrf->field() ?><?php return_to_field(); ?><div class="pos-layout"><section>
    <div class="form-row"><div class="form-group pos-client-picker"><label class="form-label" for="pos-client-search">Cliente</label><input type="hidden" id="pos-client-id" name="cliente_id"><input class="form-control-os" id="pos-client-search" placeholder="<?= $canSearchClients ? 'Consumidor final ou busque por nome, código ou documento' : 'Consumidor final' ?>" autocomplete="off" <?= $canSearchClients ? '' : 'disabled' ?>><div class="pos-client-results d-none" id="pos-client-results" role="listbox"></div></div><div class="form-group"><label class="form-label" for="pos-payment-form">Forma de pagamento</label><select class="form-control-os" id="pos-payment-form" name="forma_pagamento" required><?php foreach (App\Finance\Service\CashManagementService::paymentForms() as $form): ?><option value="<?= h($form) ?>"><?= h(cash_label($form)) ?></option><?php endforeach; ?></select></div></div>
    <div class="pos-product-picker"><div class="form-group"><label class="form-label" for="pos-product-search">Buscar / ler código</label><input class="form-control-os" id="pos-product-search" placeholder="Nome, código ou código de barras"></div><div class="form-group"><label class="form-label" for="pos-product">Produto</label><select class="form-control-os" id="pos-product"><option value="">Selecione</option><?php foreach ($products as $product): ?><option value="<?= h((string) $product['id']) ?>" data-price="<?= h((string) $product['preco_venda']) ?>" data-stock="<?= h((string) $product['estoque']) ?>" data-unit="<?= h((string) $product['unidade']) ?>" data-search="<?= h(strtolower((string) ($product['codigo'] . ' ' . $product['nome'] . ' ' . ($product['codigo_barras'] ?? '')))) ?>"><?= h((string) $product['nome']) ?> · <?= money((string) $product['preco_venda']) ?> · est. <?= h((string) $product['estoque']) ?></option><?php endforeach; ?></select></div><div class="form-group pos-quantity"><label class="form-label" for="pos-quantity">Qtd.</label><input class="form-control-os" id="pos-quantity" inputmode="decimal" value="1"></div><button class="btn-filter btn-filter-primary" id="pos-add-product" type="button"><i class="bi bi-plus-lg"></i> Adicionar</button></div>
    <div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Produto</th><th>Qtd.</th><th>Unitário</th><th>Subtotal</th><th></th></tr></thead><tbody id="pos-cart-body"><tr class="pos-cart-empty"><td colspan="5">Nenhum produto adicionado.</td></tr></tbody></table></div><div id="pos-hidden-items"></div>
  </section><aside class="pos-summary"><h3>Resumo</h3><div><span>Subtotal</span><strong id="pos-subtotal">R$ 0,00</strong></div><label><span>Desconto</span><input class="form-control-os" id="pos-discount" name="desconto" inputmode="decimal" value="0,00"></label><label><span>Acréscimo</span><input class="form-control-os" id="pos-increase" name="acrescimo" inputmode="decimal" value="0,00"></label><div class="pos-total"><span>Total</span><strong id="pos-total">R$ 0,00</strong></div><small>A venda baixa o estoque e lança a entrada nesta sessão automaticamente.</small></aside></div></div>
  <div class="modal-footer"><button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn-modal-save" type="submit"><i class="bi bi-check2-circle"></i> Finalizar venda</button></div>
</form></div></div>
<?php endif; ?>

<?php if ($currentSession !== null && $canReverseSale): ob_start(); ?><input type="hidden" id="cash-reversal-sale-id" name="venda_id"><p>Esta operação devolverá todos os produtos ao estoque e criará um estorno financeiro na sessão atual.</p><div class="cash-close-summary"><span>Venda selecionada</span><strong id="cash-reversal-sale-number">—</strong></div><div class="form-group"><label class="form-label" for="cash-reversal-reason">Motivo obrigatório</label><textarea class="form-control-os" id="cash-reversal-reason" name="motivo" rows="3" maxlength="255" required></textarea></div><?php cash_modal('cash-sale-reversal-modal', 'Estornar venda do PDV', 'actions/caixa-venda-estornar.php', (string) ob_get_clean(), 'Estornar venda', 'bi-arrow-counterclockwise'); endif; ?>
