<?php
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$pageCss = ['dashboard'];
$pageJs = ['dashboard'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>
  <section class="page-header">
    <div>
      <span class="eyebrow">Visão operacional</span>
      <h1>Dashboard</h1>
      <p>Acompanhe ordens de serviço, orçamentos, faturamento, estoque e alertas técnicos da K.Yamaguchi.</p>
    </div>
    <div class="page-header__actions">
      <a class="btn btn--primary" href="ordens-servico.php?action=new">+ Nova OS</a>
      <a class="btn btn--secondary" href="orcamentos.php?action=new">Novo Orçamento</a>
    </div>
  </section>

  <section class="grid-4" id="dashboardStats">
    <article class="stat-card"><div class="stat-card__icon stat-card__icon--blue">OS</div><div><span>OS abertas</span><strong>--</strong><small>Carregando dados...</small></div></article>
    <article class="stat-card"><div class="stat-card__icon stat-card__icon--amber">EX</div><div><span>Em execução</span><strong>--</strong><small>Carregando dados...</small></div></article>
    <article class="stat-card"><div class="stat-card__icon stat-card__icon--teal">OR</div><div><span>Orçamentos pendentes</span><strong>--</strong><small>Carregando dados...</small></div></article>
    <article class="stat-card"><div class="stat-card__icon stat-card__icon--green">R$</div><div><span>Faturamento do mês</span><strong>--</strong><small>Carregando dados...</small></div></article>
  </section>

  <section class="dashboard-grid">
    <article class="panel">
      <div class="panel__header"><div><span class="eyebrow">Status das OS</span><h2>Ordens de serviço por status</h2></div><a class="btn btn--secondary btn--sm" href="ordens-servico.php">Ver OS</a></div>
      <div class="chart-shell"><canvas id="osStatusChart"></canvas></div>
    </article>
    <article class="panel">
      <div class="panel__header"><div><span class="eyebrow">Financeiro</span><h2>Faturamento mensal</h2></div></div>
      <div class="chart-shell"><canvas id="revenueChart"></canvas></div>
    </article>
  </section>

  <section class="dashboard-grid">
    <article class="panel">
      <div class="panel__header"><div><span class="eyebrow">Operação recente</span><h2>Últimas ordens de serviço</h2></div><a class="btn btn--secondary btn--sm" href="ordens-servico.php">Abrir lista</a></div>
      <div class="responsive-table"><table><thead><tr><th>OS</th><th>Cliente</th><th>Serviço</th><th>Status</th><th>Técnico</th><th>Valor</th><th>Ações</th></tr></thead><tbody id="recentOrders"><tr><td colspan="7">Carregando...</td></tr></tbody></table></div>
    </article>
    <aside class="panel">
      <div class="panel__header"><div><span class="eyebrow">Agenda</span><h2>Atendimentos do dia</h2></div></div>
      <div class="dash-list" id="todaySchedule"></div>
      <div class="panel__header" style="margin-top:18px"><div><span class="eyebrow">Atenção</span><h2>Alertas importantes</h2></div></div>
      <div class="alert-list" id="alertsList"></div>
    </aside>
  </section>
</main>
<?php include 'includes/footer.php'; ?>
