<?php
$pageKey = 'pecas';
$activePage = 'pecas';
$pageTitle = 'Peças / Estoque';
$pageSubtitle = 'Controle peças utilizadas nos serviços e estoque mínimo';
$primaryActionLabel = 'Nova Peça';
$primaryActionIcon = 'bi-plus-lg';
$primaryActionHandler = "openEntityModal('peca')";
$pageContent = __DIR__ . '/pages/operational.php';
$pageScripts = ['assets/js/osmais-app.js'];

require __DIR__ . '/includes/shell.php';
