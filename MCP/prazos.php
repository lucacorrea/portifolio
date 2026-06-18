<?php
require_once __DIR__ . '/layout.php';
require_login();

render_layout_start(
    'prazos',
    'Prazos',
    'Controle dos processos vencidos, urgentes e previstos por mes.',
    'Use esta tela para acompanhar prazos proximos, vencidos e pagamentos pendentes sem depender apenas do painel principal.'
);
?>

<section class="data-panel" data-page="deadlines">
    <div class="panel-heading">
        <div>
            <h2 id="deadline-title">Prazos urgentes</h2>
            <p id="deadline-count-label">Carregando prazos...</p>
        </div>
        <div class="deadline-tabs" role="tablist" aria-label="Filtros de prazo">
            <button class="tab-button active" type="button" data-deadline-tab="urgent">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Urgentes
            </button>
            <button class="tab-button" type="button" data-deadline-tab="overdue">
                <i class="fa-solid fa-calendar-xmark"></i>
                Vencidos
            </button>
            <button class="tab-button" type="button" data-deadline-tab="month">
                <i class="fa-solid fa-calendar-days"></i>
                Mensal
            </button>
        </div>
    </div>

    <div class="filter-bar compact-wide">
        <label class="search-field">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="deadline-q" placeholder="Buscar por cliente, numero ou tipo">
        </label>
        <select id="deadline-tipo" aria-label="Tipo">
            <option value="">Todos os tipos</option>
        </select>
        <select id="deadline-payment" aria-label="Pagamento">
            <option value="">Todos os pagamentos</option>
            <option value="pending">Pagamento pendente</option>
            <option value="paid">Pago</option>
        </select>
        <input type="month" id="deadline-month" aria-label="Mes do cronograma">
    </div>

    <div class="stats-grid slim">
        <article class="stat-card">
            <span class="stat-icon rose"><i class="fa-solid fa-bell"></i></span>
            <div>
                <small>Urgentes</small>
                <strong id="deadline-stat-urgent">0</strong>
            </div>
        </article>
        <article class="stat-card">
            <span class="stat-icon amber"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <div>
                <small>Vencidos</small>
                <strong id="deadline-stat-overdue">0</strong>
            </div>
        </article>
        <article class="stat-card">
            <span class="stat-icon green"><i class="fa-solid fa-money-check-dollar"></i></span>
            <div>
                <small>Pagos</small>
                <strong id="deadline-stat-paid">0</strong>
            </div>
        </article>
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
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody id="deadline-table-body">
                <tr>
                    <td colspan="7" class="empty-row">Carregando prazos...</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<?php render_layout_end(); ?>
