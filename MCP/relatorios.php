<?php
require_once __DIR__ . '/layout.php';
require_login();

render_layout_start(
    'relatorios',
    'Relatorios',
    'Analise e exportacao dos processos cadastrados.',
    'Monte o recorte desejado pelos filtros e gere o relatorio. Voce pode imprimir ou exportar a listagem em CSV.'
);
?>

<section class="data-panel" data-page="reports">
    <div class="panel-heading">
        <div>
            <h2>Gerador de Relatorios</h2>
            <p id="report-count-label">Defina os filtros e gere o relatorio.</p>
        </div>
        <div class="button-row">
            <button class="btn ghost" type="button" id="report-print">
                <i class="fa-solid fa-print"></i>
                Imprimir
            </button>
            <button class="btn primary" type="button" id="report-csv">
                <i class="fa-solid fa-file-csv"></i>
                CSV
            </button>
        </div>
    </div>

    <div class="print-report-header" aria-hidden="true">
        <div>
            <p>Relatorio gerencial</p>
            <h2>Relatorio de Processos</h2>
        </div>
        <dl class="print-report-meta">
            <div>
                <dt>Gerado em</dt>
                <dd id="report-print-generated">-</dd>
            </div>
            <div>
                <dt>Filtros aplicados</dt>
                <dd id="report-print-filters">Todos os processos</dd>
            </div>
        </dl>
    </div>

    <div class="report-filters" id="report-filters">
        <div class="filter-section filter-section-search">
            <span class="filter-title">Buscar processo</span>
            <label class="search-field">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="report-q" placeholder="Cliente, numero ou observacao">
            </label>
        </div>

        <div class="filter-section">
            <span class="filter-title">Filtrar por</span>
            <div class="filter-pair">
                <select id="report-tipo" aria-label="Tipo">
                    <option value="">Todos os tipos</option>
                </select>
                <select id="report-situacao" aria-label="Situacao">
                    <option value="">Todas as situacoes</option>
                </select>
            </div>
        </div>

        <div class="filter-section">
            <span class="filter-title">Periodo de faturamento</span>
            <div class="filter-dates">
                <label class="mini-field">
                    <span>Mes</span>
                    <input type="month" id="report-mes" aria-label="Mes de faturamento" title="Mes de faturamento">
                </label>
                <label class="mini-field">
                    <span>De</span>
                    <input type="date" id="report-inicio" aria-label="Pagamento inicial" title="Pagamento inicial">
                </label>
                <label class="mini-field">
                    <span>Ate</span>
                    <input type="date" id="report-fim" aria-label="Pagamento final" title="Pagamento final">
                </label>
            </div>
        </div>

        <div class="filter-actions">
            <button class="btn primary" type="button" id="report-generate">
                <i class="fa-solid fa-chart-simple"></i>
                Gerar
            </button>
            <button class="btn ghost" type="button" id="report-clear">
                <i class="fa-solid fa-rotate-left"></i>
                Limpar
            </button>
        </div>
    </div>

    <div class="report-summary">
        <article>
            <small>Total filtrado</small>
            <strong id="report-total">0</strong>
        </article>
        <article class="finance-card">
            <small>Faturamento filtrado</small>
            <strong id="report-revenue">R$ 0,00</strong>
        </article>
        <article>
            <small>Processos pagos</small>
            <strong id="report-paid">0</strong>
        </article>
        <article>
            <small>Ticket medio</small>
            <strong id="report-average">R$ 0,00</strong>
        </article>
        <article>
            <small>Tipos encontrados</small>
            <strong id="report-types">0</strong>
        </article>
        <article>
            <small>Situacoes encontradas</small>
            <strong id="report-statuses">0</strong>
        </article>
    </div>

    <div class="report-grid">
        <section>
            <h3>Por situacao</h3>
            <div id="report-by-status" class="bar-list"></div>
        </section>
        <section>
            <h3>Por tipo</h3>
            <div id="report-by-type" class="bar-list"></div>
        </section>
    </div>

    <div class="table-wrap printable-area">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Numero</th>
                    <th>Tipo</th>
                    <th>Situacao</th>
                    <th>Prazo</th>
                    <th>Pagamento</th>
                    <th>Cadastro</th>
                    <th>Observacao</th>
                </tr>
            </thead>
            <tbody id="report-table-body">
                <tr>
                    <td colspan="8" class="empty-row">Nenhum relatorio gerado ainda.</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<?php render_layout_end(); ?>
