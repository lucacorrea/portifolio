<?php
require_once __DIR__ . '/layout.php';
require_login();

render_layout_start(
    'dashboard',
    'Painel de Processos',
    'Acompanhamento central dos seus processos juridicos pessoais.',
    'Use os filtros para localizar processos por cliente, numero, tipo, situacao ou prazo. A tabela atualiza com paginacao e mostra pagamentos registrados.'
);
?>

<section class="stats-grid">
    <article class="stat-card">
        <span class="stat-icon blue"><i class="fa-solid fa-folder-open"></i></span>
        <div>
            <small>Total de processos</small>
            <strong id="stat-total">0</strong>
        </div>
    </article>
    <article class="stat-card">
        <span class="stat-icon amber"><i class="fa-solid fa-spinner"></i></span>
        <div>
            <small>Em andamento</small>
            <strong id="stat-andamento">0</strong>
        </div>
    </article>
    <article class="stat-card">
        <span class="stat-icon rose"><i class="fa-solid fa-triangle-exclamation"></i></span>
        <div>
            <small>Prazos proximos</small>
            <strong id="stat-proximos">0</strong>
        </div>
    </article>
    <article class="stat-card">
        <span class="stat-icon green"><i class="fa-solid fa-money-check-dollar"></i></span>
        <div>
            <small>Pagamentos</small>
            <strong id="stat-pagos">0</strong>
        </div>
    </article>
</section>

<section class="data-panel" data-page="dashboard">
    <div class="panel-heading">
        <div>
            <h2>Lista de Processos</h2>
            <p id="process-count-label">Carregando registros...</p>
        </div>
        <a class="btn primary" href="cadastro.php">
            <i class="fa-solid fa-plus"></i>
            Novo processo
        </a>
    </div>

    <div class="filter-bar" id="dashboard-filters">
        <label class="search-field">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="filter-q" placeholder="Buscar por cliente, numero ou observacao">
        </label>
        <select id="filter-tipo" aria-label="Filtrar por tipo">
            <option value="">Todos os tipos</option>
        </select>
        <select id="filter-situacao" aria-label="Filtrar por situacao">
            <option value="">Todas as situacoes</option>
        </select>
        <input type="date" id="filter-inicio" aria-label="Data inicial">
        <input type="date" id="filter-fim" aria-label="Data final">
        <select id="filter-sort" aria-label="Ordenacao">
            <option value="recentes">Mais recentes</option>
            <option value="antigos">Mais antigos</option>
            <option value="cliente">Cliente A-Z</option>
            <option value="numero">Numero</option>
            <option value="situacao">Situacao</option>
        </select>
        <button class="btn ghost" type="button" id="clear-filters">
            <i class="fa-solid fa-filter-circle-xmark"></i>
            Limpar
        </button>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Numero</th>
                    <th>Tipo</th>
                    <th>Situacao</th>
                    <th>Prazo</th>
                    <th>Pagamento</th>
                    <th>Atualizacao</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody id="process-table-body">
                <tr>
                    <td colspan="8" class="empty-row">Carregando processos...</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="pagination" id="process-pagination"></div>
</section>

<?php render_layout_end(); ?>
