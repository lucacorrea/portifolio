<?php
if (!isset($paginaAtual)) {
    $paginaAtual = '';
}
?>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-badge">AFA</div>
        <div>
            <small>Agro Forest Amazon</small>
            <strong>Administrativo</strong>
        </div>
    </div>

    <div class="menu-title">Principal</div>
    <nav class="nav-list">
        <a href="<?= route_url('administrativo', 'dashboard') ?>" class="nav-link <?= $paginaAtual === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span>
            Dashboard
        </a>

        <a href="<?= route_url('administrativo', 'protocolosRecebidos') ?>" class="nav-link <?= $paginaAtual === 'protocolosRecebidos' ? 'active' : '' ?>">
            <span class="nav-icon">📥</span>
            Protocolos Recebidos
        </a>

        <a href="<?= route_url('administrativo', 'orcamentos') ?>" class="nav-link <?= $paginaAtual === 'orcamentos' ? 'active' : '' ?>">
            <span class="nav-icon">💰</span>
            Orçamentos
        </a>

        <a href="<?= route_url('administrativo', 'clientes') ?>" class="nav-link <?= $paginaAtual === 'clientes' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span>
            Clientes
        </a>

        <a href="<?= route_url('administrativo', 'documentos') ?>" class="nav-link <?= $paginaAtual === 'documentos' ? 'active' : '' ?>">
            <span class="nav-icon">📎</span>
            Documentos
        </a>
    </nav>

    <div class="menu-title">Gestão</div>
    <nav class="nav-list">
        <a href="<?= route_url('administrativo', 'pendencias') ?>" class="nav-link <?= $paginaAtual === 'pendencias' ? 'active' : '' ?>">
            <span class="nav-icon">⏳</span>
            Pendências
        </a>

        <a href="<?= route_url('administrativo', 'relatorios') ?>" class="nav-link <?= $paginaAtual === 'relatorios' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            Relatórios
        </a>

        <a href="<?= route_url('administrativo', 'configuracoes') ?>" class="nav-link <?= $paginaAtual === 'configuracoes' ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span>
            Configurações
        </a>
    </nav>

    <div class="sidebar-card">
        <strong>Resumo do administrativo</strong>
        <p>
            O setor administrativo recebe os protocolos, valida documentos,
            monta os orçamentos e acompanha as pendências até a finalização.
        </p>
        <a href="<?= route_url('administrativo', 'orcamentos') ?>" class="btn-sidebar">+ Novo Orçamento</a>
    </div>
</aside>