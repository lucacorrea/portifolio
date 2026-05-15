<?php

$categorias = is_array($categorias ?? null) ? $categorias : [];
$summary = is_array($summary ?? null) ? $summary : [
    'total' => count($categorias),
    'ativas' => count(array_filter($categorias, static fn (array $categoria): bool => (int) $categoria['ativo'] === 1)),
    'inativas' => 0,
];
$summary['inativas'] = (int) ($summary['inativas'] ?? max(0, (int) $summary['total'] - (int) $summary['ativas']));
$pagination = is_array($pagination ?? null) ? $pagination : [
    'current_page' => 1,
    'per_page' => 10,
    'total' => count($categorias),
    'total_pages' => 1,
    'from' => count($categorias) > 0 ? 1 : 0,
    'to' => count($categorias),
    'per_page_options' => [10, 15, 25, 50],
];
$pageUrl = static fn (int $page): string => url('/categorias?' . http_build_query([
    'per_page' => (int) $pagination['per_page'],
    'page' => $page,
]));
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
            <strong><?= \App\Core\View::e((string) ((int) $summary['ativas'])) ?></strong>
            <p>Disponíveis para classificar novas saídas.</p>
        </article>

        <article class="status-card">
            <span>Inativas</span>
            <strong><?= \App\Core\View::e((string) ((int) $summary['inativas'])) ?></strong>
            <p>Preservadas para histórico e consistência de relatórios.</p>
        </article>
    </div>

    <article class="transactions-card entries-list-card">
        <div class="chart-header">
            <div>
                <span class="section-kicker">Organização</span>
                <h2>Categorias cadastradas</h2>
            </div>
            <span class="badge badge-muted"><?= \App\Core\View::e((string) $pagination['total']) ?> total</span>
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
                <form class="table-page-size" method="get" action="<?= \App\Core\View::e(url('/categorias')) ?>">
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

            <?php if ((int) $pagination['total_pages'] > 1): ?>
                <nav class="pagination-nav" aria-label="Paginação de categorias">
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
