<?php
declare(strict_types=1);

$paginaAtual = $paginaAtual ?? '';
?>
<aside class="sidebar">
    <a class="brand" href="<?= $paginaAtual === 'dashboard' ? '#' : '../dashboard.php' ?>">
        <span class="brand-mark">S</span>
        <span class="brand-copy">
            <strong>SIGO</strong>
            <small>Gestão de Ofícios</small>
        </span>
    </a>

    <nav class="sidebar-nav" aria-label="Navegação principal">
        <a class="<?= $paginaAtual === 'dashboard' ? 'active' : '' ?>" href="<?= $paginaAtual === 'dashboard' ? 'dashboard.php' : '../dashboard.php' ?>">Dashboard</a>
        <a class="<?= $paginaAtual === 'oficios' ? 'active' : '' ?>" href="<?= $paginaAtual === 'dashboard' ? 'oficios/index.php' : '../oficios/index.php' ?>">Ofícios</a>
        <a class="<?= $paginaAtual === 'movimentacoes' ? 'active' : '' ?>" href="<?= $paginaAtual === 'dashboard' ? 'movimentacoes/index.php' : '../movimentacoes/index.php' ?>">Movimentações</a>
        <a class="<?= $paginaAtual === 'recebimento' ? 'active' : '' ?>" href="<?= $paginaAtual === 'dashboard' ? 'recebimento/index.php' : '../recebimento/index.php' ?>">Recebimento rápido</a>
        <a class="<?= $paginaAtual === 'pessoas' ? 'active' : '' ?>" href="<?= $paginaAtual === 'dashboard' ? 'pessoas/index.php' : '../pessoas/index.php' ?>">Pessoas</a>
        <a class="<?= $paginaAtual === 'relatorios' ? 'active' : '' ?>" href="<?= $paginaAtual === 'dashboard' ? 'relatorios/index.php' : '../relatorios/index.php' ?>">Relatórios</a>
        <a class="<?= $paginaAtual === 'configuracoes' ? 'active' : '' ?>" href="<?= $paginaAtual === 'dashboard' ? 'configuracoes/index.php' : '../configuracoes/index.php' ?>">Configurações</a>
    </nav>

    <div class="sidebar-footer">
        <span>Casa Civil</span>
        <small>Sistema Integrado de Gestão de Ofícios</small>
    </div>
</aside>
