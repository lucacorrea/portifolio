<?php
declare(strict_types=1);

$paginaAtual = $paginaAtual ?? '';
$prefixo = $paginaAtual === 'dashboard' ? '' : '../';
?>
<aside class="utility-rail" data-utility-rail>
    <button class="rail-button" type="button" data-search-open title="Buscar" aria-label="Buscar">
        <i data-lucide="search"></i>
    </button>

    <a class="rail-button rail-accent" href="<?= $prefixo ?>oficios/cadastrar.php" title="Novo ofício" aria-label="Novo ofício">
        <i data-lucide="plus"></i>
    </a>

    <div class="rail-divider"></div>

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
