<?php require_once dirname(__DIR__, 3) . '/Helpers/url.php'; ?>
<aside class="sidebar">
    <div class="brand"><div class="brand-badge">AFA</div><div><small>Agro Forest Amazon</small><strong>Administrativo</strong></div></div>
    <div class="menu-title">Principal</div>
    <nav class="nav-list">
        <a href="<?= route_url('administrativo', 'dashboard') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'dashboard' ? 'active' : '' ?>"><span class="nav-icon">🏠</span>Dashboard</a>
        <a href="<?= route_url('administrativo', 'protocolosRecebidos') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'protocolosRecebidos' ? 'active' : '' ?>"><span class="nav-icon">📥</span>Protocolos Recebidos</a>
        <a href="<?= route_url('administrativo', 'orcamentos') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'orcamentos' ? 'active' : '' ?>"><span class="nav-icon">💰</span>Orçamentos</a>
        <a href="<?= route_url('administrativo', 'analises') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'analises' ? 'active' : '' ?>"><span class="nav-icon">📋</span>Análises</a>
        <a href="<?= route_url('administrativo', 'relatorios') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'relatorios' ? 'active' : '' ?>"><span class="nav-icon">📊</span>Relatórios</a>
    </nav>
</aside>
