<?php
declare(strict_types=1);

$paginaAtual = $paginaAtual ?? '';
$prefixo = $paginaAtual === 'dashboard' ? '' : '../';
?>
<aside class="utility-rail" data-utility-rail>
    <button class="rail-button" type="button" data-search-open title="Buscar" aria-label="Buscar">
        <i data-lucide="search"></i>
    </button>

    <div class="rail-divider"></div>

    <a class="rail-button <?= $paginaAtual === 'dashboard' ? 'active' : '' ?>" href="<?= $prefixo ?>dashboard.php" title="Dashboard" aria-label="Dashboard">
        <i data-lucide="layout-dashboard"></i>
    </a>
    <a class="rail-button <?= $paginaAtual === 'oficios' ? 'active' : '' ?>" href="<?= $prefixo ?>oficios/index.php" title="Ofícios" aria-label="Ofícios">
        <i data-lucide="file-text"></i>
    </a>
    <a class="rail-button <?= $paginaAtual === 'movimentacoes' ? 'active' : '' ?>" href="<?= $prefixo ?>movimentacoes/index.php" title="Movimentações" aria-label="Movimentações">
        <i data-lucide="arrow-left-right"></i>
    </a>
    <a class="rail-button <?= $paginaAtual === 'recebimento' ? 'active' : '' ?>" href="<?= $prefixo ?>recebimento/index.php" title="Recebimento rápido" aria-label="Recebimento rápido">
        <i data-lucide="circle-check"></i>
    </a>
    <a class="rail-button <?= $paginaAtual === 'pessoas' ? 'active' : '' ?>" href="<?= $prefixo ?>pessoas/index.php" title="Pessoas" aria-label="Pessoas">
        <i data-lucide="users"></i>
    </a>
    <a class="rail-button <?= $paginaAtual === 'relatorios' ? 'active' : '' ?>" href="<?= $prefixo ?>relatorios/index.php" title="Relatórios" aria-label="Relatórios">
        <i data-lucide="chart-no-axes-column"></i>
    </a>

    <div class="rail-spacer"></div>

    <a class="rail-button <?= $paginaAtual === 'configuracoes' ? 'active' : '' ?>" href="<?= $prefixo ?>configuracoes/index.php" title="Configurações" aria-label="Configurações">
        <i data-lucide="settings"></i>
    </a>
</aside>
