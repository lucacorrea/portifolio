<?php

$saidas = is_array($saidas ?? null) ? $saidas : [];
$summary = is_array($summary ?? null) ? $summary : ['total' => 0, 'quantidade' => 0];
$totalMes = (float) ($summary['total'] ?? 0);
$quantidadeMes = (int) ($summary['quantidade'] ?? 0);
$formatCurrency = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
$formatDate = static function (?string $date): string {
    $timestamp = $date ? strtotime($date) : false;

    return $timestamp ? date('d/m/Y', $timestamp) : '-';
};
$formatLabel = static function (?string $value): string {
    $value = trim((string) $value);

    if ($value === '') {
        return '-';
    }

    $labels = [
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'cartao' => 'Cartão',
        'transferencia' => 'Transferência',
        'boleto' => 'Boleto',
        'outro' => 'Outro',
    ];

    return $labels[$value] ?? ucfirst(str_replace('_', ' ', $value));
};
?>

<section class="page-section module-page">
    <div class="module-hero expenses-hero">
        <div>
            <p class="eyebrow">Saídas</p>
            <h1>Despesas e pagamentos</h1>
            <p>Acompanhe pagamentos da igreja por categoria, fornecedor, forma de pagamento e data.</p>
        </div>

        <div class="module-hero-actions">
            <a class="button soft-danger" href="<?= \App\Core\View::e(url('/saidas/criar')) ?>">
                <i data-lucide="plus-circle"></i>
                Nova saída
            </a>
            <a class="button secondary" href="<?= \App\Core\View::e(url('/categorias')) ?>">
                <i data-lucide="tags"></i>
                Categorias
            </a>
        </div>
    </div>

    <div class="entry-summary-grid">
        <article class="metric-card metric-danger">
            <div class="metric-card-header">
                <span class="metric-icon">
                    <i data-lucide="arrow-up-circle"></i>
                </span>
                <span class="metric-badge">Mês atual</span>
            </div>
            <span class="metric-label">Total pago</span>
            <strong class="metric-value"><?= \App\Core\View::e($formatCurrency($totalMes)) ?></strong>
            <p class="metric-helper"><?= $quantidadeMes > 0 ? \App\Core\View::e($quantidadeMes . ' saída(s) no período.') : 'Sem movimentações neste período.' ?></p>
        </article>

        <article class="status-card entry-guidance-card">
            <span>Controle financeiro</span>
            <strong>Classifique cada despesa</strong>
            <p>Usar categorias consistentes melhora relatórios e leitura do saldo mensal.</p>
        </article>
    </div>

    <article class="transactions-card entries-list-card">
        <div class="chart-header">
            <div>
                <span class="section-kicker">Listagem</span>
                <h2>Saídas registradas</h2>
            </div>
            <span class="badge badge-danger"><?= \App\Core\View::e((string) count($saidas)) ?> registro(s)</span>
        </div>

        <?php if (is_string($success ?? null)): ?>
            <div class="alert success"><?= \App\Core\View::e($success) ?></div>
        <?php endif; ?>

        <?php if (is_string($loadError ?? null)): ?>
            <div class="alert error"><?= \App\Core\View::e($loadError) ?></div>
        <?php endif; ?>

        <?php if ($saidas === []): ?>
            <div class="empty-state">
                <i data-lucide="receipt"></i>
                <strong>Nenhuma saída registrada</strong>
                <p>Quando despesas ou pagamentos forem cadastrados, eles aparecerão nesta listagem.</p>
                <a class="button soft-danger" href="<?= \App\Core\View::e(url('/saidas/criar')) ?>">
                    <i data-lucide="plus-circle"></i>
                    Cadastrar primeira saída
                </a>
            </div>
        <?php else: ?>
            <div class="transactions-table-wrap">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th>Fornecedor</th>
                            <th>Descrição</th>
                            <th>Pagamento</th>
                            <th>Data</th>
                            <th>Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saidas as $saida): ?>
                            <tr>
                                <td data-label="Categoria">
                                    <span class="category-pill">
                                        <span style="background: <?= \App\Core\View::e($saida['categoria_cor'] ?: '#2FAF8F') ?>"></span>
                                        <?= \App\Core\View::e($saida['categoria_nome'] ?: 'Sem categoria') ?>
                                    </span>
                                </td>
                                <td data-label="Fornecedor"><?= \App\Core\View::e($saida['fornecedor'] ?: 'Não informado') ?></td>
                                <td data-label="Descrição"><?= \App\Core\View::e($saida['descricao'] ?: '-') ?></td>
                                <td data-label="Pagamento"><?= \App\Core\View::e($formatLabel($saida['forma_pagamento'] ?? null)) ?></td>
                                <td data-label="Data"><?= \App\Core\View::e($formatDate($saida['data_saida'] ?? null)) ?></td>
                                <td data-label="Valor" class="amount-negative">-<?= \App\Core\View::e($formatCurrency((float) ($saida['valor'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</section>
