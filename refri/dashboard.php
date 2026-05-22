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
      <span class="eyebrow">Visão executiva</span>
      <h1>Dashboard</h1>
      <p>Visão geral dos serviços, orçamentos, atendimentos e alertas da K.Yamaguchi.</p>
    </div>
    <div class="page-header__actions">
      <a href="tabelas.php?tipo=os&acao=nova" class="btn btn--primary">+ Nova OS</a>
      <a href="tabelas.php?tipo=orcamentos&acao=novo" class="btn btn--secondary">Novo Orçamento</a>
    </div>
  </section>

  <section class="kpi-grid" id="dashboardStats" aria-live="polite">
    <article class="metric-card skeleton"></article>
    <article class="metric-card skeleton"></article>
    <article class="metric-card skeleton"></article>
    <article class="metric-card skeleton"></article>
  </section>

  <section class="dashboard-grid">
    <article class="panel panel--large">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Operação</span>
          <h2>OS por status</h2>
        </div>
        <select class="control control--small" id="statusPeriod">
          <option>Este mês</option>
          <option>Últimos 7 dias</option>
          <option>Últimos 90 dias</option>
        </select>
      </div>
      <div class="chart-shell">
        <canvas id="statusChart" height="260"></canvas>
      </div>
    </article>

    <article class="panel panel--large">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Financeiro</span>
          <h2>Faturamento mensal</h2>
        </div>
        <span class="trend-pill">+12% no mês</span>
      </div>
      <div class="chart-shell">
        <canvas id="revenueChart" height="260"></canvas>
      </div>
    </article>
  </section>

  <section class="content-grid">
    <article class="panel">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Últimos atendimentos</span>
          <h2>OS recentes</h2>
        </div>
        <a href="tabelas.php?tipo=os" class="link-action">Ver todas</a>
      </div>
      <div class="responsive-table">
        <table>
          <thead>
            <tr>
              <th>OS</th>
              <th>Cliente</th>
              <th>Serviço</th>
              <th>Status</th>
              <th>Valor</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody id="recentOrders">
            <tr><td colspan="6">Carregando...</td></tr>
          </tbody>
        </table>
      </div>
    </article>

    <aside class="side-stack">
      <article class="panel">
        <div class="panel__header">
          <div>
            <span class="eyebrow">Agenda</span>
            <h2>Atendimentos de hoje</h2>
          </div>
          <span class="mini-icon">📅</span>
        </div>
        <div class="timeline" id="todayAgenda"></div>
      </article>

      <article class="panel panel--warning">
        <div class="panel__header">
          <div>
            <span class="eyebrow">Atenção</span>
            <h2>Alertas importantes</h2>
          </div>
          <span class="mini-icon">⚠</span>
        </div>
        <div class="alert-list" id="alertsList"></div>
      </article>
    </aside>
  </section>
</main>
<?php include 'includes/footer.php'; ?>
