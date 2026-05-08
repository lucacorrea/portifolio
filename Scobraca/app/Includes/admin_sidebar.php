<?php
$currentPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$adminNavItems = [
    ['/admin/dashboard.php', 'Dashboard', '<path d="M3 9l9-6 9 6-9 6-9-6z"></path><path d="M3 15l9 6 9-6"></path>'],
    ['/admin/empresas.php', 'Empresas', '<path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path><path d="M9 9h1"></path><path d="M9 13h1"></path><path d="M9 17h1"></path>'],
    ['/admin/planos.php', 'Planos', '<path d="M20 7H4"></path><path d="M20 12H4"></path><path d="M20 17H4"></path><path d="M6 7v10"></path>'],
    ['/admin/assinaturas.php', 'Assinaturas', '<path d="M7 3h10l4 4v14H3V3h4z"></path><path d="M17 3v5h5"></path><path d="M8 13h8"></path><path d="M8 17h5"></path>'],
    ['/admin/usuarios-plataforma.php', 'Usuários da plataforma', '<path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9.5" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>'],
];
?>
<button class="mobile-toggle" type="button" data-sidebar-toggle aria-label="Abrir menu">
    <svg viewBox="0 0 24 24"><path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path></svg>
</button>
<aside class="sidebar" id="sidebar">
    <div class="logo-area">
        <div class="brand"><strong>FluxPay</strong><span>Admin da plataforma</span></div>
    </div>
    <nav class="nav">
        <?php foreach ($adminNavItems as [$href, $label, $icon]): ?>
            <?php $active = str_ends_with($currentPath, $href) ? ' active' : ''; ?>
            <a class="nav-item<?= $active ?>" href="<?= e(public_url($href)) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
        <span class="sidebar-spacer"></span>
        <a class="nav-item" href="<?= e(public_url('/logout.php')) ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M21 3v18"></path></svg>
            <span>Sair</span>
        </a>
    </nav>
</aside>
<div class="sidebar-backdrop" data-sidebar-close></div>
<script src="<?= e(public_url('/assets/js/app.js')) ?>" defer></script>
