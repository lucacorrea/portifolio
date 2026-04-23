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
            <strong>Recepção</strong>
        </div>
    </div>

    <div class="menu-title">Principal</div>
    <nav class="nav-list">
        <a href="/agroForest/public/?pagina=dashboard" class="nav-link <?= $paginaAtual === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span>
            Dashboard
        </a>

        <a href="/agroForest/public/?pagina=novoProtocolo" class="nav-link <?= $paginaAtual === 'novo-protocolo' ? 'active' : '' ?>">
            <span class="nav-icon">📝</span>
            Novo Protocolo
        </a>

        <a href="/agroForest/public/?pagina=clientes" class="nav-link <?= $paginaAtual === 'clientes' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span>
            Clientes
        </a>

        <a href="/agroForest/public/?pagina=protocolos" class="nav-link <?= $paginaAtual === 'protocolos' ? 'active' : '' ?>">
            <span class="nav-icon">📂</span>
            Protocolos
        </a>

        <a href="/agroForest/public/?pagina=documentos" class="nav-link <?= $paginaAtual === 'documentos' ? 'active' : '' ?>">
            <span class="nav-icon">📎</span>
            Documentos
        </a>
    </nav>

    <div class="menu-title">Operação</div>
    <nav class="nav-list">
        <a href="/agroForest/public/?pagina=encaminhar" class="nav-link <?= $paginaAtual === 'encaminhar' ? 'active' : '' ?>">
            <span class="nav-icon">📤</span>
            Encaminhar
        </a>

        <a href="/agroForest/public/?pagina=pendencias" class="nav-link <?= $paginaAtual === 'pendencias' ? 'active' : '' ?>">
            <span class="nav-icon">⏳</span>
            Pendências
        </a>

        <a href="/agroForest/public/?pagina=relatorios" class="nav-link <?= $paginaAtual === 'relatorios' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            Relatórios
        </a>

        <a href="/agroForest/public/?pagina=configuracoes" class="nav-link <?= $paginaAtual === 'configuracoes' ? 'active' : '' ?>">
            <span class="nav-icon">⚙️</span>
            Configurações
        </a>
    </nav>

    <div class="sidebar-card">
        <strong>Resumo da recepção</strong>
        <p>
            A recepção cadastra o cliente, identifica o tipo de serviço, gera o protocolo
            e encaminha para o administrativo realizar o orçamento.
        </p>
        <button type="button">+ Abrir novo protocolo</button>
    </div>
</aside>