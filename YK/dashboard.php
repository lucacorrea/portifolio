<?php
$pageTitle = 'Dashboard';
$pageSubtitle = 'Visão geral do sistema';
$activePage = 'dashboard';
$primaryActionLabel = 'Nova OS';
$primaryActionIcon = 'bi-plus-lg';
$primaryActionHandler = 'openModal()';
$pageContent = __DIR__ . '/pages/dashboard.php';
$pageScripts = ['assets/js/dashboard.js'];
$includeDashboardModal = true;

require __DIR__ . '/includes/shell.php';
