<?php
$currentPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$tenantNavItems = [
    ['/app/dashboard.php', 'Dashboard', '<path d="M3 9l9-6 9 6-9 6-9-6z"></path><path d="M3 15l9 6 9-6"></path>', ['/app/dashboard.php']],
    ['/app/clientes.php', 'Clientes', '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>', ['/app/clientes.php', '/app/clientes-cadastro.php']],
    ['/app/cobrancas.php', 'Cobranças', '<path d="M7 3h10l4 4v14H3V3h4z"></path><path d="M17 3v5h5"></path><path d="M8 13h8"></path><path d="M8 17h5"></path>', ['/app/cobrancas.php', '/app/cobrancas-cadastro.php']],
    ['/app/pagamentos.php', 'Pagamentos', '<path d="M4 7h16v10H4z"></path><path d="M4 11h16"></path><path d="M8 15h3"></path>', ['/app/pagamentos.php', '/app/pagamentos-cadastro.php']],
    ['/app/mensagens.php', 'Mensagens', '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path>', ['/app/mensagens.php', '/app/mensagens-enviar.php']],
    ['/app/conexao.php', 'Conexão', '<path d="M7 17a5 5 0 0 1 0-10"></path><path d="M17 7a5 5 0 0 1 0 10"></path><path d="M8 12h8"></path><path d="M11 9l-3 3 3 3"></path><path d="M13 9l3 3-3 3"></path>', ['/app/conexao.php']],
    ['/app/relatorios.php', 'Relatórios', '<path d="M4 19V5"></path><path d="M4 19h16"></path><path d="M8 16v-5"></path><path d="M12 16V8"></path><path d="M16 16v-3"></path>', ['/app/relatorios.php']],
    ['/app/usuarios.php', 'Usuários', '<path d="M17 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9.5" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>', ['/app/usuarios.php', '/app/usuarios-cadastro.php']],
    ['/app/suporte.php', 'Suporte', '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"></path><path d="M8 9h8"></path><path d="M8 13h5"></path>', ['/app/suporte.php', '/app/suporte-chat.php']],
    ['/app/configuracoes.php', 'Configurações', '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H5.78a1.65 1.65 0 0 0-1.51 1 1.65 1.65 0 0 0 .33 1.82A10 10 0 0 0 12 17.66 10 10 0 0 0 19.4 15z"></path>', ['/app/configuracoes.php']],
];
?>
<button class="mobile-toggle" type="button" data-sidebar-toggle aria-label="Abrir menu">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path></svg>
</button>
<aside class="sidebar" id="sidebar">
    <div class="logo-area">
        <div class="brand"><strong>FluxPay</strong><span>Painel da empresa</span></div>
    </div>
    <nav class="nav">
        <?php foreach ($tenantNavItems as [$href, $label, $icon, $matches]): ?>
            <?php
            $active = '';
            foreach ($matches as $match) {
                if (str_ends_with($currentPath, $match)) {
                    $active = ' active';
                    break;
                }
            }
            ?>
            <a class="nav-item<?= $active ?>" href="<?= e(public_url($href)) ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon ?></svg>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
        <span class="sidebar-spacer"></span>
        <a class="nav-item" href="<?= e(public_url('/logout.php')) ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M21 3v18"></path></svg>
            <span>Sair</span>
        </a>
    </nav>
</aside>
<div class="sidebar-backdrop" data-sidebar-close></div>
<script src="<?= e(asset_url('/assets/js/app.js')) ?>" defer></script>
