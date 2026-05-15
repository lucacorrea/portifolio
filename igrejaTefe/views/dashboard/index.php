<?php

$chartData = [
    'months' => ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
    'entradas' => [7000, 10000, 15000, 5000, 65000, 25000],
    'saidas' => [5000, 7000, 9000, 2000, 35000, 15000],
    'categorias' => [],
    'categoriasValores' => [],
];

$metrics = [
    [
        'label' => 'Entradas do mês',
        'value' => 'R$ 5.000,00',
        'helper' => 'Movimentações neste período.',
        'badge' => 'Mês atual',
        'icon' => 'arrow-down-circle',
        'tone' => 'success',
    ],
    [
        'label' => 'Dízimos',
        'value' => 'R$ 5.000,00',
        'helper' => 'Movimentações neste período.',
        'badge' => 'Mês atual',
        'icon' => 'hand-coins',
        'tone' => 'info',
    ],
    [
        'label' => 'Ofertas',
        'value' => 'R$ 5.000,00',
        'helper' => 'Movimentações neste período.',
        'badge' => 'Mês atual',
        'icon' => 'gift',
        'tone' => 'purple',
    ],
    [
        'label' => 'Saídas do mês',
        'value' => 'R$ 5.000,00',
        'helper' => 'Movimentações neste período.',
        'badge' => 'Mês atual',
        'icon' => 'arrow-up-circle',
        'tone' => 'danger',
    ],
    [
        'label' => 'Saldo do mês',
        'value' => 'R$ 10.000,00',
        'helper' => 'Movimentações neste período.',
        'badge' => 'Atualizado',
        'icon' => 'wallet',
        'tone' => 'warning',
    ],
];

$transactions = [];
?>

<section class="dashboard-page">
    <div class="dashboard-hero">
        <div class="dashboard-hero-content">
            <p class="eyebrow">Dashboard financeiro</p>
            <h1>Visão geral da igreja</h1>
            <p>Acompanhe entradas, saídas, saldo e movimentações recentes em tempo real.</p>
        </div>

        <div class="dashboard-hero-actions">
            <a class="button primary" href="<?= \App\Core\View::e(url('/entradas')) ?>">
                <i data-lucide="plus-circle"></i>
                Nova entrada
            </a>
            <a class="button soft-danger" href="<?= \App\Core\View::e(url('/saidas')) ?>">
                <i data-lucide="minus-circle"></i>
                Nova saída
            </a>
            <a class="button secondary" href="<?= \App\Core\View::e(url('/relatorios')) ?>">
                <i data-lucide="file-text"></i>
                Gerar relatório
            </a>
        </div>
    </div>

    <div class="metric-grid">
        <?php foreach ($metrics as $metric): ?>
            <article class="metric-card metric-<?= \App\Core\View::e($metric['tone']) ?>">
                <div class="metric-card-header">
                    <span class="metric-icon">
                        <i data-lucide="<?= \App\Core\View::e($metric['icon']) ?>"></i>
                    </span>
                    <span class="metric-badge"><?= \App\Core\View::e($metric['badge']) ?></span>
                </div>
                <span class="metric-label"><?= \App\Core\View::e($metric['label']) ?></span>
                <strong class="metric-value"><?= \App\Core\View::e($metric['value']) ?></strong>
                <p class="metric-helper"><?= \App\Core\View::e($metric['helper']) ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="dashboard-grid">
        <article class="chart-card chart-card-wide">
            <div class="chart-header">
                <div>
                    <span class="section-kicker">Fluxo mensal</span>
                    <h2>Entradas x Saídas</h2>
                </div>
                <span class="badge badge-muted">Últimos 6 meses</span>
            </div>
            <div class="chart-container" id="cashflow-chart"></div>
            <div class="empty-state chart-empty" data-chart-empty="cashflow">
                <i data-lucide="line-chart"></i>
                <strong>Nenhum dado financeiro</strong>
                <p>Quando entradas ou saídas forem cadastradas, o gráfico será atualizado.</p>
            </div>
        </article>

        <article class="chart-card">
            <div class="chart-header">
                <div>
                    <span class="section-kicker">Categorias</span>
                    <h2>Saídas por categoria</h2>
                </div>
            </div>
            <div class="chart-container donut-container" id="category-chart"></div>
            <div class="empty-state chart-empty" data-chart-empty="category">
                <i data-lucide="pie-chart"></i>
                <strong>Sem despesas por categoria</strong>
                <p>As categorias aparecerão aqui após o registro de saídas.</p>
            </div>
        </article>

        <article class="quick-actions-card">
            <div class="chart-header">
                <div>
                    <span class="section-kicker">Atalhos</span>
                    <h2>Ações rápidas</h2>
                </div>
            </div>
            <div class="quick-action-list">
                <a class="quick-action-item" href="<?= \App\Core\View::e(url('/entradas')) ?>">
                    <span><i data-lucide="arrow-down-circle"></i></span>
                    <strong>Registrar entrada</strong>
                    <small>Dízimos, ofertas e contribuições.</small>
                </a>
                <a class="quick-action-item" href="<?= \App\Core\View::e(url('/saidas')) ?>">
                    <span><i data-lucide="arrow-up-circle"></i></span>
                    <strong>Registrar saída</strong>
                    <small>Despesas e pagamentos da igreja.</small>
                </a>
                <a class="quick-action-item" href="<?= \App\Core\View::e(url('/relatorios')) ?>">
                    <span><i data-lucide="file-text"></i></span>
                    <strong>Ver relatórios</strong>
                    <small>Resumo por período e exportações.</small>
                </a>
                <a class="quick-action-item" href="<?= \App\Core\View::e(url('/categorias')) ?>">
                    <span><i data-lucide="tags"></i></span>
                    <strong>Gerenciar categorias</strong>
                    <small>Organize despesas por finalidade.</small>
                </a>
            </div>
        </article>
    </div>

    <article class="transactions-card">
        <div class="chart-header">
            <div>
                <span class="section-kicker">Histórico</span>
                <h2>Últimas movimentações</h2>
            </div>
            <span class="badge badge-muted">Tempo real</span>
        </div>

        <?php if ($transactions === []): ?>
            <div class="empty-state">
                <i data-lucide="inbox"></i>
                <strong>Nenhuma movimentação registrada</strong>
                <p>Quando entradas ou saídas forem cadastradas, elas aparecerão aqui.</p>
            </div>
        <?php else: ?>
            <div class="transactions-table-wrap">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Descrição</th>
                            <th>Categoria</th>
                            <th>Data</th>
                            <th>Valor</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td data-label="Tipo">
                                    <span class="badge <?= $transaction['tipo'] === 'entrada' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= \App\Core\View::e($transaction['tipo']) ?>
                                    </span>
                                </td>
                                <td data-label="Descrição"><?= \App\Core\View::e($transaction['descricao']) ?></td>
                                <td data-label="Categoria"><?= \App\Core\View::e($transaction['categoria']) ?></td>
                                <td data-label="Data"><?= \App\Core\View::e($transaction['data']) ?></td>
                                <td data-label="Valor" class="<?= $transaction['tipo'] === 'entrada' ? 'amount-positive' : 'amount-negative' ?>">
                                    <?= \App\Core\View::e($transaction['valor']) ?>
                                </td>
                                <td data-label="Ação">
                                    <a class="table-action" href="<?= \App\Core\View::e($transaction['url']) ?>">Ver</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>

<script type="application/json" id="dashboard-chart-data"><?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
