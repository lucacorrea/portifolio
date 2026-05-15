<?php

$categorias = is_array($categorias ?? null) ? $categorias : [];
$ativas = array_values(array_filter($categorias, static fn (array $categoria): bool => (int) $categoria['ativo'] === 1));
$inativas = count($categorias) - count($ativas);
?>

<section class="page-section module-page">
    <div class="module-hero categories-hero">
        <div>
            <p class="eyebrow">Categorias</p>
            <h1>Plano de categorias</h1>
            <p>Organize despesas por finalidade para manter relatórios claros, comparáveis e fáceis de auditar.</p>
        </div>

        <div class="module-hero-actions">
            <a class="button primary" href="<?= \App\Core\View::e(url('/categorias/criar')) ?>">
                <i data-lucide="plus-circle"></i>
                Nova categoria
            </a>
            <a class="button secondary" href="<?= \App\Core\View::e(url('/saidas')) ?>">
                <i data-lucide="arrow-up-circle"></i>
                Ver saídas
            </a>
        </div>
    </div>

    <div class="category-overview-grid">
        <article class="status-card">
            <span>Categorias ativas</span>
            <strong><?= \App\Core\View::e((string) count($ativas)) ?></strong>
            <p>Disponíveis para classificar novas saídas.</p>
        </article>

        <article class="status-card">
            <span>Inativas</span>
            <strong><?= \App\Core\View::e((string) $inativas) ?></strong>
            <p>Preservadas para histórico e consistência de relatórios.</p>
        </article>
    </div>

    <article class="transactions-card entries-list-card">
        <div class="chart-header">
            <div>
                <span class="section-kicker">Organização</span>
                <h2>Categorias cadastradas</h2>
            </div>
            <span class="badge badge-muted"><?= \App\Core\View::e((string) count($categorias)) ?> total</span>
        </div>

        <?php if (is_string($success ?? null)): ?>
            <div class="alert success"><?= \App\Core\View::e($success) ?></div>
        <?php endif; ?>

        <?php if (is_string($loadError ?? null)): ?>
            <div class="alert error"><?= \App\Core\View::e($loadError) ?></div>
        <?php endif; ?>

        <?php if ($categorias === []): ?>
            <div class="empty-state">
                <i data-lucide="tags"></i>
                <strong>Nenhuma categoria cadastrada</strong>
                <p>Crie categorias como Aluguel, Energia, Missões e Manutenção para organizar as saídas.</p>
                <a class="button primary" href="<?= \App\Core\View::e(url('/categorias/criar')) ?>">
                    <i data-lucide="plus-circle"></i>
                    Cadastrar primeira categoria
                </a>
            </div>
        <?php else: ?>
            <div class="category-grid">
                <?php foreach ($categorias as $categoria): ?>
                    <article class="category-card">
                        <div class="category-card-head">
                            <span class="category-swatch" style="background: <?= \App\Core\View::e($categoria['cor'] ?: '#2FAF8F') ?>"></span>
                            <span class="badge <?= (int) $categoria['ativo'] === 1 ? 'badge-success' : 'badge-muted' ?>">
                                <?= (int) $categoria['ativo'] === 1 ? 'Ativa' : 'Inativa' ?>
                            </span>
                        </div>
                        <strong><?= \App\Core\View::e($categoria['nome']) ?></strong>
                        <p><?= \App\Core\View::e($categoria['descricao'] ?: 'Sem descrição cadastrada.') ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>
</section>
