<?php require_once dirname(__DIR__, 3) . '/Helpers/url.php'; ?>
<aside class="sidebar">
    <div class="brand">
        <div class="brand-badge">AFA</div>
        <div>
            <small>Agro Forest Amazon</small>
            <strong>Recepção</strong>
        </div>
    </div>

    <div class="menu-title">Principal</div>
    <nav class="nav-list">
        <a href="<?= route_url('recepcao', 'dashboard') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span>Dashboard
        </a>
        <a href="<?= route_url('recepcao', 'novoProtocolo') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'novoProtocolo' ? 'active' : '' ?>">
            <span class="nav-icon">📝</span>Novo Protocolo
        </a>
        <a href="<?= route_url('recepcao', 'clientes') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'clientes' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span>Clientes
        </a>
        <a href="<?= route_url('recepcao', 'protocolos') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'protocolos' ? 'active' : '' ?>">
            <span class="nav-icon">📂</span>Protocolos
        </a>
        <a href="<?= route_url('recepcao', 'documentos') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'documentos' ? 'active' : '' ?>">
            <span class="nav-icon">📎</span>Documentos
        </a>
    </nav>

    <div class="menu-title">Operação</div>
    <nav class="nav-list">
        <a href="<?= route_url('recepcao', 'encaminhar') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'encaminhar' ? 'active' : '' ?>">
            <span class="nav-icon">📤</span>Encaminhar
        </a>
        <a href="<?= route_url('recepcao', 'pendencias') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'pendencias' ? 'active' : '' ?>">
            <span class="nav-icon">⏳</span>Pendências
        </a>
        <a href="<?= route_url('recepcao', 'relatorios') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'relatorios' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>Relatórios
        </a>
        <a href="<?= route_url('recepcao', 'configuracoes') ?>" class="nav-link <?= ($paginaAtual ?? '') === 'configuracoes' ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span>Configurações
        </a>
    </nav>

    <div class="sidebar-card">
        <strong>Resumo da Recepção</strong>
        <p>
            A recepção registra o cliente, classifica o serviço, gera o protocolo e encaminha o atendimento para o setor administrativo elaborar o orçamento.
        </p>
        <a href="<?= route_url('recepcao', 'novoProtocolo') ?>" class="btn-sidebar">+ Abrir novo protocolo</a>
    </div>
</aside>
