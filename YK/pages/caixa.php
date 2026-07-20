<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/caixa-ui.php';

$cash = $application->cashManagement();
$currentSession = $cash->currentSession();
$summary = $cash->sessionSummary($currentSession === null ? null : (int) $currentSession['id']);
$canOpen = $authorization->can('caixa.abrir');
$canClose = $authorization->can('caixa.fechar');
$canWithdrawal = $authorization->can('caixa.sangria');
$canSupply = $authorization->can('caixa.suprimento');
$canSell = $authorization->can('caixa.registrar_venda');
$canViewSales = $authorization->can('venda_avulsa.visualizar');
$canSeeBalance = $authorization->can('caixa.visualizar_saldo');
?>

<div class="page-body cash-page">
  <section class="cash-session-card <?= $currentSession === null ? 'is-closed' : 'is-open' ?>">
    <div class="cash-session-main"><span class="cash-session-icon"><i class="bi <?= $currentSession === null ? 'bi-lock' : 'bi-unlock' ?>"></i></span><div><span class="cash-eyebrow">Sessão operacional</span><h2><?= $currentSession === null ? 'Caixa fechado' : h((string) $currentSession['codigo']) . ' aberto' ?></h2><p><?= $currentSession === null ? 'Abra uma sessão para liberar vendas e movimentações financeiras.' : 'Aberto por ' . h((string) $currentSession['aberto_por_nome']) . ' em ' . h(cash_datetime((string) $currentSession['aberto_em'])) ?></p></div></div>
    <div class="cash-session-values"><div><span>Fundo inicial</span><strong><?= $currentSession !== null && $canSeeBalance ? money((string) $currentSession['valor_abertura']) : '—' ?></strong></div><div><span>Dinheiro esperado</span><strong><?= $currentSession !== null && $canSeeBalance ? money($summary['dinheiro_esperado']) : '—' ?></strong></div></div>
    <div class="cash-session-actions">
      <?php if ($currentSession === null && $canOpen): ?><button class="btn-filter btn-filter-primary" type="button" data-bs-toggle="modal" data-bs-target="#cash-open-modal"><i class="bi bi-unlock"></i> Abrir Caixa</button><?php endif; ?>
      <?php if ($currentSession !== null && $canWithdrawal): ?><button class="btn-filter btn-filter-ghost" type="button" data-bs-toggle="modal" data-bs-target="#cash-withdrawal-modal"><i class="bi bi-box-arrow-up"></i> Sangria</button><?php endif; ?>
      <?php if ($currentSession !== null && $canSupply): ?><button class="btn-filter btn-filter-ghost" type="button" data-bs-toggle="modal" data-bs-target="#cash-supply-modal"><i class="bi bi-box-arrow-in-down"></i> Suprimento</button><?php endif; ?>
      <?php if ($currentSession !== null && $canClose): ?><button class="btn-filter btn-filter-danger" type="button" data-bs-toggle="modal" data-bs-target="#cash-close-modal"><i class="bi bi-lock"></i> Fechar Caixa</button><?php endif; ?>
    </div>
  </section>

  <?php metric_grid([
      ['Entradas da sessão', $canSeeBalance ? money($summary['entrada']) : 'Restrito', 'bi-arrow-down-circle', '#16A34A', 'sessão atual'],
      ['Saídas da sessão', $canSeeBalance ? money($summary['saida']) : 'Restrito', 'bi-arrow-up-circle', '#DC2626', 'sessão atual'],
      ['Estornos', $canSeeBalance ? money($summary['estornos']) : 'Restrito', 'bi-arrow-counterclockwise', '#D97706', 'sessão atual'],
      ['Saldo líquido', $canSeeBalance ? money($summary['saldo']) : 'Restrito', 'bi-cash-stack', '#2563EB', 'sem fundo inicial'],
  ]); ?>

  <section class="cash-module-grid" aria-label="Áreas do Caixa">
    <?php if ($canSell): ?><a class="cash-module-card cash-module-card--primary" href="frente-caixa.php"><span class="cash-module-icon"><i class="bi bi-shop-window"></i></span><div><strong>Frente de Caixa</strong><p>Venda rápida de peças com código de barras, carrinho, pagamento e baixa de estoque.</p></div><span class="cash-module-go">Abrir PDV <i class="bi bi-arrow-right"></i></span></a><?php endif; ?>
    <?php if ($canViewSales): ?><a class="cash-module-card" href="caixa-vendas.php"><span class="cash-module-icon"><i class="bi bi-receipt"></i></span><div><strong>Vendas do Caixa</strong><p>Consulte vendas realizadas, formas de pagamento, operadores e estornos.</p></div><span class="cash-module-go">Ver vendas <i class="bi bi-arrow-right"></i></span></a><?php endif; ?>
    <a class="cash-module-card" href="caixa-movimentacoes.php"><span class="cash-module-icon"><i class="bi bi-arrow-left-right"></i></span><div><strong>Movimentações e sessões</strong><p>Acompanhe entradas, saídas, sangrias, suprimentos, conferências e fechamentos.</p></div><span class="cash-module-go">Ver histórico <i class="bi bi-arrow-right"></i></span></a>
    <?php if ($authorization->can('produto.visualizar')): ?><a class="cash-module-card" href="produtos.php"><span class="cash-module-icon"><i class="bi bi-box-seam"></i></span><div><strong>Peças e estoque</strong><p>Consulte preços, códigos, localização, saldo disponível e estoque mínimo.</p></div><span class="cash-module-go">Ver estoque <i class="bi bi-arrow-right"></i></span></a><?php endif; ?>
  </section>

  <?php if ($currentSession === null): ?><div class="alert alert-warning mt-4 mb-0"><i class="bi bi-exclamation-triangle me-2"></i>O PDV pode ser consultado, mas nenhuma venda ou movimentação financeira será concluída enquanto o Caixa estiver fechado.</div><?php endif; ?>
