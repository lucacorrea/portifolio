<?php
$pageKey = 'faturamento';
$activePage = 'faturamento';
$pageTitle = 'Notas / Faturamento';
$pageSubtitle = 'Controle visual de notas, valores e status financeiro';
$primaryActionLabel = 'Registrar Nota';
$primaryActionIcon = 'bi-receipt-cutoff';
$primaryActionHandler = "openEntityModal('nota')";
$pageContent = __DIR__ . '/pages/operational.php';
$pageScripts = ['assets/js/osmais-app.js'];

require __DIR__ . '/includes/shell.php';
