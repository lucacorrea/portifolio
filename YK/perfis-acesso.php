<?php
declare(strict_types=1);

$pageTitle = 'Perfis de Acesso';
$pageSubtitle = 'Gerencie tipos de usuário e permissões';
$activePage = 'perfis-acesso';
$primaryActionLabel = 'Novo perfil';
$primaryActionIcon = 'bi-plus-lg';
$primaryActionHref = 'perfil-formulario.php';
$primaryActionPermission = 'perfil.criar';
$requiredPermission = 'perfil.visualizar';
$pageContent = __DIR__ . '/pages/perfis-acesso.php';
$pageScripts = ['assets/js/perfis-acesso.js'];

require __DIR__ . '/includes/shell.php';
