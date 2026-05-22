<?php
$pageKey = 'ordens';
$activePage = 'ordens';
$pageTitle = 'Ordens de Serviço';
$pageSubtitle = 'Controle os atendimentos técnicos da empresa';
$primaryActionLabel = 'Nova OS';
$primaryActionIcon = 'bi-plus-lg';
$primaryActionHandler = "openEntityModal('os')";
$pageContent = __DIR__ . '/pages/operational.php';
$pageScripts = ['assets/js/osmais-app.js'];

require __DIR__ . '/includes/shell.php';
