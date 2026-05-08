<?php require_once dirname(__DIR__, 3) . '/Helpers/url.php'; ?>
<aside class="sidebar">
    <div class="brand"><div class="brand-badge">AFA</div><div><small>Agro Forest Amazon</small><strong>Dono</strong></div></div>
    <div class="menu-title">Gestão</div>
    <nav class="nav-list">
        <a href="<?= route_url('dono', 'dashboard') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'dashboard' ? 'active' : '' ?>"><span class="nav-icon">🏠</span>Dashboard</a>
        <a href="<?= route_url('dono', 'clientes') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'clientes' ? 'active' : '' ?>"><span class="nav-icon">👥</span>Clientes</a>
        <a href="<?= route_url('dono', 'terrenos') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'terrenos' ? 'active' : '' ?>"><span class="nav-icon">🗺️</span>Terrenos</a>
        <a href="<?= route_url('dono', 'usuarios') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'usuarios' ? 'active' : '' ?>"><span class="nav-icon">👤</span>Usuários</a>
        <a href="<?= route_url('dono', 'permissoes') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'permissoes' ? 'active' : '' ?>"><span class="nav-icon">🛡️</span>Permissões</a>
        <a href="<?= route_url('dono', 'tiposServicos') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'tiposServicos' ? 'active' : '' ?>"><span class="nav-icon">🧾</span>Tipos de Serviços</a>
        <a href="<?= route_url('dono', 'relatorios') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'relatorios' ? 'active' : '' ?>"><span class="nav-icon">📊</span>Relatórios</a>
        <a href="<?= route_url('dono', 'configuracoes') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'configuracoes' ? 'active' : '' ?>"><span class="nav-icon">⚙️</span>Configurações</a>
    </nav>
    <div class="menu-title">Acesso total</div>
    <nav class="nav-list">
        <a href="<?= route_url('recepcao', 'dashboard') ?>" class="nav-link"><span class="nav-icon">🏢</span>Recepção</a>
        <a href="<?= route_url('recepcao', 'novoProtocolo') ?>" class="nav-link"><span class="nav-icon">➕</span>Novo Protocolo</a>
        <a href="<?= route_url('recepcao', 'clientes') ?>" class="nav-link"><span class="nav-icon">👥</span>Clientes</a>
        <a href="<?= route_url('administrativo', 'dashboard') ?>" class="nav-link"><span class="nav-icon">📋</span>Administrativo</a>
        <a href="<?= route_url('administrativo', 'terrenos') ?>" class="nav-link"><span class="nav-icon">🗺️</span>Terrenos</a>
        <a href="<?= route_url('administrativo', 'protocolosRecebidos') ?>" class="nav-link"><span class="nav-icon">📥</span>Protocolos Recebidos</a>
        <a href="<?= route_url('administrativo', 'orcamentos') ?>" class="nav-link"><span class="nav-icon">💰</span>Orçamentos</a>
    </nav>
</aside>
