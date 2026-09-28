<?php
declare(strict_types=1);
?>
<header class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" type="button" data-sidebar-open aria-label="Abrir menu">☰</button>
        <div class="topbar-title">
            <strong><?= htmlspecialchars($paginaTitulo ?? 'SIGO', ENT_QUOTES, 'UTF-8') ?></strong>
            <span><?= htmlspecialchars($paginaDescricao ?? 'Sistema Integrado de Gestão de Ofícios', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <div class="topbar-actions">
        <label class="quick-search">
            <span class="search-icon">⌕</span>
            <span class="sr-only">Buscar protocolo, ofício ou responsável</span>
            <input type="search" placeholder="Buscar protocolo, ofício ou responsável">
            <kbd>Ctrl K</kbd>
        </label>

        <button class="notification-button" type="button" aria-label="Notificações">
            <span>♢</span>
            <i></i>
        </button>

        <button class="user-chip" type="button" aria-label="Abrir menu do usuário">
            <span class="user-avatar">AD</span>
            <span class="user-info">
                <strong>Administrador</strong>
                <small>Casa Civil</small>
            </span>
            <span class="user-chevron">⌄</span>
        </button>
    </div>
</header>
