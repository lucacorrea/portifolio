<?php
$adminTitle = 'Pedidos';
$activeAdmin = 'pedidos';
require_once __DIR__ . '/../includes/orders.php';
require_once __DIR__ . '/../includes/whatsapp.php';
require_once __DIR__ . '/../includes/admin-head.php';

$filters = order_admin_filters_from_request($_GET);
$pedidos = orders_admin_list($filters);
$stats = orders_admin_stats($filters);
$csrf = admin_csrf_token();
$successMessages = [
    'status_atualizado' => 'Status do pedido atualizado.',
    'pagamento_confirmado' => 'Pagamento confirmado manualmente.',
    'pagamento_cancelado' => 'Pagamento cancelado.',
    'whatsapp_reenviado' => 'Notificação WhatsApp reenviada ou simulada.',
];
$errorMessages = [
    'acao_invalida' => 'Ação inválida. Recarregue a página e tente novamente.',
    'pedido_invalido' => 'Pedido não encontrado ou dados inválidos.',
    'falha_status' => 'Não foi possível atualizar o status.',
    'falha_pagamento' => 'Não foi possível atualizar o pagamento.',
    'whatsapp_nao_enviado' => 'Não foi possível reenviar a notificação WhatsApp.',
];
$successKey = is_string($_GET['success'] ?? null) ? (string) $_GET['success'] : '';
$errorKey = is_string($_GET['error'] ?? null) ? (string) $_GET['error'] : '';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Operação</span>
    <h1>Pedidos</h1>
    <p>Fila real de pedidos do catálogo, com pagamento manual, status e notificações WhatsApp.</p>
  </div>
  <div class="admin-hero-actions"><a class="btn btn-primary" href="<?= site_url('admin/caixa.php') ?>">Abrir PDV</a></div>
</section>

<?php if ($successKey !== '' && isset($successMessages[$successKey])): ?>
  <section class="admin-alert-card admin-alert-success" role="status" data-admin-flash><strong>Sucesso</strong><?= e($successMessages[$successKey]) ?></section>
<?php endif; ?>
<?php if ($errorKey !== '' && isset($errorMessages[$errorKey])): ?>
  <section class="admin-alert-card admin-alert-danger" role="alert"><strong>Atenção</strong><?= e($errorMessages[$errorKey]) ?></section>
<?php endif; ?>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Pedidos hoje</span><strong><?= (int) $stats['pedidos_hoje'] ?></strong><small>Catálogo e canais internos</small></article>
  <article class="admin-kpi-card"><span>Aguardando pagamento</span><strong><?= (int) $stats['aguardando_pagamento'] ?></strong><small>Pix/manual pendente</small></article>
  <article class="admin-kpi-card"><span>Em preparo</span><strong><?= (int) $stats['em_preparo'] ?></strong><small>Produção em andamento</small></article>
  <article class="admin-kpi-card"><span>Faturamento</span><strong><?= money_br((float) $stats['faturamento']) ?></strong><small>Período filtrado</small></article>
</section>

