<?php

require_once __DIR__ . '/../app/bootstrap.php';

use FluxEmpresa\Core\Auth;

Auth::requireLogin();

$user = Auth::user();
$isSuperAdmin = Auth::isSuperAdmin();
$empresaId = Auth::empresaId();
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

ob_start();
?>
<section class="page-heading">
    <div>
        <span class="section-label"><?= $isSuperAdmin ? 'Acesso global L&J' : 'Empresa vinculada' ?></span>
        <h2>Dashboard do FluxEmpresa</h2>
        <p>Base administrativa para acompanhar operação, acessos e próximos módulos do MVP.</p>
    </div>

    <div class="context-card <?= $isSuperAdmin ? 'is-global' : '' ?>">
        <span><?= $isSuperAdmin ? 'Contexto atual' : 'Empresa atual' ?></span>
        <strong><?= $isSuperAdmin ? 'Global L&J' : ($empresaId !== null ? 'Empresa ID ' . h($empresaId) : 'Não vinculada') ?></strong>
    </div>
</section>

<section class="metric-grid" aria-label="Resumo do acesso">
    <article class="metric-card">
        <span>Usuário</span>
        <strong><?= h($user['nome'] ?? 'Usuário') ?></strong>
    </article>
    <article class="metric-card">
        <span>Perfil</span>
        <strong><?= h($user['perfil'] ?? '') ?></strong>
    </article>
    <article class="metric-card">
        <span>Escopo</span>
        <strong><?= $isSuperAdmin ? 'Todas as empresas' : 'Empresa própria' ?></strong>
    </article>
</section>

<?php if ($isSuperAdmin): ?>
    <section class="content-panel">
        <div>
            <span class="section-label">Super Admin</span>
            <h3>Área global L&J</h3>
            <p>Este acesso visualiza a administração global do FluxEmpresa. A troca de empresa ainda não foi implementada nesta fase.</p>
        </div>
    </section>
<?php else: ?>
    <section class="content-panel">
        <div>
            <span class="section-label">Empresa</span>
            <h3>Área operacional</h3>
            <p>Este acesso fica limitado aos módulos iniciais da empresa vinculada ao usuário logado.</p>
        </div>
    </section>
<?php endif; ?>
<?php
$layoutContent = ob_get_clean();

require APP_PATH . '/Views/layout/app.php';
