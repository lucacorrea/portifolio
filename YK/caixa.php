<?php
$pageKey = 'caixa';
$activePage = 'caixa';
$pageTitle = 'Caixa';
$pageSubtitle = 'Sessão, PDV, estoque e movimentações auditáveis';
$requiredPermission = 'caixa.visualizar';
$pageContent = __DIR__ . '/pages/caixa.php';
$pageScripts = ['assets/js/caixa.js'];

require __DIR__ . '/includes/shell.php';
