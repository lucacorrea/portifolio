<?php
$adminTitle = 'Pedidos';
$activeAdmin = 'pedidos';
require_once __DIR__ . '/../includes/admin-head.php';
$pedidos = [
    ['codigo' => '#AF-1030', 'cliente' => 'Marina', 'status' => 'Aguardando pagamento', 'origem' => 'Catálogo', 'pagamento' => 'Pix', 'recebimento' => 'Entrega', 'total' => 129.9],
    ['codigo' => '#AF-1029', 'cliente' => 'Rafael', 'status' => 'Em preparo', 'origem' => 'PDV', 'pagamento' => 'Cartão presencial', 'recebimento' => 'Retirada', 'total' => 179.9],
    ['codigo' => '#AF-1028', 'cliente' => 'Beatriz', 'status' => 'Finalizado', 'origem' => 'Atendimento', 'pagamento' => 'Dinheiro', 'recebimento' => 'Entrega', 'total' => 79.9],
    ['codigo' => '#AF-1027', 'cliente' => 'Ana Clara', 'status' => 'Saiu para entrega', 'origem' => 'Catálogo', 'pagamento' => 'Pix', 'recebimento' => 'Entrega', 'total' => 189.9],
];
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Operação</span>
    <h1>Pedidos</h1>
    <p>Fila visual com origem, pagamento, entrega/retirada, status e ações rápidas.</p>
  </div>
  <div class="admin-hero-actions"><a class="btn btn-primary" href="<?= site_url('admin/caixa.php') ?>">Abrir PDV</a></div>
</section>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Pedidos hoje</span><strong>12</strong><small>Catálogo e PDV</small></article>
  <article class="admin-kpi-card"><span>Pendentes</span><strong>7</strong><small>Aguardando ação</small></article>
  <article class="admin-kpi-card"><span>Pix pendente</span><strong>3</strong><small>Confirmar manualmente</small></article>
  <article class="admin-kpi-card"><span>Finalizados</span><strong>18</strong><small>No período visual</small></article>
</section>

<section class="admin-command-bar">
  <label class="admin-field"><span>Buscar</span><input placeholder="Pedido, cliente ou bairro"></label>
  <label class="admin-field"><span>Status</span><select><option>Todos</option><option>Pedido recebido</option><option>Aguardando pagamento</option><option>Em preparo</option><option>Finalizado</option></select></label>
  <label class="admin-field"><span>Origem</span><select><option>Todas</option><option>Catálogo</option><option>PDV</option><option>Atendimento</option></select></label>
  <label class="admin-field"><span>Pagamento</span><select><option>Todos</option><option>Pix</option><option>Dinheiro</option><option>Cartão presencial</option></select></label>
  <button class="btn btn-soft" type="button">Aplicar</button>
</section>

<section class="priority-orders">
  <article class="admin-alert-card"><strong>Prioridade</strong>#AF-1030 aguarda confirmação Pix antes do preparo.</article>
  <article class="admin-alert-card"><strong>Entrega</strong>#AF-1027 saiu para entrega e precisa acompanhamento.</article>
  <article class="admin-alert-card"><strong>Retirada</strong>#AF-1029 estará pronto às 16h.</article>
</section>

<div class="admin-data-table">
  <table>
    <thead><tr><th>Pedido</th><th>Cliente</th><th>Status</th><th>Origem</th><th>Pagamento</th><th>Recebimento</th><th>Total</th><th>Ações</th></tr></thead>
    <tbody>
      <?php foreach ($pedidos as $pedido): ?>
        <?php
          $badge = $pedido['status'] === 'Finalizado' ? 'admin-badge-ok' : ($pedido['status'] === 'Aguardando pagamento' ? 'admin-badge-info' : 'admin-badge-warn');
        ?>
        <tr>
          <td><strong><?= e($pedido['codigo']) ?></strong><small>Pedido demonstrativo</small></td>
          <td><?= e($pedido['cliente']) ?></td>
          <td><span class="<?= $badge ?>"><?= e($pedido['status']) ?></span></td>
          <td><?= e($pedido['origem']) ?></td>
          <td><?= e($pedido['pagamento']) ?></td>
          <td><?= e($pedido['recebimento']) ?></td>
          <td><?= money_br($pedido['total']) ?></td>
          <td><div class="admin-table-actions"><button type="button">Detalhes</button><button type="button">Mudar status</button><button type="button">Confirmar pagamento</button><button type="button">Finalizar</button></div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
