<?php
declare(strict_types=1);

$paginaAtual = $paginaAtual ?? '';
$naRaiz = $paginaAtual === 'dashboard';
$prefixo = $naRaiz ? '' : '../';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a class="brand" href="<?= $prefixo ?>dashboard.php">
            <span class="brand-mark">S</span>
            <span class="brand-copy">
                <strong>SIGO</strong>
                <small>Gestão de Ofícios</small>
            </span>
        </a>
        <button class="sidebar-close" type="button" data-sidebar-close aria-label="Fechar menu">×</button>
    </div>

    <div class="sidebar-context">
        <span class="context-mark">CC</span>
        <span>
            <small>Órgão</small>
            <strong>Casa Civil</strong>
        </span>
    </div>

    <nav class="sidebar-nav" aria-label="Navegação principal">
        <span class="nav-section">Visão geral</span>
        <a class="<?= $paginaAtual === 'dashboard' ? 'active' : '' ?>" href="<?= $prefixo ?>dashboard.php">
            <span class="nav-icon">⌂</span><span>Dashboard</span>
        </a>

        <span class="nav-section">Operação</span>
        <a class="<?= $paginaAtual === 'oficios' ? 'active' : '' ?>" href="<?= $prefixo ?>oficios/index.php">
            <span class="nav-icon">▤</span><span>Ofícios</span><span class="nav-badge">30</span>
        </a>
        <a class="<?= $paginaAtual === 'movimentacoes' ? 'active' : '' ?>" href="<?= $prefixo ?>movimentacoes/index.php">
            <span class="nav-icon">⇄</span><span>Movimentações</span>
        </a>
        <a class="<?= $paginaAtual === 'recebimento' ? 'active' : '' ?>" href="<?= $prefixo ?>recebimento/index.php">
            <span class="nav-icon">✓</span><span>Recebimento rápido</span><span class="nav-badge warning">5</span>
        </a>

        <span class="nav-section">Gestão</span>
        <a class="<?= $paginaAtual === 'pessoas' ? 'active' : '' ?>" href="<?= $prefixo ?>pessoas/index.php">
            <span class="nav-icon">♙</span><span>Pessoas</span>
        </a>
        <a class="<?= $paginaAtual === 'relatorios' ? 'active' : '' ?>" href="<?= $prefixo ?>relatorios/index.php">
            <span class="nav-icon">▥</span><span>Relatórios</span>
        </a>
        <a class="<?= $paginaAtual === 'configuracoes' ? 'active' : '' ?>" href="<?= $prefixo ?>configuracoes/index.php">
            <span class="nav-icon">⚙</span><span>Configurações</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-footer-icon">?</div>
        <span>
            <strong>Precisa de ajuda?</strong>
            <small>Consulte o responsável pelo SIGO</small>
        </span>
    </div>
</aside>
<div class="sidebar-overlay" data-sidebar-close></div>
