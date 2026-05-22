<?php
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$pageCss = ['dashboard'];
$pageJs = ['dashboard'];
$topbarSearchPlaceholder = 'Buscar cliente, OS, orçamento, peça ou técnico...';
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>

  <section class="page-header">
    <div>
      <span class="eyebrow">Central de controle</span>
      <h1>Dashboard operacional</h1>
      <p>Visão objetiva de OS, técnicos, peças críticas, orçamentos pendentes e faturamento da K.Yamaguchi Service.</p>
    </div>
    <div class="page-header__actions">
      <a href="ordens-servico.php?action=new" class="btn btn--primary">+ Nova OS</a>
      <a href="orcamentos.php?action=new" class="btn btn--secondary">Novo orçamento</a>
    </div>
  </section>

  <section class="dashboard-command">
    <article class="command-panel">
      <span class="eyebrow">Operação do dia</span>
      <h2>Controle técnico com foco em SLA, execução e faturamento.</h2>
      <p>Resumo executivo para acompanhar gargalos sem depender de telas carregadas ou efeitos visuais exagerados.</p>
      <div class="command-metrics">
        <div class="command-metric"><span>SLA no prazo</span><strong>96%</strong></div>
        <div class="command-metric"><span>Tempo médio</span><strong>2h15</strong></div>
        <div class="command-metric"><span>Conversão</span><strong>68%</strong></div>
      </div>
    </article>

    <article class="command-panel">
      <span class="eyebrow">Saúde da operação</span>
      <h2>Eficiência técnica</h2>
      <p>Indicadores compactos para detectar risco de atraso, peça crítica ou pendência fiscal.</p>
      <div class="health-list">
        <div class="health-row"><span>OS no prazo</span><div class="bar-track"><div class="bar-fill bar-fill--86"></div></div><strong>86%</strong></div>
        <div class="health-row"><span>Peças OK</span><div class="bar-track"><div class="bar-fill bar-fill--71"></div></div><strong>71%</strong></div>
        <div class="health-row"><span>NF emitida</span><div class="bar-track"><div class="bar-fill bar-fill--64"></div></div><strong>64%</strong></div>
      </div>
    </article>
  </section>

  <section class="grid-4" id="dashboardStats" aria-live="polite"></section>

  <section class="dashboard-grid">
    <article class="panel chart-panel">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Status das OS</span>
          <h2>Distribuição operacional</h2>
          <p class="panel-sub">Gargalos por etapa de atendimento.</p>
        </div>
        <a href="ordens-servico.php" class="btn btn--secondary btn--sm">Ver OS</a>
      </div>
      <div class="chart-shell">
        <canvas id="osStatusChart" height="245"></canvas>
      </div>
    </article>

    <article class="panel chart-panel">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Financeiro</span>
          <h2>Faturamento mensal</h2>
          <p class="panel-sub">Receita prevista e realizada por mês.</p>
        </div>
        <a href="relatorios.php" class="btn btn--secondary btn--sm">Relatório</a>
      </div>
      <div class="chart-shell">
        <canvas id="revenueChart" height="245"></canvas>
      </div>
    </article>
  </section>

  <section class="dashboard-work">
    <article class="panel">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Operação recente</span>
          <h2>Últimas ordens de serviço</h2>
          <p class="panel-sub">Use a busca superior para filtrar a tabela sem recarregar.</p>
        </div>
        <a href="ordens-servico.php" class="btn btn--secondary btn--sm">Abrir lista</a>
      </div>
      <div class="responsive-table">
        <table>
          <thead>
            <tr>
              <th>OS</th>
              <th>Cliente</th>
              <th>Serviço</th>
              <th>Status</th>
              <th>Técnico</th>
              <th>Valor</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody id="recentOrders">
            <tr><td colspan="7">Carregando ordens...</td></tr>
          </tbody>
        </table>
      </div>
    </article>

    <aside class="dash-list">
      <article class="panel">
        <div class="panel__header">
          <div>
            <span class="eyebrow">Agenda</span>
            <h2>Atendimentos do dia</h2>
          </div>
        </div>
        <div class="dash-list" id="todaySchedule"></div>
      </article>

      <article class="panel">
        <div class="panel__header">
          <div>
            <span class="eyebrow">Atenção</span>
            <h2>Alertas importantes</h2>
          </div>
        </div>
        <div class="alert-list" id="alertsList"></div>
      </article>
    </aside>
  </section>
</main>
<?php include 'includes/footer.php'; ?>
