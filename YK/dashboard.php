<?php
$pageTitle = 'Dashboard';
$pageSubtitle = 'Visão geral do sistema';
$activePage = 'dashboard';
$primaryActionLabel = 'Nova OS';
$primaryActionIcon = 'bi-plus-lg';
$primaryActionTarget = '#modal-os';
$primaryActionPermission = 'os.criar';
$requiredPermission = 'dashboard.visualizar';
$pageContent = __DIR__ . '/pages/dashboard.php';

require __DIR__ . '/includes/shell.php';
