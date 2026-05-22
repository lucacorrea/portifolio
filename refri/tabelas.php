<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/topbar.php'; ?>

<div class="app-wrapper">
  <main class="main-content">
    <h1 class="section-title">Ordens de Serviço</h1>

    <!-- Barra de filtros -->
    <form id="filtros-form" class="filters-bar" onsubmit="return false;">
      <select name="status" class="filter-select">
        <option value="">Todos os status</option>
        <option value="aberta">Aberta</option>
        <option value="em_andamento">Em andamento</option>
        <option value="aguardando_peca">Aguardando peça</option>
        <option value="finalizada">Finalizada</option>
      </select>
      <input type="date" name="data_de" class="filter-input" placeholder="Data de">
      <input type="date" name="data_ate" class="filter-input" placeholder="Data até">
      <input type="text" name="cliente" class="filter-input" placeholder="Cliente">
      <button type="button" class="btn btn-primary" onclick="aplicarFiltros()">Filtrar</button>
    </form>

    <!-- Tabela -->
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>OS</th>
            <th>Cliente</th>
            <th>Equipamento</th>
            <th>Status</th>
            <th>Data</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody id="tabela-os-body">
          <!-- carregado via AJAX ou PHP -->
          <tr>
            <td>#1023</td>
            <td>João Silva</td>
            <td>Split 9000</td>
            <td><span class="badge badge-em-andamento">Em andamento</span></td>
            <td>20/05/2026</td>
            <td class="actions">
              <button class="btn-icon">✏️</button>
              <button class="btn-icon">👁️</button>
            </td>
          </tr>
          <!-- mais linhas -->
        </tbody>
      </table>
    </div>
  </main>
</div>