</div>

<?php if ($currentSession === null && $canOpen): ob_start(); ?><div class="form-group"><label class="form-label" for="cash-opening-value">Fundo inicial em dinheiro</label><input class="form-control-os" id="cash-opening-value" name="valor_abertura" inputmode="decimal" value="0,00" required></div><div class="form-group"><label class="form-label" for="cash-opening-notes">Observação</label><textarea class="form-control-os" id="cash-opening-notes" name="observacao" rows="2" maxlength="255"></textarea></div><?php cash_modal('cash-open-modal', 'Abrir Caixa', 'actions/caixa-abrir.php', (string) ob_get_clean(), 'Abrir Caixa', 'bi-unlock'); endif; ?>
<?php if ($currentSession !== null && $canClose): ob_start(); ?><div class="cash-close-summary"><span>Dinheiro esperado</span><strong><?= $canSeeBalance ? money($summary['dinheiro_esperado']) : 'Valor restrito' ?></strong><small>Conte somente o dinheiro físico da gaveta.</small></div><div class="form-group"><label class="form-label" for="cash-counted-value">Dinheiro contado</label><input class="form-control-os" id="cash-counted-value" name="saldo_informado" inputmode="decimal" required></div><div class="form-group"><label class="form-label" for="cash-closing-notes">Observação do fechamento</label><textarea class="form-control-os" id="cash-closing-notes" name="observacao" rows="2" maxlength="255"></textarea></div><?php cash_modal('cash-close-modal', 'Conferir e fechar Caixa', 'actions/caixa-fechar.php', (string) ob_get_clean(), 'Fechar Caixa', 'bi-lock'); endif; ?>
<?php if ($currentSession !== null && $canWithdrawal): ob_start(); ?><div class="alert alert-warning">A sangria retira apenas dinheiro físico e não pode superar o saldo disponível.</div><div class="form-group"><label class="form-label" for="cash-withdrawal-value">Valor</label><input class="form-control-os" id="cash-withdrawal-value" name="valor" inputmode="decimal" required></div><div class="form-group"><label class="form-label" for="cash-withdrawal-reason">Motivo</label><textarea class="form-control-os" id="cash-withdrawal-reason" name="motivo" rows="2" maxlength="220" required></textarea></div><?php cash_modal('cash-withdrawal-modal', 'Registrar sangria', 'actions/caixa-sangria.php', (string) ob_get_clean(), 'Confirmar sangria', 'bi-box-arrow-up'); endif; ?>
<?php if ($currentSession !== null && $canSupply): ob_start(); ?><div class="form-group"><label class="form-label" for="cash-supply-value">Valor em dinheiro</label><input class="form-control-os" id="cash-supply-value" name="valor" inputmode="decimal" required></div><div class="form-group"><label class="form-label" for="cash-supply-reason">Origem / motivo</label><textarea class="form-control-os" id="cash-supply-reason" name="motivo" rows="2" maxlength="220" required></textarea></div><?php cash_modal('cash-supply-modal', 'Registrar suprimento', 'actions/caixa-suprimento.php', (string) ob_get_clean(), 'Confirmar suprimento', 'bi-box-arrow-in-down'); endif; ?>
