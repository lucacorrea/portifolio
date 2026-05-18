<?php
$adminTitle = 'Estoque';
$activeAdmin = 'estoque';
require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Estoque</span>
    <h1>Controle de estoque</h1>
    <p>Entradas, saídas, ajustes, perdas, alertas de estoque baixo e movimentações demonstrativas.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="btn btn-soft" href="<?= site_url('admin/produtos.php') ?>">Ver produtos</a>
    <button class="btn btn-primary" type="button">Registrar movimentação</button>
  </div>
</section>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Entradas</span><strong>28</strong><small>Reposições no mês</small></article>
  <article class="admin-kpi-card"><span>Saídas</span><strong>17</strong><small>Vendas e baixas</small></article>
  <article class="admin-kpi-card"><span>Estoque baixo</span><strong>3</strong><small>Produtos críticos</small></article>
  <article class="admin-kpi-card"><span>Perdas</span><strong>2</strong><small>Flores vencidas/danificadas</small></article>
</section>

<section class="admin-grid-2">
  <form class="admin-form-card">
    <div class="admin-panel-header"><div><span class="badge">Movimentação</span><h2>Entrada, saída ou ajuste</h2></div></div>
    <div class="admin-form-grid">
      <label class="admin-field"><span>Produto</span><input placeholder="Buquê, vaso, arranjo..."></label>
      <label class="admin-field"><span>Tipo</span><select><option>Entrada</option><option>Saída</option><option>Ajuste</option><option>Perda</option></select></label>
      <label class="admin-field"><span>Quantidade</span><input type="number" placeholder="1"></label>
      <label class="admin-field"><span>Responsável</span><input placeholder="Operador"></label>
      <label class="admin-field"><span>Origem</span><select><option>Compra</option><option>Venda</option><option>Correção interna</option><option>Montagem de kit</option></select></label>
      <label class="admin-field"><span>Data</span><input type="date"></label>
      <label class="admin-field full"><span>Motivo</span><textarea placeholder="Descreva o motivo da movimentação"></textarea></label>
    </div>
    <div class="admin-action-row"><button class="btn btn-primary" type="button">Salvar demonstração</button><button class="btn btn-soft" type="button">Limpar</button></div>
  </form>

  <article class="admin-panel-card">
    <div class="admin-panel-header"><div><span class="badge badge-rose">Críticos</span><h2>Produtos em atenção</h2></div></div>
    <div class="admin-metric-list">
      <div class="admin-metric-row"><span>Cesta Afeto com Flores</span><strong class="admin-badge-danger">0 un.</strong></div>
      <div class="admin-metric-row"><span>Kit Presente Romântico</span><strong class="admin-badge-warn">2 un.</strong></div>
      <div class="admin-metric-row"><span>Arranjo Floral Premium</span><strong class="admin-badge-warn">3 un.</strong></div>
      <div class="admin-metric-row"><span>Vaso de Violeta</span><strong class="admin-badge-ok">12 un.</strong></div>
    </div>
  </article>
</section>

<section class="admin-panel-card">
  <div class="admin-panel-header">
    <div><span class="badge">Histórico</span><h2>Movimentações recentes</h2></div>
    <button class="btn btn-soft" type="button">Exportar visual</button>
  </div>
  <div class="admin-data-table">
    <table>
      <thead><tr><th>Data</th><th>Produto</th><th>Tipo</th><th>Qtd.</th><th>Responsável</th><th>Motivo</th><th>Status</th></tr></thead>
      <tbody>
        <tr><td>Hoje</td><td><strong>Buquê de Rosas</strong><small>AF-BUQ-001</small></td><td>Entrada</td><td>10</td><td>Admin</td><td>Reposição</td><td><span class="admin-badge-ok">Concluído</span></td></tr>
        <tr><td>Hoje</td><td><strong>Kit Romântico</strong><small>AF-PRE-010</small></td><td>Saída</td><td>2</td><td>Caixa</td><td>Venda PDV</td><td><span class="admin-badge-ok">Concluído</span></td></tr>
        <tr><td>Ontem</td><td><strong>Cesta Afeto</strong><small>AF-PRE-005</small></td><td>Ajuste</td><td>1</td><td>Admin</td><td>Reserva sob encomenda</td><td><span class="admin-badge-info">Reservado</span></td></tr>
        <tr><td>Ontem</td><td><strong>Flores avulsas</strong><small>INSUMO</small></td><td>Perda</td><td>2</td><td>Admin</td><td>Produto danificado</td><td><span class="admin-badge-danger">Baixado</span></td></tr>
      </tbody>
    </table>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
