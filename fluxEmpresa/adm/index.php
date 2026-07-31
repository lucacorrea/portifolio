<?php
declare(strict_types=1);

$pageTitle = 'Administração';
$pageSubtitle = 'Gestão completa da plataforma Flux Empresas';
$activePage = 'platform-admin';
$showPrimaryAction = false;
$requiredPermission = 'dashboard.visualizar';
$platformAdminOnly = true;
$documentBaseHref = '../';
$pageContent = dirname(__DIR__) . '/pages/admin-dashboard.php';

require dirname(__DIR__) . '/includes/shell.php';
