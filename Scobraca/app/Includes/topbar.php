<?php
$usuarioAtual = current_user() ?? [];
$nomeUsuario = (string) ($usuarioAtual['nome'] ?? 'Usuário');
$empresaNome = (string) (($usuarioAtual['empresa_nome'] ?? '') ?: 'Admin da Plataforma');
$nomePartes = preg_split('/\s+/', trim($nomeUsuario)) ?: [];
$iniciais = '';

foreach ($nomePartes as $parte) {
    if ($parte !== '') {
        $iniciais .= strtoupper(substr($parte, 0, 1));
    }

    if (strlen($iniciais) >= 2) {
        break;
    }
}

$iniciais = $iniciais !== '' ? $iniciais : 'FP';
?>
<header class="topbar">
    <div class="topbar-title">
        <strong><?= e($pageTitle ?? 'Painel') ?></strong>
        <?php if (!empty($pageDescription)): ?>
            <p><?= e($pageDescription) ?></p>
        <?php endif; ?>
    </div>
    <div class="topbar-actions">
        <div class="search-box" role="search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10" cy="10" r="7"></circle><path d="M21 21l-6-6"></path></svg>
            <input type="search" data-table-search placeholder="Buscar nesta página">
        </div>
        <span class="notification-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        </span>
        <div class="userbox">
            <div class="userbox-text">
                <span><?= e($nomeUsuario) ?></span>
                <small><?= e($empresaNome) ?></small>
            </div>
            <div class="avatar"><?= e(substr($iniciais, 0, 2)) ?></div>
        </div>
    </div>
</header>
