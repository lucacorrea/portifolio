<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = $pageTitle ?? 'Painel Admin';
$contentView = $contentView ?? null;

$adminNome = $_SESSION['usuario_nome'] ?? 'Administrador';
$adminIniciais = mb_strtoupper(mb_substr($adminNome, 0, 1) . mb_substr(explode(' ', trim($adminNome))[1] ?? '', 0, 1));
$empresaNome = 'Plataforma SaaS Contábil';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-app">

    <button class="mobile-toggle" id="mobileMenuToggle" aria-label="Abrir menu">
        ☰
    </button>

    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-brand">
            <div class="brand-logo">ContaFlow</div>
            <div class="brand-subtitle">painel administrativo</div>
        </div>

        <nav class="sidebar-nav">
            <a href="/admin/dashboard" class="nav-link active">
                <span class="nav-icon">◻</span>
                <span>Dashboard</span>
            </a>

            <a href="/admin/contadores" class="nav-link">
                <span class="nav-icon">👥</span>
                <span>Contadores</span>
            </a>

            <a href="/admin/planos" class="nav-link">
                <span class="nav-icon">📦</span>
                <span>Planos</span>
            </a>

            <a href="/admin/assinaturas" class="nav-link">
                <span class="nav-icon">💳</span>
                <span>Assinaturas</span>
            </a>

            <a href="/admin/suporte" class="nav-link">
                <span class="nav-icon">🎫</span>
                <span>Suporte</span>
            </a>

            <a href="/admin/financeiro" class="nav-link">
                <span class="nav-icon">💰</span>
                <span>Financeiro</span>
            </a>

            <a href="/admin/configuracoes" class="nav-link">
                <span class="nav-icon">⚙</span>
                <span>Configurações</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="/logout" class="nav-link logout-link">
                <span class="nav-icon">↩</span>
                <span>Sair</span>
            </a>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <div class="topbar-left">
                <h1 class="page-title"><?= h($pageTitle) ?></h1>
                <div class="page-subtitle"><?= h($empresaNome) ?></div>
            </div>

            <div class="topbar-right">
                <div class="search-box">
                    <input type="text" placeholder="Buscar contador, plano, assinatura...">
                </div>

                <button class="icon-btn" type="button" aria-label="Notificações">🔔</button>

                <div class="user-box">
                    <div class="user-avatar"><?= h($adminIniciais ?: 'AD') ?></div>
                    <div class="user-info">
                        <strong><?= h($adminNome) ?></strong>
                        <span>Administrador</span>
                    </div>
                </div>
            </div>
        </header>

        <section class="admin-content">
            <?php if ($contentView && file_exists($contentView)): ?>
                <?php include $contentView; ?>
            <?php else: ?>
                <div class="empty-state">
                    <h2>Conteúdo não encontrado</h2>
                    <p>A view informada não foi localizada.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<script src="/assets/js/admin.js"></script>
</body>
</html>