<?php

$entradas = is_array($entradas ?? null) ? $entradas : [];
$summary = is_array($summary ?? null) ? $summary : ['total' => 0, 'quantidade' => 0];
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
            <span class="badge badge-success"><?= \App\Core\View::e((string) count($entradas)) ?> registro(s)</span>
        </div>

        <?php if (is_string($success ?? null)): ?>
            <div class="alert success"><?= \App\Core\View::e($success) ?></div>
        <?php endif; ?>

        <?php if (is_string($loadError ?? null)): ?>
            <div class="alert error"><?= \App\Core\View::e($loadError) ?></div>
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
        <?php endif; ?>
    </article>
</section>
