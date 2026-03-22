<?php
use App\Services\MenuService;

if (!function_exists('sidebar_icon')) {
    function sidebar_icon(string $icon): string
    {
        $icons = [
            'home' => '<svg viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg>',
            'users' => '<svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87"/></svg>',
            'file' => '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>',
            'money' => '<svg viewBox="0 0 24 24"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14.5a3.5 3.5 0 0 1 0 7H6"/></svg>',
            'card' => '<svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>',
            'settings' => '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1-2 3.4-.2-.1a1.7 1.7 0 0 0-2 .1 1.7 1.7 0 0 0-.8 1.5V23H9.2v-.2a1.7 1.7 0 0 0-.8-1.5 1.7 1.7 0 0 0-2-.1l-.2.1-2-3.4.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3V9.2h.2a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1 2-3.4.2.1a1.7 1.7 0 0 0 2-.1 1.7 1.7 0 0 0 .8-1.5V1h5.6v.2a1.7 1.7 0 0 0 .8 1.5 1.7 1.7 0 0 0 2 .1l.2-.1 2 3.4-.1.1a1.7 1.7 0 0 0-.3 1.8 1.7 1.7 0 0 0 1.5 1h.2V15h-.2a1.7 1.7 0 0 0-1.5 1Z"/></svg>',
        ];

        return $icons[$icon] ?? $icons['home'];
    }
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div>
            <div class="brand-title">Contábil ERP</div>
            <div class="brand-subtitle">Gestão inteligente do escritório</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-group">
            <div class="sidebar-group-title">Principal</div>

            <?php foreach (($menuItems ?? []) as $item): ?>
                <a href="<?= htmlspecialchars((string)$item['route']) ?>"
                   class="nav-link <?= MenuService::isActive((string)($activeMenu ?? ''), (string)$item['key']) ? 'is-active' : '' ?>">
                    <span class="nav-icon"><?= sidebar_icon((string)$item['icon']) ?></span>
                    <span><?= htmlspecialchars((string)$item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-plan">
            <strong>Escritório em operação</strong>
            <span>Painel preparado para clientes, obrigações, guias e financeiro.</span>
        </div>
    </div>
</aside>
