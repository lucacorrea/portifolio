<?php
declare(strict_types=1);

$paginaAtual = $paginaAtual ?? '';
$prefixo = $paginaAtual === 'dashboard' ? '' : '../';
?>
<header class="topbar">
    <a class="topbar-brand" href="<?= $prefixo ?>dashboard.php" aria-label="SIGO - Início">
        <span class="brand-symbol">
            <span></span><span></span><span></span>
        </span>
        <span class="brand-text">SIGO</span>
    </a>

    <nav class="top-nav" aria-label="Navegação principal">
        <a class="<?= $paginaAtual === 'dashboard' ? 'active' : '' ?>" href="<?= $prefixo ?>dashboard.php">
            <span class="nav-circle"><i data-lucide="house"></i></span>
            Dashboard
        </a>
        <a class="<?= $paginaAtual === 'oficios' ? 'active' : '' ?>" href="<?= $prefixo ?>oficios/index.php">
            <span class="nav-circle"><i data-lucide="file-text"></i></span>
            Ofícios
        </a>
        <a class="<?= $paginaAtual === 'movimentacoes' ? 'active' : '' ?>" href="<?= $prefixo ?>movimentacoes/index.php">
            <span class="nav-circle"><i data-lucide="arrow-left-right"></i></span>
            Movimentações
        </a>
        <a class="<?= $paginaAtual === 'recebimento' ? 'active' : '' ?>" href="<?= $prefixo ?>recebimento/index.php">
            <span class="nav-circle"><i data-lucide="circle-check"></i></span>
            Recebimento
        </a>
    </nav>

    <div class="topbar-actions">
        <button class="icon-button" type="button" data-search-open aria-label="Buscar">
            <i data-lucide="search"></i>
        </button>
        <button class="icon-button notification" type="button" aria-label="Notificações">
            <i data-lucide="bell"></i>
            <span></span>
        </button>
        <button class="profile-button" type="button" aria-label="Perfil do administrador">
            <span class="profile-avatar">AD</span>
            <span class="profile-copy">
                <strong>Administrador</strong>
                <small>Casa Civil</small>
            </span>
            <i data-lucide="chevron-down"></i>
        </button>
    </div>
</header>
