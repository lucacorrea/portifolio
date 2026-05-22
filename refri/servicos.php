<?php
$pageKey = 'servicos';
$activePage = 'servicos';
$pageTitle = 'Serviços';
$pageSubtitle = 'Cadastre os tipos de serviço oferecidos pela empresa';
$primaryActionLabel = 'Novo Serviço';
$primaryActionIcon = 'bi-plus-lg';
$primaryActionHandler = "openEntityModal('servico')";
$pageContent = __DIR__ . '/pages/operational.php';
$pageScripts = ['assets/js/osmais-app.js'];

require __DIR__ . '/includes/shell.php';
