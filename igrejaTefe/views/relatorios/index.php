<?php

$report = is_array($report ?? null) ? $report : [];
$filters = is_array($report['filters'] ?? null) ? $report['filters'] : [];
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$categorias = is_array($report['categorias'] ?? null) ? $report['categorias'] : [];
$formasPagamento = is_array($report['formasPagamento'] ?? null) ? $report['formasPagamento'] : [];
$dailyAll = is_array($report['daily'] ?? null) ? $report['daily'] : [];
$daily = is_array($report['dailyPaginado'] ?? null) ? $report['dailyPaginado'] : [];
$dailyPagination = is_array($report['dailyPagination'] ?? null) ? $report['dailyPagination'] : [
    'current_page' => 1,
    'per_page' => 4,
    'total' => count($daily),
    'total_pages' => 1,
    'from' => count($daily) > 0 ? 1 : 0,
    'to' => count($daily),
];
$movimentos = is_array($report['movimentosPaginados'] ?? null) ? $report['movimentosPaginados'] : [];
$pagination = is_array($report['pagination'] ?? null) ? $report['pagination'] : [
    'current_page' => 1,
    'per_page' => 10,
    'total' => count($movimentos),
    'total_pages' => 1,
    'from' => count($movimentos) > 0 ? 1 : 0,
    'to' => count($movimentos),
];
$categoryOptions = is_array($report['categoryOptions'] ?? null) ? $report['categoryOptions'] : [];
$query = (string) ($report['query'] ?? '');
$pageQuery = (string) ($report['pageQuery'] ?? $query);
$dailyPageQuery = (string) ($report['dailyPageQuery'] ?? $query);
$formatCurrency = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
$formatPercent = static fn (float $value): string => number_format($value, 1, ',', '.') . '%';
$formatDate = static function (?string $date): string {
    $timestamp = $date ? strtotime($date) : false;

    return $timestamp ? date('d/m/Y', $timestamp) : '-';
};
$paymentLabel = static function (?string $payment): string {
    $payment = (string) $payment;

    return [
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'cartao' => 'Cartão',
        'transferencia' => 'Transferência',
        'boleto' => 'Boleto',
        'outro' => 'Outro',
    ][$payment] ?? ucfirst(str_replace('_', ' ', $payment));
};
$pageUrl = static fn (int $page): string => url('/relatorios?' . ($pageQuery !== '' ? $pageQuery . '&' : '') . 'page=' . $page);
$dailyPageUrl = static fn (int $page): string => url('/relatorios?' . ($dailyPageQuery !== '' ? $dailyPageQuery . '&' : '') . 'daily_page=' . $page);
$maxDaily = 1.0;
foreach ($dailyAll as $day) {
    $maxDaily = max($maxDaily, (float) $day['entradas'], (float) $day['saidas']);
}
?>

