<?php
$pageKey = 'servicos';
$activePage = 'servicos';
$pageTitle = 'Serviços';
$pageSubtitle = 'Cadastre os tipos de serviço oferecidos pela empresa';
$primaryActionLabel = 'Novo Serviço';
$primaryActionIcon = 'bi-plus-lg';
$primaryActionTarget = '#modal-servico';
$primaryActionPermission = 'servico.criar';
$requiredPermission = 'servico.visualizar';
$pageContent = __DIR__ . '/pages/operational.php';

require __DIR__ . '/includes/shell.php';