<form class="admin-command-bar" method="get" action="<?= site_url('admin/pedidos.php') ?>">
  <label class="admin-field"><span>Buscar</span><input name="busca" value="<?= e($filters['busca']) ?>" placeholder="Pedido, cliente, contato ou bairro"></label>
  <label class="admin-field">
    <span>Status</span>
    <select name="status">
      <option value="">Todos</option>
      <?php foreach (order_status_options() as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="admin-field">
    <span>Pagamento</span>
    <select name="status_pagamento">
      <option value="">Todos</option>
      <?php foreach (order_payment_status_options() as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $filters['status_pagamento'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="admin-field">
    <span>Origem</span>
    <select name="origem">
      <option value="">Todas</option>
      <?php foreach (['catalogo' => 'Catálogo', 'pdv' => 'PDV', 'atendimento' => 'Atendimento'] as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $filters['origem'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="admin-field"><span>Início</span><input type="date" name="data_inicio" value="<?= e((string) $filters['data_inicio']) ?>"></label>
  <label class="admin-field"><span>Fim</span><input type="date" name="data_fim" value="<?= e((string) $filters['data_fim']) ?>"></label>
  <button class="btn btn-soft" type="submit">Aplicar</button>
</form>

<section class="priority-orders">
  <article class="admin-alert-card admin-alert-info"><strong>Pagamentos</strong><?= (int) $stats['aguardando_pagamento'] ?> pedido(s) aguardando confirmação manual.</article>
  <article class="admin-alert-card admin-alert-warning"><strong>Entrega</strong><?= (int) $stats['saiu_para_entrega'] ?> pedido(s) saiu para entrega.</article>
  <article class="admin-alert-card admin-alert-success"><strong>Finalizados</strong><?= (int) $stats['finalizados'] ?> pedido(s) finalizado(s) no período.</article>
</section>

<div class="admin-data-table">
  <table>
    <thead>
      <tr>
        <th>Pedido</th>
        <th>Cliente</th>
        <th>Status</th>
        <th>Pagamento</th>
        <th>Recebimento</th>
        <th>Total</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($pedidos)): ?>
        <tr><td colspan="7"><div class="admin-empty-state"><strong>Nenhum pedido encontrado.</strong><p>Ajuste os filtros ou finalize um pedido pelo checkout público.</p></div></td></tr>
      <?php endif; ?>
      <?php foreach ($pedidos as $pedido): ?>
        <?php
          $items = order_items((int) $pedido['id']);
          $history = order_history((int) $pedido['id']);
          $payment = order_payment((int) $pedido['id']);
          $notifications = whatsapp_order_notifications((int) $pedido['id']);
        ?>
        <tr>
          <td>
            <strong><?= e((string) $pedido['codigo']) ?></strong>
            <small><?= e(date('d/m/Y H:i', strtotime((string) $pedido['criado_em']))) ?> · <?= e(order_origin_label((string) $pedido['origem'])) ?></small>
          </td>
          <td>
            <?= e((string) $pedido['cliente_nome']) ?>
            <small><?= e((string) $pedido['cliente_contato']) ?></small>
          </td>
          <td><span class="<?= e(order_badge_class((string) $pedido['status'])) ?>"><?= e(order_status_label((string) $pedido['status'])) ?></span></td>
          <td>
            <span class="<?= e(order_badge_class((string) $pedido['status_pagamento'])) ?>"><?= e(order_payment_status_label((string) $pedido['status_pagamento'])) ?></span>
            <small><?= e(order_payment_method_label((string) $pedido['forma_pagamento'])) ?></small>
          </td>
          <td>
            <?= e(order_receipt_label((string) $pedido['recebimento'])) ?>
            <small><?= e((string) ($pedido['bairro'] ?? '')) ?></small>
          </td>
          <td><?= money_br((float) $pedido['total']) ?></td>
          <td>
            <div class="admin-table-actions order-actions">
              <details class="admin-order-details">
                <summary>Detalhes</summary>
                <div class="admin-order-detail-panel">
                  <h3>Itens</h3>
                  <?php foreach ($items as $item): ?>
                    <p>
                      <strong><?= (int) $item['quantidade'] ?>x <?= e((string) $item['produto_nome']) ?></strong>
                      <?php if (!empty($item['produto_cor_nome'])): ?>
                        <span class="admin-color-chip"><i class="admin-color-dot" style="--color: <?= e((string) ($item['produto_cor_hex'] ?: '#FFFFFF')) ?>"></i><?= e((string) $item['produto_cor_nome']) ?></span>
                      <?php endif; ?>
                      · <?= money_br((float) $item['total_linha']) ?>
                    </p>
                  <?php endforeach; ?>

                  <h3>Recebimento</h3>
                  <p><?= e(order_receipt_label((string) $pedido['recebimento'])) ?> · <?= e((string) $pedido['endereco']) ?> <?= e((string) $pedido['bairro']) ?></p>
                  <?php if (!empty($pedido['mensagem_cartao'])): ?><p><strong>Cartão:</strong> <?= e((string) $pedido['mensagem_cartao']) ?></p><?php endif; ?>
                  <?php if (!empty($pedido['observacoes'])): ?><p><strong>Obs.:</strong> <?= e((string) $pedido['observacoes']) ?></p><?php endif; ?>

                  <h3>Histórico</h3>
                  <?php foreach ($history as $event): ?>
                    <p><?= e(date('d/m/Y H:i', strtotime((string) $event['criado_em']))) ?> · <?= e(order_status_label((string) $event['status_novo'])) ?><?= !empty($event['observacao']) ? ' · ' . e((string) $event['observacao']) : '' ?></p>
                  <?php endforeach; ?>

                  <h3>WhatsApp</h3>
                  <?php if (empty($notifications)): ?>
                    <p>Nenhuma tentativa registrada.</p>
                  <?php else: ?>
                    <?php foreach (array_slice($notifications, 0, 3) as $notification): ?>
                      <p><?= e(date('d/m/Y H:i', strtotime((string) $notification['criado_em']))) ?> · <?= e((string) $notification['status']) ?><?= !empty($notification['erro']) ? ' · ' . e((string) $notification['erro']) : '' ?></p>
                    <?php endforeach; ?>
                  <?php endif; ?>

                  <?php if ($payment): ?>
                    <h3>Pagamento</h3>
                    <p><?= e(order_payment_method_label((string) $payment['forma_pagamento'])) ?> · <?= e(order_payment_status_label((string) $payment['status'])) ?> · <?= money_br((float) $payment['valor']) ?></p>
                  <?php endif; ?>
                </div>
              </details>

              <form method="post" action="<?= site_url('admin/actions/pedido-status.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                <select name="novo_status" aria-label="Novo status">
                  <?php foreach (order_status_options() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $pedido['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit">Atualizar</button>
              </form>

              <form method="post" action="<?= site_url('admin/actions/pedido-pagamento.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                <input type="hidden" name="acao" value="confirmar_pagamento">
                <button type="submit">Confirmar pagamento</button>
              </form>

              <form method="post" action="<?= site_url('admin/actions/pedido-pagamento.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                <input type="hidden" name="acao" value="cancelar_pagamento">
                <button type="submit">Cancelar pagamento</button>
              </form>

              <form method="post" action="<?= site_url('admin/actions/reenviar-whatsapp-pedido.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="pedido_id" value="<?= (int) $pedido['id'] ?>">
                <button type="submit">Reenviar WhatsApp</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
