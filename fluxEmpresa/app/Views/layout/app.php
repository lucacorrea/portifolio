<?php

use FluxEmpresa\Core\Auth;

defined('APP_PATH') || exit('Acesso direto negado.');

Auth::requireLogin();

$pageTitle = $pageTitle ?? 'Dashboard';
$currentPage = $currentPage ?? 'dashboard';
$layoutContent = $layoutContent ?? '';
$layoutUser = Auth::user() ?? [];
$layoutRole = Auth::role();
$layoutIsSuperAdmin = Auth::isSuperAdmin();
$layoutEmpresaId = Auth::empresaId();
$layoutRoleLabels = [
    'SUPER_ADMIN' => 'Super Admin',
    'ADMIN_EMPRESA' => 'Admin Empresa',
    'OPERADOR' => 'Operador',
    'FINANCEIRO' => 'Financeiro',
];
$layoutRoleLabel = $layoutRoleLabels[$layoutRole] ?? ($layoutRole !== '' ? $layoutRole : 'Perfil');
$layoutContextLabel = $layoutIsSuperAdmin
    ? 'Acesso global L&J'
    : ($layoutEmpresaId !== null ? 'Empresa ID ' . $layoutEmpresaId : 'Empresa não vinculada');
$layoutMenuItems = $layoutIsSuperAdmin ? [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php'],
    ['key' => 'empresas', 'label' => 'Empresas', 'href' => '#'],
    ['key' => 'usuarios', 'label' => 'Usuários', 'href' => '#'],
    ['key' => 'auditoria', 'label' => 'Auditoria', 'href' => '#'],
    ['key' => 'configuracoes', 'label' => 'Configurações', 'href' => '#'],
] : [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php'],
    ['key' => 'clientes', 'label' => 'Clientes', 'href' => '#'],
    ['key' => 'produtos-servicos', 'label' => 'Produtos/Serviços', 'href' => '#'],
    ['key' => 'solicitacoes', 'label' => 'Solicitações', 'href' => '#'],
    ['key' => 'orcamentos', 'label' => 'Orçamentos', 'href' => '#'],
    ['key' => 'execucoes', 'label' => 'Execuções', 'href' => '#'],
    ['key' => 'pagamentos', 'label' => 'Pagamentos', 'href' => '#'],
    ['key' => 'relatorios', 'label' => 'Relatórios', 'href' => '#'],
];

require __DIR__ . '/head.php';
?>
<body class="admin-page">
    <div class="admin-shell">
        <?php require __DIR__ . '/sidebar.php'; ?>

        <div class="admin-main">
            <?php require __DIR__ . '/header.php'; ?>

            <main class="admin-content">
                <?= $layoutContent ?>
            </main>

            <?php require __DIR__ . '/footer.php'; ?>
        </div>
    </div>

    <?php require __DIR__ . '/scripts.php'; ?>
</body>
</html>
