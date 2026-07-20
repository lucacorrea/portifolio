<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/caixa-ui.php';

$cash = $application->cashManagement();
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date'] ?? '')) === 1 ? (string) $_GET['date'] : date('Y-m-d');
$sales = $cash->listSalesByDate($date);
$currentSession = $cash->currentSession();
$canSell = $authorization->can('caixa.registrar_venda');
$canReverse = $authorization->can('venda_avulsa.estornar');
$totals = ['emitida' => 0.0, 'estornada' => 0.0, 'count' => count($sales)];
foreach ($sales as $sale) {
    if (isset($totals[(string) $sale['status']])) $totals[(string) $sale['status']] += (float) $sale['total'];
}
?>
<div class="page-body cash-page">
  <nav class="cash-subnav" aria-label="Navegação do Caixa"><a href="caixa.php"><i class="bi bi-grid"></i> Visão geral</a><?php if ($canSell): ?><a href="frente-caixa.php"><i class="bi bi-shop-window"></i> Frente de Caixa</a><?php endif; ?><a class="active" href="caixa-vendas.php"><i class="bi bi-receipt"></i> Vendas</a><a href="caixa-movimentacoes.php"><i class="bi bi-arrow-left-right"></i> Movimentações</a></nav>
  <?php metric_grid([
      ['Vendas realizadas', (string) $totals['count'], 'bi-receipt', '#2563EB', 'na data'],
      ['Valor vendido', money(number_format($totals['emitida'], 2, '.', '')), 'bi-cash-stack', '#16A34A', 'vendas ativas'],
      ['Valor estornado', money(number_format($totals['estornada'], 2, '.', '')), 'bi-arrow-counterclockwise', '#DC2626', 'vendas estornadas'],
      ['Situação do Caixa', $currentSession === null ? 'Fechado' : 'Aberto', $currentSession === null ? 'bi-lock' : 'bi-unlock', $currentSession === null ? '#DC2626' : '#0F766E', $currentSession['codigo'] ?? 'sem sessão'],
  ]); ?>
  <form class="filter-bar" method="get" action="caixa-vendas.php" data-live-filter="cash-sales" data-live-regions="metrics sales-results"><input class="filter-select input-date" type="date" name="date" value="<?= h($date) ?>" aria-label="Data das vendas"><button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button><a class="btn-filter btn-filter-ghost" href="caixa-vendas.php"><i class="bi bi-calendar-check"></i> Hoje</a><?php if ($canSell): ?><a class="btn-filter btn-filter-primary ms-auto" href="frente-caixa.php"><i class="bi bi-plus-lg"></i> Nova venda</a><?php endif; ?></form>
  <section class="panel" data-live-region="sales-results"><div class="panel-header"><div><div class="panel-title"><i class="bi bi-receipt"></i>Vendas de peças</div><small class="text-muted"><?= h((new DateTimeImmutable($date))->format('d/m/Y')) ?></small></div></div>
  <?php if ($sales === []): ?><?php empty_state('Nenhuma venda nesta data', 'As vendas concluídas na Frente de Caixa aparecerão aqui.'); ?>
  <?php else: ?><div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Número</th><th>Horário / sessão</th><th>Cliente</th><th>Itens</th><th>Pagamento</th><th>Total</th><th>Operador</th><th>Status</th><th>Ações</th></tr></thead><tbody>
  <?php foreach ($sales as $sale): ?><tr class="cash-sale-row cash-sale-row--<?= h((string) $sale['status']) ?>"><td><strong><?= h((string) $sale['numero']) ?></strong></td><td><?= h(cash_datetime((string) $sale['criada_em'])) ?><small><?= h((string) ($sale['sessao_codigo'] ?? 'Histórico')) ?></small></td><td><?= h((string) ($sale['cliente_nome'] ?? 'Consumidor final')) ?></td><td><strong><?= h((string) $sale['itens']) ?> item(ns)</strong><small title="<?= h((string) ($sale['itens_resumo'] ?? '')) ?>"><?= h((string) ($sale['itens_resumo'] ?? 'Sem descrição')) ?></small></td><td><?= h(cash_label((string) $sale['forma_pagamento'])) ?></td><td><strong><?= money((string) $sale['total']) ?></strong></td><td><?= h((string) $sale['usuario_nome']) ?></td><td><?= ui_badge(cash_label((string) $sale['status'])) ?></td><td class="table-actions-cell"><div class="dropdown table-action-dropdown"><button class="btn-action" type="button" data-bs-toggle="dropdown" aria-label="Ações da venda <?= h((string) $sale['numero']) ?>"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><?php if ($canReverse && $currentSession !== null && $sale['status'] === 'emitida'): ?><li><button class="dropdown-item text-danger js-reverse-sale" type="button" data-bs-toggle="modal" data-bs-target="#cash-sale-reversal-modal" data-sale-id="<?= h((string) $sale['id']) ?>" data-sale-number="<?= h((string) $sale['numero']) ?>"><i class="bi bi-arrow-counterclockwise"></i> Estornar venda</button></li><?php else: ?><li><span class="dropdown-item-text text-muted">Sem ações disponíveis</span></li><?php endif; ?></ul></div></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?></section>
</div>
<?php if ($canReverse && $currentSession !== null): ob_start(); ?><input type="hidden" id="cash-reversal-sale-id" name="venda_id"><p>Os produtos voltarão ao estoque e o financeiro receberá uma movimentação compensatória.</p><div class="cash-close-summary"><span>Venda selecionada</span><strong id="cash-reversal-sale-number">—</strong></div><div class="form-group"><label class="form-label" for="cash-reversal-reason">Motivo obrigatório</label><textarea class="form-control-os" id="cash-reversal-reason" name="motivo" rows="3" maxlength="255" required></textarea></div><?php cash_modal('cash-sale-reversal-modal', 'Estornar venda do Caixa', 'actions/caixa-venda-estornar.php', (string) ob_get_clean(), 'Estornar venda', 'bi-arrow-counterclockwise'); endif; ?>
