<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/caixa-ui.php';

$cash = $application->cashManagement();
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date'] ?? '')) === 1 ? (string) $_GET['date'] : date('Y-m-d');
$movements = $cash->listByDate($date);
$sessions = $cash->recentSessions(30);
$canSell = $authorization->can('caixa.registrar_venda');
$canViewSales = $authorization->can('venda_avulsa.visualizar');
$canSeeBalance = $authorization->can('caixa.visualizar_saldo');
$daily = ['entrada' => 0.0, 'saida' => 0.0, 'estorno_entrada' => 0.0, 'estorno_saida' => 0.0];
foreach ($movements as $movement) if (isset($daily[(string) $movement['tipo']])) $daily[(string) $movement['tipo']] += (float) $movement['valor'];
$balance = $daily['entrada'] - $daily['saida'] - $daily['estorno_entrada'] + $daily['estorno_saida'];
?>
<div class="page-body cash-page">
  <nav class="cash-subnav" aria-label="Navegação do Caixa"><a href="caixa.php"><i class="bi bi-grid"></i> Visão geral</a><?php if ($canSell): ?><a href="frente-caixa.php"><i class="bi bi-shop-window"></i> Frente de Caixa</a><?php endif; ?><?php if ($canViewSales): ?><a href="caixa-vendas.php"><i class="bi bi-receipt"></i> Vendas</a><?php endif; ?><a class="active" href="caixa-movimentacoes.php"><i class="bi bi-arrow-left-right"></i> Movimentações</a></nav>
  <?php metric_grid([
      ['Entradas', money(number_format($daily['entrada'], 2, '.', '')), 'bi-arrow-down-circle', '#16A34A', 'na data'],
      ['Saídas', money(number_format($daily['saida'], 2, '.', '')), 'bi-arrow-up-circle', '#DC2626', 'na data'],
      ['Estornos', money(number_format($daily['estorno_entrada'] + $daily['estorno_saida'], 2, '.', '')), 'bi-arrow-counterclockwise', '#D97706', 'compensações'],
      ['Saldo líquido', $canSeeBalance ? money(number_format($balance, 2, '.', '')) : 'Restrito', 'bi-cash-coin', '#2563EB', 'na data'],
  ]); ?>
  <form class="filter-bar" method="get" action="caixa-movimentacoes.php" data-live-filter="cash-movements" data-live-regions="metrics movement-results session-results"><input class="filter-select input-date" type="date" name="date" value="<?= h($date) ?>"><button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button><a class="btn-filter btn-filter-ghost" href="caixa-movimentacoes.php"><i class="bi bi-calendar-check"></i> Hoje</a></form>
  <section class="panel mb-4" data-live-region="movement-results"><div class="panel-header"><div><div class="panel-title"><i class="bi bi-arrow-left-right"></i>Movimentações financeiras</div><small class="text-muted"><?= h((new DateTimeImmutable($date))->format('d/m/Y')) ?></small></div></div><?php if ($movements === []): ?><?php empty_state('Nenhuma movimentação', 'Entradas, saídas, sangrias e suprimentos aparecerão aqui.'); ?><?php else: ?><div class="table-panel-wrap"><table class="os-table cash-movements-table"><thead><tr><th>Horário</th><th>Sessão</th><th>Tipo / origem</th><th>Descrição</th><th>Forma</th><th>Valor</th><th>Usuário</th></tr></thead><tbody><?php foreach ($movements as $movement): $negative = in_array((string) $movement['tipo'], ['saida','estorno_entrada'], true); ?><tr class="cash-movement cash-movement--<?= h((string) $movement['tipo']) ?>"><td><?= h((new DateTimeImmutable((string) $movement['data_movimento']))->format('H:i')) ?></td><td><?= h((string) ($movement['sessao_codigo'] ?? 'Histórico')) ?></td><td><strong><?= h(cash_label((string) $movement['tipo'])) ?></strong><small><?= h(cash_label((string) $movement['origem_tipo'])) ?><?= $movement['origem_id'] === null ? '' : ' #' . h((string) $movement['origem_id']) ?></small></td><td><?= h((string) $movement['descricao']) ?></td><td><?= h(cash_label((string) ($movement['forma_pagamento'] ?? 'outro'))) ?></td><td class="cash-value"><?= $negative ? '− ' : '+ ' ?><?= money((string) $movement['valor']) ?></td><td><?= h((string) $movement['usuario_nome']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
  <section class="panel" data-live-region="session-results"><div class="panel-header"><div class="panel-title"><i class="bi bi-clock-history"></i>Últimas sessões</div></div><?php if ($sessions === []): ?><?php empty_state('Nenhuma sessão registrada', 'A primeira abertura de Caixa iniciará este histórico.'); ?><?php else: ?><div class="table-panel-wrap"><table class="os-table"><thead><tr><th>Sessão</th><th>Status</th><th>Abertura</th><th>Fundo</th><th>Fechamento</th><th>Esperado</th><th>Informado</th><th>Diferença</th></tr></thead><tbody><?php foreach ($sessions as $row): ?><tr><td><strong><?= h((string) $row['codigo']) ?></strong><small><?= h((string) $row['aberto_por_nome']) ?></small></td><td><?= ui_badge(cash_label((string) $row['status'])) ?></td><td><?= h(cash_datetime((string) $row['aberto_em'])) ?></td><td><?= $canSeeBalance ? money((string) $row['valor_abertura']) : '—' ?></td><td><?= h(cash_datetime($row['fechado_em'] === null ? null : (string) $row['fechado_em'])) ?><?php if ($row['fechado_por_nome'] !== null): ?><small><?= h((string) $row['fechado_por_nome']) ?></small><?php endif; ?></td><td><?= $canSeeBalance && $row['saldo_esperado'] !== null ? money((string) $row['saldo_esperado']) : '—' ?></td><td><?= $canSeeBalance && $row['saldo_informado'] !== null ? money((string) $row['saldo_informado']) : '—' ?></td><td class="<?= (float) ($row['diferenca'] ?? 0) === 0.0 ? '' : 'text-danger fw-bold' ?>"><?= $canSeeBalance && $row['diferenca'] !== null ? money((string) $row['diferenca']) : '—' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</div>
