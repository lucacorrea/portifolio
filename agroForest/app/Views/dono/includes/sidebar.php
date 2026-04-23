<?php require_once dirname(__DIR__, 3) . '/Helpers/url.php'; ?>
<aside class="sidebar">
    <div class="brand"><div class="brand-badge">AFA</div><div><small>Agro Forest Amazon</small><strong>Dono</strong></div></div>
    <div class="menu-title">Gestão</div>
    <nav class="nav-list">
        <a href="<?= route_url('dono', 'dashboard') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'dashboard' ? 'active' : '' ?>"><span class="nav-icon">🏠</span>Dashboard</a>
        <a href="<?= route_url('dono', 'usuarios') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'usuarios' ? 'active' : '' ?>"><span class="nav-icon">👤</span>Usuários</a>
        <a href="<?= route_url('dono', 'permissoes') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'permissoes' ? 'active' : '' ?>"><span class="nav-icon">🛡️</span>Permissões</a>
        <a href="<?= route_url('dono', 'relatorios') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'relatorios' ? 'active' : '' ?>"><span class="nav-icon">📊</span>Relatórios</a>
        <a href="<?= route_url('dono', 'configuracoes') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'configuracoes' ? 'active' : '' ?>"><span class="nav-icon">⚙️</span>Configurações</a>
    </nav>
</aside>
