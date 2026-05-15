<?php

$entradas = is_array($entradas ?? null) ? $entradas : [];
$summary = is_array($summary ?? null) ? $summary : ['total' => 0, 'quantidade' => 0];
$pagination = is_array($pagination ?? null) ? $pagination : [
    'current_page' => 1,
    'per_page' => 10,
    'total' => count($entradas),
    'total_pages' => 1,
    'from' => count($entradas) > 0 ? 1 : 0,
    'to' => count($entradas),
    'per_page_options' => [10, 15, 25, 50],
];
$totalMes = (float) ($summary['total'] ?? 0);
$quantidadeMes = (int) ($summary['quantidade'] ?? 0);
$formatCurrency = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
$formatDate = static function (?string $date): string {
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);

    return $timestamp ? date('d/m/Y', $timestamp) : '-';
};
$formatLabel = static function (?string $value): string {
    $value = trim((string) $value);

    if ($value === '') {
        return '-';
    }

    $labels = [
        'dizimo' => 'Dízimo',
        'oferta' => 'Oferta',
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'cartao' => 'Cartão',
        'transferencia' => 'Transferência',
        'outro' => 'Outro',
    ];

    return $labels[$value] ?? ucfirst(str_replace('_', ' ', $value));
};
$pageUrl = static fn (int $page): string => url('/entradas?' . http_build_query([
    'per_page' => (int) $pagination['per_page'],
    'page' => $page,
]));
?>

<section class="page-section entries-page">
    <div class="module-hero entries-hero">
        <div>
            <p class="eyebrow">Entradas</p>
            <h1>Receitas da igreja</h1>
            <p>Liste dízimos, ofertas e demais contribuições registradas para acompanhar o fluxo de entrada do mês.</p>
        </div>

        <div class="module-hero-actions">
            <a class="button primary" href="<?= \App\Core\View::e(url('/entradas/criar')) ?>">
                <i data-lucide="plus-circle"></i>
                Nova entrada
            </a>
            <a class="button secondary" href="<?= \App\Core\View::e(url('/dashboard')) ?>">
                <i data-lucide="layout-dashboard"></i>
                Dashboard
            </a>
        </div>
    </div>

    <div class="entry-summary-grid">
        <article class="metric-card metric-success">
            <div class="metric-card-header">
                <span class="metric-icon">
                    <i data-lucide="arrow-down-circle"></i>
                </span>
                <span class="metric-badge">Mês atual</span>
            </div>
            <span class="metric-label">Total recebido</span>
            <strong class="metric-value"><?= \App\Core\View::e($formatCurrency($totalMes)) ?></strong>
            <p class="metric-helper"><?= $quantidadeMes > 0 ? \App\Core\View::e($quantidadeMes . ' entrada(s) no período.') : 'Sem movimentações neste período.' ?></p>
        </article>

        <article class="status-card entry-guidance-card">
            <span>Próximo passo</span>
            <strong>Cadastre uma nova entrada</strong>
            <p>Use o botão acima para abrir a tela de cadastro de dízimos e ofertas.</p>
        </article>
    </div>

    <article class="transactions-card entries-list-card">
        <div class="chart-header">
            <div>
                <span class="section-kicker">Listagem</span>
                <h2>Entradas registradas</h2>
            </div>
            <span class="badge badge-success"><?= \App\Core\View::e((string) $pagination['total']) ?> registro(s)</span>
        </div>

        <?php if (is_string($success ?? null)): ?>
            <div class="alert success"><?= \App\Core\View::e($success) ?></div>
        <?php endif; ?>

        <?php if (is_string($loadError ?? null)): ?>
            <div class="alert error"><?= \App\Core\View::e($loadError) ?></div>
        <?php endif; ?>

        <?php if ((int) $pagination['total'] > 0): ?>
            <div class="table-toolbar">
                <span>
                    Exibindo <?= \App\Core\View::e((string) $pagination['from']) ?>-<?= \App\Core\View::e((string) $pagination['to']) ?>
                    de <?= \App\Core\View::e((string) $pagination['total']) ?>
                </span>
                <form class="table-page-size" method="get" action="<?= \App\Core\View::e(url('/entradas')) ?>">
                    <label>
                        Linhas
                        <select name="per_page" onchange="this.form.submit()">
                            <?php foreach ($pagination['per_page_options'] as $perPageOption): ?>
                                <option value="<?= \App\Core\View::e((string) $perPageOption) ?>" <?= (int) $pagination['per_page'] === (int) $perPageOption ? 'selected' : '' ?>>
                                    <?= \App\Core\View::e((string) $perPageOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($entradas === []): ?>
            <div class="empty-state">
                <i data-lucide="receipt-text"></i>
                <strong>Nenhuma entrada registrada</strong>
                <p>Quando dízimos, ofertas ou contribuições forem cadastrados, eles aparecerão nesta listagem.</p>
                <a class="button primary" href="<?= \App\Core\View::e(url('/entradas/criar')) ?>">
                    <i data-lucide="plus-circle"></i>
                    Cadastrar primeira entrada
                </a>
            </div>
        <?php else: ?>
            <div class="transactions-table-wrap">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Contribuinte</th>
                            <th>Descrição</th>
                            <th>Pagamento</th>
                            <th>Data</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entradas as $entrada): ?>
                            <tr>
                                <td data-label="Tipo">
                                    <span class="badge badge-success"><?= \App\Core\View::e($formatLabel($entrada['tipo'] ?? null)) ?></span>
                                </td>
                                <td data-label="Contribuinte"><?= \App\Core\View::e($entrada['contribuinte_nome'] ?: 'Não informado') ?></td>
                                <td data-label="Descrição"><?= \App\Core\View::e($entrada['descricao'] ?: '-') ?></td>
                                <td data-label="Pagamento"><?= \App\Core\View::e($formatLabel($entrada['forma_pagamento'] ?? null)) ?></td>
                                <td data-label="Data"><?= \App\Core\View::e($formatDate($entrada['data_entrada'] ?? null)) ?></td>
                                <td data-label="Valor" class="amount-positive"><?= \App\Core\View::e($formatCurrency((float) ($entrada['valor'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ((int) $pagination['total_pages'] > 1): ?>
                <nav class="pagination-nav" aria-label="Paginação de entradas">
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
