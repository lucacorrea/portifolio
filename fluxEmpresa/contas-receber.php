<?php
declare(strict_types=1);

$activePage = 'contas-receber';
$pageTitle = 'Contas a Receber';
$pageSubtitle = 'Histórico de valores pendentes e pagos das ordens de serviço';
$requiredPermission = 'contas_receber.visualizar';
$pageContent = __DIR__ . '/pages/contas-receber.php';
$pageScripts = ['assets/js/contas-receber.js'];

require __DIR__ . '/includes/shell.php';
