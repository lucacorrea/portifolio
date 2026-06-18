<?php
require_once __DIR__ . '/layout.php';
require_suporte();

render_layout_start(
    'configuracoes',
    'Configuracoes',
    'Ajustes operacionais, listas inteligentes e auditoria.',
    'Cadastre tipos e situacoes para padronizar os filtros. A auditoria mostra as principais movimentacoes feitas no sistema.'
);
?>

<section class="settings-layout" data-page="settings">
    <div class="data-panel">
        <div class="panel-heading">
            <div>
                <h2>Tipos de Processo</h2>
                <p>Itens usados no cadastro e nos filtros.</p>
            </div>
        </div>
        <form class="inline-form" id="type-form">
            <input type="hidden" id="type-id">
            <input type="text" id="type-name" placeholder="Novo tipo" required>
            <input type="color" id="type-color" value="#2563eb" title="Cor">
            <input type="number" id="type-order" value="0" min="0" title="Ordem">
            <button class="btn primary" type="submit"><i class="fa-solid fa-plus"></i> Salvar</button>
        </form>
        <div id="type-list" class="chip-list"></div>
    </div>

    <div class="data-panel">
        <div class="panel-heading">
            <div>
                <h2>Situacoes</h2>
                <p>Etapas usadas no acompanhamento dos processos.</p>
            </div>
        </div>
        <form class="inline-form" id="status-form">
            <input type="hidden" id="status-id">
            <input type="text" id="status-name" placeholder="Nova situacao" required>
            <input type="color" id="status-color" value="#64748b" title="Cor">
            <label class="check-field">
                <input type="checkbox" id="status-final">
                <span>Finalizadora</span>
            </label>
            <input type="number" id="status-order" value="0" min="0" title="Ordem">
            <button class="btn primary" type="submit"><i class="fa-solid fa-plus"></i> Salvar</button>
        </form>
        <div id="status-list" class="chip-list"></div>
    </div>

    <div class="data-panel wide">
        <div class="panel-heading">
            <div>
                <h2>Auditoria</h2>
                <p id="audit-count-label">Carregando historico...</p>
            </div>
        </div>
        <div class="filter-bar compact">
            <label class="search-field">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="audit-q" placeholder="Buscar por usuario, acao ou tabela">
            </label>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Usuario</th>
                        <th>Acao</th>
                        <th>Tabela</th>
                        <th>Registro</th>
                    </tr>
                </thead>
                <tbody id="audit-table-body">
                    <tr>
                        <td colspan="5" class="empty-row">Carregando auditoria...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="pagination" id="audit-pagination"></div>
    </div>
</section>

<?php render_layout_end(); ?>
