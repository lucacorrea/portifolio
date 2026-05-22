<?php
$tipo = $_GET['tipo'] ?? 'os';

$configs = [
  'clientes' => [
    'title' => 'Clientes',
    'subtitle' => 'Gerencie clientes, contatos, endereços e histórico de atendimentos.',
    'button' => 'Novo Cliente',
    'active' => 'clientes',
    'eyebrow' => 'Base de atendimento',
  ],
  'os' => [
    'title' => 'Ordens de Serviço',
    'subtitle' => 'Acompanhe solicitações, técnicos, equipamentos, status e finalização.',
    'button' => 'Nova OS',
    'active' => 'ordens',
    'eyebrow' => 'Execução técnica',
  ],
  'orcamentos' => [
    'title' => 'Orçamentos',
    'subtitle' => 'Crie, envie por WhatsApp, aprove e converta orçamentos em OS.',
    'button' => 'Novo Orçamento',
    'active' => 'orcamentos',
    'eyebrow' => 'Comercial técnico',
  ],
  'pecas' => [
    'title' => 'Peças',
    'subtitle' => 'Controle estoque, valores, fornecedores e alertas de reposição.',
    'button' => 'Nova Peça',
    'active' => 'pecas',
    'eyebrow' => 'Estoque técnico',
  ],
  'servicos' => [
    'title' => 'Tipos de Serviço',
    'subtitle' => 'Padronize serviços, valores base, tempo médio e categorias.',
    'button' => 'Novo Serviço',
    'active' => 'servicos',
    'eyebrow' => 'Catálogo técnico',
  ],
  'notas' => [
    'title' => 'Notas Fiscais',
    'subtitle' => 'Acompanhe notas emitidas, pendentes, rejeitadas e canceladas.',
    'button' => 'Nova Nota',
    'active' => 'notas',
    'eyebrow' => 'Controle fiscal',
  ],
];

$current = $configs[$tipo] ?? $configs['os'];
$pageTitle = $current['title'];
$activePage = $current['active'];
$pageCss = ['tables'];
$pageJs = ['tabelas'];
$topbarSearchPlaceholder = 'Buscar nome, número, telefone, serviço ou status...';
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main" data-table-type="<?= htmlspecialchars($tipo) ?>">
  <?php include 'includes/topbar.php'; ?>

  <section class="page-header">
    <div>
      <span class="eyebrow"><?= htmlspecialchars($current['eyebrow']) ?></span>
      <h1><?= htmlspecialchars($current['title']) ?></h1>
      <p><?= htmlspecialchars($current['subtitle']) ?></p>
    </div>
    <div class="page-header__actions">
      <button class="btn btn--primary" id="openCreateModal">+ <?= htmlspecialchars($current['button']) ?></button>
      <button class="btn btn--secondary" id="openFiltersMobile">Filtros</button>
    </div>
  </section>

  <section class="grid-4" id="tableSummary" aria-live="polite"></section>

  <section class="filter-panel filter-panel--mobile-toggle" id="filterPanel">
    <div class="filter-panel__grid">
      <label class="field">
        <span>Buscar</span>
        <input id="tableSearch" type="search" placeholder="Nome, número, código ou telefone">
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
      <button class="btn btn--primary" id="applyFilters">Filtrar</button>
      <button class="btn btn--secondary" id="clearFilters">Limpar</button>
    </div>
  </section>

  <section class="panel">
    <div class="panel__header">
      <div>
        <span class="eyebrow">Listagem</span>
        <h2>Registros encontrados</h2>
      </div>
      <div class="table-page-tools">
        <button class="btn btn--secondary btn--sm">Exportar</button>
        <button class="btn btn--secondary btn--sm">Mais ações</button>
      </div>
    </div>

    <div class="responsive-table desktop-table">
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
      <div class="action-strip">
        <button class="btn btn--secondary btn--sm" disabled>Anterior</button>
        <button class="btn btn--secondary btn--sm">Próxima</button>
      </div>
    </div>
  </section>

  <div class="modal" id="createModal" aria-hidden="true">
    <div class="modal__dialog">
      <div class="panel__header">
        <div>
          <span class="eyebrow">Novo registro</span>
          <h2><?= htmlspecialchars($current['button']) ?></h2>
          <p class="panel-sub">Preencha os dados principais para iniciar o cadastro operacional.</p>
        </div>
        <button class="btn btn--secondary btn--sm" id="closeCreateModal" type="button">Fechar</button>
      </div>
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
        <button class="btn btn--secondary" id="cancelModal" type="button">Cancelar</button>
        <button class="btn btn--primary" type="button">Salvar</button>
      </div>
    </div>
  </div>
</main>
<?php include 'includes/footer.php'; ?>
