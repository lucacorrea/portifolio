<?php
$pageKey = 'relatorios';
$activePage = 'relatorios';
$pageTitle = 'Relatórios';
$pageSubtitle = 'Acompanhe indicadores operacionais e financeiros';
$primaryActionLabel = 'Atualizar';
$primaryActionIcon = 'bi-arrow-clockwise';
$primaryActionHandler = 'renderCurrentPage()';
$pageContent = __DIR__ . '/pages/operational.php';
$pageScripts = ['assets/js/osmais-app.js'];

require __DIR__ . '/includes/shell.php';
