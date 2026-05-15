<?php

$entradas = is_array($entradas ?? null) ? $entradas : ['total' => 0, 'quantidade' => 0];
$saidas = is_array($saidas ?? null) ? $saidas : ['total' => 0, 'quantidade' => 0];
$categorias = is_array($categorias ?? null) ? $categorias : [];
$totalEntradas = (float) ($entradas['total'] ?? 0);
$totalSaidas = (float) ($saidas['total'] ?? 0);
$saldo = $totalEntradas - $totalSaidas;
$formatCurrency = static fn (float $value): string => 'R$ ' . number_format($value, 2, ',', '.');
?>

<section class="page-section module-page">
    <div class="module-hero reports-hero">
        <div>
            <p class="eyebrow">Relatórios</p>
            <h1>Resumo financeiro</h1>
            <p>Visualize entradas, saídas, saldo do mês e concentração de despesas por categoria.</p>
        </div>

        <div class="module-hero-actions">
            <a class="button primary" href="<?= \App\Core\View::e(url('/entradas')) ?>">
                <i data-lucide="arrow-down-circle"></i>
                Entradas
            </a>
            <a class="button soft-danger" href="<?= \App\Core\View::e(url('/saidas')) ?>">
                <i data-lucide="arrow-up-circle"></i>
                Saídas
            </a>
        </div>
    </div>

    <div class="report-filter-card">
        <div>
            <span class="section-kicker">Período</span>
            <h2>Mês atual</h2>
        </div>
        <div class="filter-fields">
            <label>
                Início
                <input type="date" value="<?= \App\Core\View::e(date('Y-m-01')) ?>" readonly>
            </label>
            <label>
                Fim
                <input type="date" value="<?= \App\Core\View::e(date('Y-m-t')) ?>" readonly>
            </label>
        </div>
    </div>

    <div class="metric-grid report-metrics">
        <article class="metric-card metric-success">
            <div class="metric-card-header">
                <span class="metric-icon"><i data-lucide="arrow-down-circle"></i></span>
                <span class="metric-badge"><?= \App\Core\View::e((string) ((int) ($entradas['quantidade'] ?? 0))) ?> registro(s)</span>
            </div>
            <span class="metric-label">Entradas</span>
            <strong class="metric-value"><?= \App\Core\View::e($formatCurrency($totalEntradas)) ?></strong>
            <p class="metric-helper">Receitas registradas no mês atual.</p>
        </article>

        <article class="metric-card metric-danger">
            <div class="metric-card-header">
                <span class="metric-icon"><i data-lucide="arrow-up-circle"></i></span>
                <span class="metric-badge"><?= \App\Core\View::e((string) ((int) ($saidas['quantidade'] ?? 0))) ?> registro(s)</span>
            </div>
            <span class="metric-label">Saídas</span>
            <strong class="metric-value"><?= \App\Core\View::e($formatCurrency($totalSaidas)) ?></strong>
            <p class="metric-helper">Despesas registradas no mês atual.</p>
        </article>

        <article class="metric-card <?= $saldo >= 0 ? 'metric-warning' : 'metric-danger' ?>">
            <div class="metric-card-header">
                <span class="metric-icon"><i data-lucide="wallet"></i></span>
                <span class="metric-badge">Saldo</span>
            </div>
            <span class="metric-label">Resultado do mês</span>
            <strong class="metric-value"><?= \App\Core\View::e($formatCurrency($saldo)) ?></strong>
            <p class="metric-helper"><?= $saldo >= 0 ? 'Saldo positivo no período.' : 'Saídas acima das entradas no período.' ?></p>
        </article>
    </div>

    <div class="dashboard-grid report-grid">
        <article class="chart-card chart-card-wide">
            <div class="chart-header">
                <div>
                    <span class="section-kicker">Análise</span>
                    <h2>Leitura executiva</h2>
                </div>
                <span class="badge badge-muted">Atualizado</span>
            </div>
            <div class="report-insight-list">
                <div>
                    <span>Receitas</span>
                    <strong><?= \App\Core\View::e($formatCurrency($totalEntradas)) ?></strong>
                </div>
                <div>
                    <span>Despesas</span>
                    <strong><?= \App\Core\View::e($formatCurrency($totalSaidas)) ?></strong>
                </div>
                <div>
                    <span>Resultado</span>
                    <strong class="<?= $saldo >= 0 ? 'amount-positive' : 'amount-negative' ?>"><?= \App\Core\View::e($formatCurrency($saldo)) ?></strong>
                </div>
            </div>
        </article>

        <article class="chart-card">
            <div class="chart-header">
                <div>
                    <span class="section-kicker">Categorias</span>
                    <h2>Maiores despesas</h2>
                </div>
            </div>

            <?php if ($categorias === []): ?>
                <div class="empty-state compact-empty">
                    <i data-lucide="pie-chart"></i>
                    <strong>Sem despesas por categoria</strong>
                    <p>As categorias aparecerão após o cadastro de saídas.</p>
                </div>
            <?php else: ?>
                <div class="category-expense-list">
                    <?php foreach ($categorias as $categoria): ?>
                        <div class="category-expense-item">
                            <span class="category-pill">
                                <span style="background: <?= \App\Core\View::e($categoria['cor'] ?: '#2FAF8F') ?>"></span>
                                <?= \App\Core\View::e($categoria['nome']) ?>
                            </span>
                            <strong><?= \App\Core\View::e($formatCurrency((float) ($categoria['total'] ?? 0))) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </div>
</section>
