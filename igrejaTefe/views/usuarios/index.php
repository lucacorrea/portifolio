<?php

$usuarios = is_array($usuarios ?? null) ? $usuarios : [];
$currentUserId = (int) ($currentUserId ?? 0);
$summary = is_array($summary ?? null) ? $summary : [
    'total' => count($usuarios),
    'ativos' => count(array_filter($usuarios, static fn (array $usuario): bool => (int) $usuario['ativo'] === 1)),
    'inativos' => 0,
];
$summary['inativos'] = (int) ($summary['inativos'] ?? max(0, (int) $summary['total'] - (int) $summary['ativos']));
$pagination = is_array($pagination ?? null) ? $pagination : [
    'current_page' => 1,
    'per_page' => 10,
    'total' => count($usuarios),
    'total_pages' => 1,
    'from' => count($usuarios) > 0 ? 1 : 0,
    'to' => count($usuarios),
    'per_page_options' => [10, 15, 25, 50],
];
$roleLabel = static fn (string $role): string => [
    'admin' => 'Administrador',
    'tesoureiro' => 'Tesoureiro',
    'visualizador' => 'Visualizador',
][$role] ?? ucfirst($role);
$formatDateTime = static function (?string $date): string {
    $timestamp = $date ? strtotime($date) : false;

    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Nunca';
};
$pageUrl = static fn (int $page): string => url('/usuarios?' . http_build_query([
    'per_page' => (int) $pagination['per_page'],
    'page' => $page,
]));
?>

<section class="page-section module-page users-page">
    <div class="module-hero users-hero">
        <div>
            <p class="eyebrow">Usuários</p>
            <h1>Equipe e permissões</h1>
            <p>Gerencie acessos, papéis e status dos usuários vinculados à igreja.</p>
        </div>

        <div class="module-hero-actions">
            <a class="button primary" href="<?= \App\Core\View::e(url('/usuarios/criar')) ?>">
                <i data-lucide="user-plus"></i>
                Novo usuário
            </a>
            <a class="button secondary" href="<?= \App\Core\View::e(url('/configuracoes')) ?>">
                <i data-lucide="settings"></i>
                Configurações
            </a>
        </div>
    </div>

    <div class="category-overview-grid">
        <article class="status-card">
            <span>Usuários ativos</span>
            <strong><?= \App\Core\View::e((string) ((int) $summary['ativos'])) ?></strong>
            <p>Acessos liberados para uso do sistema.</p>
        </article>

        <article class="status-card">
            <span>Desativados</span>
            <strong><?= \App\Core\View::e((string) ((int) $summary['inativos'])) ?></strong>
            <p>Preservados para histórico e auditoria.</p>
        </article>
    </div>

    <article class="transactions-card entries-list-card">
        <div class="chart-header">
            <div>
                <span class="section-kicker">Acessos</span>
                <h2>Usuários cadastrados</h2>
            </div>
            <span class="badge badge-muted"><?= \App\Core\View::e((string) $pagination['total']) ?> usuário(s)</span>
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
                <form class="table-page-size" method="get" action="<?= \App\Core\View::e(url('/usuarios')) ?>">
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

        <?php if ($usuarios === []): ?>
            <div class="empty-state">
                <i data-lucide="users"></i>
                <strong>Nenhum usuário cadastrado</strong>
                <p>Cadastre os usuários da equipe administrativa, tesouraria e visualização.</p>
                <a class="button primary" href="<?= \App\Core\View::e(url('/usuarios/criar')) ?>">
                    <i data-lucide="user-plus"></i>
                    Cadastrar primeiro usuário
                </a>
            </div>
        <?php else: ?>
            <div class="transactions-table-wrap">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Usuário</th>
                            <th>Email</th>
                            <th>Papel</th>
                            <th>Status</th>
                            <th>Último login</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td data-label="Usuário">
                                    <span class="user-row-title">
                                        <span class="user-avatar small-avatar"><?= \App\Core\View::e(strtoupper(substr((string) $usuario['nome'], 0, 1))) ?></span>
                                        <?= \App\Core\View::e($usuario['nome']) ?>
                                    </span>
                                </td>
                                <td data-label="Email"><?= \App\Core\View::e($usuario['email']) ?></td>
                                <td data-label="Papel">
                                    <span class="badge badge-muted"><?= \App\Core\View::e($roleLabel((string) $usuario['papel'])) ?></span>
                                </td>
                                <td data-label="Status">
                                    <span class="badge <?= (int) $usuario['ativo'] === 1 ? 'badge-success' : 'badge-danger' ?>">
                                        <?= (int) $usuario['ativo'] === 1 ? 'Ativo' : 'Desativado' ?>
                                    </span>
                                </td>
                                <td data-label="Último login"><?= \App\Core\View::e($formatDateTime($usuario['ultimo_login_em'] ?? null)) ?></td>
                                <td data-label="Ação">
                                    <div class="table-action-group">
                                        <a class="table-action" href="<?= \App\Core\View::e(url('/usuarios/editar?id=' . $usuario['id'])) ?>">Editar</a>
                                        <?php if ((int) $usuario['id'] !== $currentUserId): ?>
                                            <?php if ((int) $usuario['ativo'] === 1): ?>
                                                <form method="post" action="<?= \App\Core\View::e(url('/usuarios/desativar')) ?>">
                                                    <input type="hidden" name="_csrf_token" value="<?= \App\Core\Session::csrfToken() ?>">
                                                    <input type="hidden" name="id" value="<?= \App\Core\View::e($usuario['id']) ?>">
                                                    <button class="link-button danger-link" type="submit">Desativar</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" action="<?= \App\Core\View::e(url('/usuarios/ativar')) ?>">
                                                    <input type="hidden" name="_csrf_token" value="<?= \App\Core\Session::csrfToken() ?>">
                                                    <input type="hidden" name="id" value="<?= \App\Core\View::e($usuario['id']) ?>">
                                                    <button class="link-button" type="submit">Ativar</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ((int) $pagination['total_pages'] > 1): ?>
                <nav class="pagination-nav" aria-label="Paginação de usuários">
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
