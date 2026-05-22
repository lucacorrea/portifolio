<?php
$tipo = $_GET['tipo'] ?? 'os';

$configs = [
  'clientes' => [
    'title' => 'Clientes',
    'subtitle' => 'Gerencie clientes, contatos, endereços e histórico de atendimentos.',
    'button' => 'Novo Cliente',
    'active' => 'clientes',
  ],
  'os' => [
    'title' => 'Ordens de Serviço',
    'subtitle' => 'Acompanhe solicitações, execução técnica, status e finalização.',
    'button' => 'Nova OS',
    'active' => 'os',
  ],
  'orcamentos' => [
    'title' => 'Orçamentos',
    'subtitle' => 'Crie, envie por WhatsApp, aprove e converta orçamentos em OS.',
    'button' => 'Novo Orçamento',
    'active' => 'orcamentos',
  ],
  'pecas' => [
    'title' => 'Peças',
    'subtitle' => 'Controle estoque, valores, fornecedores e alertas de reposição.',
    'button' => 'Nova Peça',
    'active' => 'pecas',
  ],
  'servicos' => [
    'title' => 'Tipos de Serviço',
    'subtitle' => 'Padronize serviços, valores base, tempo médio e categorias.',
    'button' => 'Novo Serviço',
    'active' => 'servicos',
  ],
  'notas' => [
    'title' => 'Notas Fiscais',
    'subtitle' => 'Acompanhe notas emitidas, pendentes, rejeitadas e canceladas.',
    'button' => 'Nova Nota',
    'active' => 'notas',
  ],
];

$current = $configs[$tipo] ?? $configs['os'];
$pageTitle = $current['title'];
$activePage = $current['active'];
$pageCss = ['tables'];
$pageJs = ['tabelas'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>

  <section class="page-header">
    <div>
      <span class="eyebrow">Gestão operacional</span>
      <h1><?= htmlspecialchars($current['title']) ?></h1>
      <p><?= htmlspecialchars($current['subtitle']) ?></p>
    </div>
    <div class="page-header__actions">
      <button class="btn btn--primary" id="openCreateModal">+ <?= htmlspecialchars($current['button']) ?></button>
      <button class="btn btn--secondary" id="openFiltersMobile">Filtros</button>
    </div>
  </section>

  <section class="summary-grid" id="tableSummary">
    <article class="mini-card skeleton"></article>
    <article class="mini-card skeleton"></article>
    <article class="mini-card skeleton"></article>
    <article class="mini-card skeleton"></article>
  </section>

  <section class="panel filter-panel" id="filterPanel">
    <div class="filter-panel__grid">
      <label class="field field--search">
        <span>Buscar</span>
        <input id="searchInput" type="search" placeholder="Nome, número, código ou telefone">
      </label>
      <label class="field">
        <span>Status</span>
        <select id="statusFilter">
          <option value="">Todos</option>
          <option>Aberta</option>
          <option>Agendada</option>
          <option>Em andamento</option>
          <option>Aguardando peça</option>
          <option>Aprovado</option>
          <option>Finalizada</option>
          <option>Cancelada</option>
        </select>
      </label>
      <label class="field">
        <span>Data inicial</span>
        <input id="dateStart" type="date">
      </label>
      <label class="field">
        <span>Data final</span>
        <input id="dateEnd" type="date">
      </label>
      <div class="filter-panel__actions">
        <button class="btn btn--primary" id="applyFilters">Filtrar</button>
        <button class="btn btn--ghost" id="clearFilters">Limpar</button>
      </div>
    </div>
  </section>

  <section class="panel table-panel">
    <div class="panel__header">
      <div>
        <span class="eyebrow">Listagem</span>
        <h2>Registros encontrados</h2>
      </div>
      <div class="table-tools">
        <button class="btn btn--secondary">Exportar</button>
        <button class="icon-action" title="Mais ações">⋯</button>
      </div>
    </div>

    <div class="desktop-table">
      <table>
        <thead id="tableHead"></thead>
        <tbody id="tableBody">
          <tr><td>Carregando registros...</td></tr>
        </tbody>
      </table>
    </div>

    <div class="mobile-cards" id="mobileCards"></div>

    <div class="pagination">
      <span id="paginationInfo">Mostrando 0 registros</span>
      <div>
        <button class="page-btn" disabled>Anterior</button>
        <button class="page-btn is-active">1</button>
        <button class="page-btn">2</button>
        <button class="page-btn">Próximo</button>
      </div>
    </div>
  </section>

  <div class="modal" id="createModal" aria-hidden="true">
    <div class="modal__dialog">
      <button class="modal__close" id="closeCreateModal" aria-label="Fechar">×</button>
      <span class="eyebrow">Novo registro</span>
      <h2><?= htmlspecialchars($current['button']) ?></h2>
      <p>Preencha os dados principais para iniciar o cadastro e manter o controle operacional atualizado.</p>
      <div class="modal-grid">
        <label class="field">
          <span>Nome / Identificação</span>
          <input type="text" placeholder="Ex.: Mercado São José">
        </label>
        <label class="field">
          <span>Status</span>
          <select>
            <option>Aberta</option>
            <option>Em andamento</option>
            <option>Aprovado</option>
          </select>
        </label>
        <label class="field field--full">
          <span>Observações</span>
          <textarea placeholder="Descreva as informações principais..."></textarea>
        </label>
      </div>
      <div class="modal__actions">
        <button class="btn btn--secondary" id="cancelModal">Cancelar</button>
        <button class="btn btn--primary">Salvar</button>
      </div>
    </div>
  </div>
</main>
<script>
  window.KY_TABLE_TYPE = <?= json_encode($tipo) ?>;
</script>
<?php include 'includes/footer.php'; ?>
