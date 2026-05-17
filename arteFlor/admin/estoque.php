<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'estoque';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Estoque | Arte&Flor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/layout.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/pages.css">
  <link rel="stylesheet" href="../assets/css/admin-premium.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body class="admin-premium-body">
<div class="admin-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="admin-main">
    <section class="admin-page-hero">
      <div class="admin-page-title">
        <span class="badge">Operação</span>
        <h1>Estoque</h1>
        <p>Controle visual de entradas, saídas, ajustes, perdas e alerta de reposição dos produtos da loja.</p>
      </div>
      <div class="admin-hero-actions">
        <a class="btn btn-soft" href="produtos.php">Ver produtos</a>
        <button class="btn btn-primary" type="button">Registrar movimentação</button>
      </div>
    </section>

    <section class="admin-kpi-grid">
      <article class="admin-kpi-card"><span>Entradas no mês</span><strong>28</strong><small>Novos itens e reposições</small></article>
      <article class="admin-kpi-card"><span>Saídas no mês</span><strong>17</strong><small>Vendas e baixas</small></article>
      <article class="admin-kpi-card"><span>Estoque baixo</span><strong>3</strong><small>Precisa atenção</small></article>
      <article class="admin-kpi-card"><span>Perdas registradas</span><strong>2</strong><small>Flores vencidas/danificadas</small></article>
    </section>

    <section class="admin-grid-2">
      <form class="admin-form-card">
        <div class="admin-panel-header">
          <div>
            <span class="badge">Movimentação</span>
            <h2>Entrada e saída</h2>
            <p>Formulário visual para simular controle operacional do estoque.</p>
          </div>
        </div>
        <div class="admin-form-grid">
          <label class="admin-field"><span>Produto</span><input placeholder="Buquê, vaso, arranjo..."></label>
          <label class="admin-field"><span>Tipo</span><select><option>Entrada</option><option>Saída</option><option>Ajuste</option><option>Perda</option></select></label>
          <label class="admin-field"><span>Quantidade</span><input type="number" placeholder="1"></label>
          <label class="admin-field"><span>Responsável</span><input placeholder="Operador"></label>
          <label class="admin-field"><span>Origem</span><select><option>Compra</option><option>Venda</option><option>Correção interna</option><option>Montagem de kit</option></select></label>
          <label class="admin-field"><span>Data</span><input type="date"></label>
          <label class="admin-field full"><span>Motivo / observação</span><textarea placeholder="Descreva o motivo da movimentação"></textarea></label>
        </div>
        <div class="admin-action-row" style="margin-top:18px;justify-content:flex-start">
          <button class="btn btn-primary" type="button">Salvar demonstração</button>
          <button class="btn btn-soft" type="button">Limpar</button>
        </div>
      </form>

      <article class="admin-panel-card">
        <div class="admin-panel-header">
          <div>
            <span class="badge">Reposição</span>
            <h2>Itens em atenção</h2>
            <p>Produtos com estoque baixo ou sob encomenda.</p>
          </div>
        </div>
        <div class="admin-metric-list">
          <div class="admin-metric-row"><span>Vaso de Violeta</span><strong class="admin-badge-warn">2 unidades</strong></div>
          <div class="admin-metric-row"><span>Cesta com Flores</span><strong class="admin-badge-danger">0 unidades</strong></div>
          <div class="admin-metric-row"><span>Orquídea</span><strong class="admin-badge-warn">3 unidades</strong></div>
          <div class="admin-metric-row"><span>Cartão personalizado</span><strong class="admin-badge-ok">50 unidades</strong></div>
        </div>
        <div class="admin-alert-card" style="margin-top:18px"><strong>Sugestão</strong>Adicionar relatório automático de estoque baixo na versão com backend.</div>
      </article>
    </section>

    <section class="admin-panel-card" style="margin-top:24px">
      <div class="admin-panel-header">
        <div>
          <span class="badge">Histórico</span>
          <h2>Movimentações recentes</h2>
          <p>Lista demonstrativa das últimas entradas, saídas e ajustes.</p>
        </div>
        <button class="btn btn-soft" type="button">Exportar</button>
      </div>
      <div class="admin-data-table">
        <table>
          <thead><tr><th>Data</th><th>Produto</th><th>Tipo</th><th>Qtd.</th><th>Responsável</th><th>Motivo</th><th>Status</th></tr></thead>
          <tbody>
            <tr><td>Hoje</td><td><div class="admin-item-title"><strong>Buquê de Rosas</strong><small>AF-1001</small></div></td><td>Entrada</td><td>10</td><td>Admin</td><td>Reposição</td><td><span class="admin-badge-ok">Concluído</span></td></tr>
            <tr><td>Hoje</td><td><div class="admin-item-title"><strong>Vaso de Violeta</strong><small>AF-1004</small></div></td><td>Saída</td><td>2</td><td>Admin</td><td>Venda balcão</td><td><span class="admin-badge-ok">Concluído</span></td></tr>
            <tr><td>Ontem</td><td><div class="admin-item-title"><strong>Cesta com Flores</strong><small>AF-1005</small></div></td><td>Ajuste</td><td>1</td><td>Admin</td><td>Reserva para encomenda</td><td><span class="admin-badge-info">Reservado</span></td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>
</body>
</html>
