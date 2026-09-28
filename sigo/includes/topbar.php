<?php
declare(strict_types=1);
?>
<header class="topbar">
    <div>
        <strong><?= htmlspecialchars($paginaTitulo ?? 'SIGO', ENT_QUOTES, 'UTF-8') ?></strong>
        <span><?= htmlspecialchars($paginaDescricao ?? 'Sistema Integrado de Gestão de Ofícios', ENT_QUOTES, 'UTF-8') ?></span>
    </div>

    <div class="topbar-actions">
        <label class="quick-search">
            <span class="sr-only">Buscar protocolo ou ofício</span>
            <input type="search" placeholder="Buscar protocolo ou ofício">
        </label>
        <div class="user-chip">
            <span class="user-avatar">A</span>
            <span>
                <strong>Administrador</strong>
                <small>Casa Civil</small>
            </span>
        </div>
    </div>
</header>
