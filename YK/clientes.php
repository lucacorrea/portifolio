<?php
$pageKey = 'clientes';
$activePage = 'clientes';
$pageTitle = 'Clientes';
$pageSubtitle = 'Gerencie clientes, contatos e histórico de atendimentos';
$primaryActionLabel = 'Novo Cliente';
$primaryActionIcon = 'bi-person-plus';
$primaryActionHandler = "openEntityModal('cliente')";
$pageContent = __DIR__ . '/pages/operational.php';
$pageScripts = ['assets/js/osmais-app.js'];

require __DIR__ . '/includes/shell.php';