<section class="page-section module-page report-page">
    <div class="module-hero reports-hero">
        <div>
            <p class="eyebrow">Relatórios</p>
            <h1>Relatório financeiro detalhado</h1>
            <p>Analise receitas, despesas, saldo, categorias, formas de pagamento e movimentações por período.</p>
        </div>

        <div class="module-hero-actions">
            <a class="button primary" href="<?= \App\Core\View::e(url('/relatorios/exportar/excel?' . $query)) ?>">
                <i data-lucide="file-spreadsheet"></i>
                Exportar Excel
            </a>
            <a class="button secondary" href="<?= \App\Core\View::e(url('/relatorios/exportar/pdf?' . $query)) ?>" target="_blank" rel="noopener">
                <i data-lucide="file-text"></i>
                Gerar PDF
            </a>
        </div>
    </div>

    <?php if (is_string($report['loadError'] ?? null)): ?>
        <div class="alert error report-alert"><?= \App\Core\View::e($report['loadError']) ?></div>
    <?php endif; ?>

    <form class="report-filter-card advanced-report-filter" method="get" action="<?= \App\Core\View::e(url('/relatorios')) ?>">
        <div>
            <span class="section-kicker">Filtros essenciais</span>
            <h2><?= \App\Core\View::e((string) ($report['periodoLabel'] ?? 'Período')) ?></h2>
        </div>

        <div class="filter-fields report-filter-fields">
            <label>
                Início
                <input type="date" name="data_inicio" value="<?= \App\Core\View::e((string) ($filters['data_inicio'] ?? date('Y-m-01'))) ?>">
            </label>
            <label>
                Fim
                <input type="date" name="data_fim" value="<?= \App\Core\View::e((string) ($filters['data_fim'] ?? date('Y-m-t'))) ?>">
            </label>
            <label>
                Tipo
                <select name="tipo">
                    <option value="todos" <?= ($filters['tipo'] ?? 'todos') === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="entrada" <?= ($filters['tipo'] ?? '') === 'entrada' ? 'selected' : '' ?>>Entradas</option>
                    <option value="saida" <?= ($filters['tipo'] ?? '') === 'saida' ? 'selected' : '' ?>>Saídas</option>
                </select>
            </label>
            <label>
                Categoria
                <select name="categoria_id">
                    <option value="">Todas</option>
                    <?php foreach ($categoryOptions as $categoria): ?>
                        <option value="<?= \App\Core\View::e($categoria['id']) ?>" <?= (int) ($filters['categoria_id'] ?? 0) === (int) $categoria['id'] ? 'selected' : '' ?>>
                            <?= \App\Core\View::e($categoria['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Pagamento
                <select name="forma_pagamento">
                    <option value="">Todos</option>
                    <?php foreach (['dinheiro', 'pix', 'cartao', 'transferencia', 'boleto', 'outro'] as $payment): ?>
                        <option value="<?= \App\Core\View::e($payment) ?>" <?= ($filters['forma_pagamento'] ?? '') === $payment ? 'selected' : '' ?>>
                            <?= \App\Core\View::e($paymentLabel($payment)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Linhas por página
                <select name="per_page">
                    <?php foreach ([10, 15, 25, 50] as $perPageOption): ?>
                        <option value="<?= \App\Core\View::e((string) $perPageOption) ?>" <?= (int) ($filters['per_page'] ?? 10) === $perPageOption ? 'selected' : '' ?>>
                            <?= \App\Core\View::e((string) $perPageOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="report-filter-actions">
            <button class="button primary" type="submit">
                <i data-lucide="filter"></i>
                Aplicar filtros
            </button>
            <a class="button secondary" href="<?= \App\Core\View::e(url('/relatorios')) ?>">
                <i data-lucide="rotate-ccw"></i>
                Limpar
            </a>
        </div>
    </form>

    <div class="metric-grid report-metrics detailed-report-metrics">
        <article class="metric-card metric-success">
            <div class="metric-card-header">
                <span class="metric-icon"><i data-lucide="arrow-down-circle"></i></span>
                <span class="metric-badge"><?= \App\Core\View::e((string) ((int) ($summary['quantidade_entradas'] ?? 0))) ?> registro(s)</span>
            </div>
            <span class="metric-label">Total de entradas</span>
            <strong class="metric-value"><?= \App\Core\View::e($formatCurrency((float) ($summary['entradas'] ?? 0))) ?></strong>
            <p class="metric-helper">Ticket médio: <?= \App\Core\View::e($formatCurrency((float) ($summary['ticket_medio_entrada'] ?? 0))) ?></p>
        </article>

        <article class="metric-card metric-danger">
            <div class="metric-card-header">
                <span class="metric-icon"><i data-lucide="arrow-up-circle"></i></span>
                <span class="metric-badge"><?= \App\Core\View::e((string) ((int) ($summary['quantidade_saidas'] ?? 0))) ?> registro(s)</span>
            </div>
            <span class="metric-label">Total de saídas</span>
            <strong class="metric-value"><?= \App\Core\View::e($formatCurrency((float) ($summary['saidas'] ?? 0))) ?></strong>
            <p class="metric-helper">Ticket médio: <?= \App\Core\View::e($formatCurrency((float) ($summary['ticket_medio_saida'] ?? 0))) ?></p>
        </article>

        <article class="metric-card <?= (float) ($summary['saldo'] ?? 0) >= 0 ? 'metric-warning' : 'metric-danger' ?>">
            <div class="metric-card-header">
                <span class="metric-icon"><i data-lucide="wallet"></i></span>
                <span class="metric-badge">Resultado</span>
            </div>
            <span class="metric-label">Saldo do período</span>
            <strong class="metric-value"><?= \App\Core\View::e($formatCurrency((float) ($summary['saldo'] ?? 0))) ?></strong>
            <p class="metric-helper">Comprometimento: <?= \App\Core\View::e($formatPercent((float) ($summary['comprometimento'] ?? 0))) ?></p>
        </article>

        <article class="metric-card metric-info">
            <div class="metric-card-header">
                <span class="metric-icon"><i data-lucide="activity"></i></span>
                <span class="metric-badge">Volume</span>
            </div>
            <span class="metric-label">Movimentações</span>
            <strong class="metric-value"><?= \App\Core\View::e((string) ((int) ($summary['quantidade_total'] ?? 0))) ?></strong>
            <p class="metric-helper">Maior entrada: <?= \App\Core\View::e($formatCurrency((float) ($summary['maior_entrada'] ?? 0))) ?></p>
        </article>
    </div>

    <div class="report-detail-grid">
        <article class="chart-card chart-card-wide daily-flow-card">
            <div class="chart-header">
                <div>
                    <span class="section-kicker">Fluxo diário</span>
                    <h2>Entradas e saídas por data</h2>
                </div>
                <span class="badge badge-muted">
                    <?= \App\Core\View::e((string) $dailyPagination['from']) ?>-<?= \App\Core\View::e((string) $dailyPagination['to']) ?>
                    de <?= \App\Core\View::e((string) $dailyPagination['total']) ?> dia(s)
                </span>
            </div>

            <?php if ($dailyAll === []): ?>
                <div class="empty-state compact-empty">
                    <i data-lucide="line-chart"></i>
                    <strong>Nenhum dado no período</strong>
                    <p>Ajuste os filtros ou registre entradas e saídas para alimentar o relatório.</p>
                </div>
            <?php else: ?>
                <div class="daily-flow-list">
                    <?php foreach ($daily as $day): ?>
                        <div class="daily-flow-item">
                            <strong><?= \App\Core\View::e($formatDate($day['data'])) ?></strong>
                            <div class="daily-bars">
                                <span class="daily-bar daily-bar-success" style="width: <?= \App\Core\View::e((string) max(6, ((float) $day['entradas'] / $maxDaily) * 100)) ?>%"></span>
                                <span class="daily-bar daily-bar-danger" style="width: <?= \App\Core\View::e((string) max(6, ((float) $day['saidas'] / $maxDaily) * 100)) ?>%"></span>
                            </div>
                            <small>
                                Entradas <?= \App\Core\View::e($formatCurrency((float) $day['entradas'])) ?> ·
                                Saídas <?= \App\Core\View::e($formatCurrency((float) $day['saidas'])) ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ((int) $dailyPagination['total_pages'] > 1): ?>
                    <nav class="pagination-nav compact-pagination" aria-label="Paginação do fluxo diário">
                        <a class="pagination-button <?= (int) $dailyPagination['current_page'] <= 1 ? 'is-disabled' : '' ?>"
                           href="<?= \App\Core\View::e($dailyPageUrl(max(1, (int) $dailyPagination['current_page'] - 1))) ?>"
                           aria-label="Página anterior do fluxo diário">
                            <i data-lucide="chevron-left"></i>
                        </a>

                        <div class="pagination-pages">
                            <?php
                            $currentDailyPage = (int) $dailyPagination['current_page'];
                            $totalDailyPages = (int) $dailyPagination['total_pages'];
                            $startDailyPage = max(1, $currentDailyPage - 1);
                            $endDailyPage = min($totalDailyPages, $currentDailyPage + 1);
                            ?>
                            <?php for ($dailyPageNumber = $startDailyPage; $dailyPageNumber <= $endDailyPage; $dailyPageNumber++): ?>
                                <a class="pagination-button <?= $dailyPageNumber === $currentDailyPage ? 'is-active' : '' ?>"
                                   href="<?= \App\Core\View::e($dailyPageUrl($dailyPageNumber)) ?>">
                                    <?= \App\Core\View::e((string) $dailyPageNumber) ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <a class="pagination-button <?= (int) $dailyPagination['current_page'] >= (int) $dailyPagination['total_pages'] ? 'is-disabled' : '' ?>"
                           href="<?= \App\Core\View::e($dailyPageUrl(min((int) $dailyPagination['total_pages'], (int) $dailyPagination['current_page'] + 1))) ?>"
                           aria-label="Próxima página do fluxo diário">
                            <i data-lucide="chevron-right"></i>
                        </a>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </article>

        <article class="chart-card">
            <div class="chart-header">
                <div>
                    <span class="section-kicker">Despesas</span>
                    <h2>Saídas por categoria</h2>
                </div>
            </div>

            <?php if ($categorias === []): ?>
                <div class="empty-state compact-empty">
                    <i data-lucide="pie-chart"></i>
                    <strong>Sem categorias no período</strong>
                    <p>As categorias aparecem quando há saídas filtradas.</p>
                </div>
            <?php else: ?>
                <div class="category-expense-list detailed-category-list">
                    <?php foreach ($categorias as $categoria): ?>
                        <div class="category-expense-item">
                            <span class="category-pill">
                                <span style="background: <?= \App\Core\View::e($categoria['cor'] ?: '#2FAF8F') ?>"></span>
                                <?= \App\Core\View::e($categoria['nome']) ?>
                            </span>
                            <strong><?= \App\Core\View::e($formatCurrency((float) $categoria['total'])) ?></strong>
                            <small><?= \App\Core\View::e($formatPercent((float) $categoria['percentual'])) ?> · <?= \App\Core\View::e((string) $categoria['quantidade']) ?> lançamento(s)</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </div>

    <div class="report-detail-grid secondary-report-grid">
        <article class="chart-card">
            <div class="chart-header">
                <div>
                    <span class="section-kicker">Pagamento</span>
                    <h2>Formas de pagamento</h2>
                </div>
            </div>
            <?php if ($formasPagamento === []): ?>
                <div class="empty-state compact-empty">
                    <i data-lucide="credit-card"></i>
                    <strong>Sem formas de pagamento</strong>
                    <p>As formas aparecem conforme as movimentações filtradas.</p>
                </div>
            <?php else: ?>
                <div class="payment-summary-grid">
                    <?php foreach ($formasPagamento as $payment): ?>
                        <div>
                            <span><?= \App\Core\View::e($payment['nome']) ?></span>
                            <strong><?= \App\Core\View::e($formatCurrency((float) $payment['total'])) ?></strong>
                            <small><?= \App\Core\View::e((string) $payment['quantidade']) ?> movimento(s)</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="chart-card">
            <div class="chart-header">
                <div>
                    <span class="section-kicker">Indicadores</span>
                    <h2>Pontos de atenção</h2>
                </div>
            </div>
            <div class="report-insight-list">
                <div>
                    <span>Maior saída</span>
                    <strong><?= \App\Core\View::e($formatCurrency((float) ($summary['maior_saida'] ?? 0))) ?></strong>
                </div>
                <div>
                    <span>Maior entrada</span>
                    <strong><?= \App\Core\View::e($formatCurrency((float) ($summary['maior_entrada'] ?? 0))) ?></strong>
                </div>
                <div>
                    <span>Saldo</span>
                    <strong class="<?= (float) ($summary['saldo'] ?? 0) >= 0 ? 'amount-positive' : 'amount-negative' ?>">
                        <?= \App\Core\View::e($formatCurrency((float) ($summary['saldo'] ?? 0))) ?>
                    </strong>
                </div>
            </div>
        </article>
    </div>

    <article class="transactions-card entries-list-card">
        <div class="chart-header">
            <div>
                <span class="section-kicker">Auditoria</span>
                <h2>Movimentações detalhadas</h2>
            </div>
            <span class="badge badge-muted">
                <?= \App\Core\View::e((string) $pagination['from']) ?>-<?= \App\Core\View::e((string) $pagination['to']) ?>
                de <?= \App\Core\View::e((string) $pagination['total']) ?>
            </span>
        </div>

        <?php if ($movimentos === []): ?>
            <div class="empty-state">
                <i data-lucide="inbox"></i>
                <strong>Nenhuma movimentação encontrada</strong>
                <p>Use os filtros acima para ampliar o período ou registre novas movimentações.</p>
            </div>
        <?php else: ?>
            <div class="transactions-table-wrap">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Categoria</th>
                            <th>Origem/Fornecedor</th>
                            <th>Pagamento</th>
                            <th>Descrição</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimentos as $item): ?>
                            <tr>
                                <td data-label="Data"><?= \App\Core\View::e($formatDate($item['data'])) ?></td>
                                <td data-label="Tipo">
                                    <span class="badge <?= $item['movimento'] === 'entrada' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= $item['movimento'] === 'entrada' ? 'Entrada' : 'Saída' ?>
                                    </span>
                                </td>
                                <td data-label="Categoria">
                                    <span class="category-pill">
                                        <span style="background: <?= \App\Core\View::e($item['categoria_cor'] ?: '#2FAF8F') ?>"></span>
                                        <?= \App\Core\View::e($item['categoria_nome']) ?>
                                    </span>
                                </td>
                                <td data-label="Origem/Fornecedor"><?= \App\Core\View::e($item['pessoa']) ?></td>
                                <td data-label="Pagamento"><?= \App\Core\View::e($paymentLabel($item['forma_pagamento'])) ?></td>
                                <td data-label="Descrição"><?= \App\Core\View::e($item['descricao']) ?></td>
                                <td data-label="Valor" class="<?= $item['movimento'] === 'entrada' ? 'amount-positive' : 'amount-negative' ?>">
                                    <?= $item['movimento'] === 'entrada' ? '+' : '-' ?><?= \App\Core\View::e($formatCurrency((float) $item['valor'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ((int) $pagination['total_pages'] > 1): ?>
                <nav class="pagination-nav" aria-label="Paginação de movimentações">
                    <a class="pagination-button <?= (int) $pagination['current_page'] <= 1 ? 'is-disabled' : '' ?>"
                       href="<?= \App\Core\View::e($pageUrl(max(1, (int) $pagination['current_page'] - 1))) ?>"
                       aria-label="Página anterior">
                        <i data-lucide="chevron-left"></i>
                    </a>

                    <div class="pagination-pages">
                        <?php
                        $currentPage = (int) $pagination['current_page'];
                        $totalPages = (int) $pagination['total_pages'];
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        ?>
                        <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
                            <a class="pagination-button <?= $pageNumber === $currentPage ? 'is-active' : '' ?>"
                               href="<?= \App\Core\View::e($pageUrl($pageNumber)) ?>">
                                <?= \App\Core\View::e((string) $pageNumber) ?>
                            </a>
                        <?php endfor; ?>
                    </div>

                    <a class="pagination-button <?= (int) $pagination['current_page'] >= (int) $pagination['total_pages'] ? 'is-disabled' : '' ?>"
                       href="<?= \App\Core\View::e($pageUrl(min((int) $pagination['total_pages'], (int) $pagination['current_page'] + 1))) ?>"
                       aria-label="Próxima página">
                        <i data-lucide="chevron-right"></i>
                    </a>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </article>
</section>
