<?php
$pageKey = 'painel-semanal';
$activePage = 'painel-semanal';

$pageTitle = 'Serviços da Semana';
$pageSubtitle = 'Distribuição semanal dos serviços por dupla';

$primaryActionLabel = 'Adicionar Serviço';
$primaryActionIcon = 'bi-calendar-plus';
$primaryActionTarget = '#modal-servico-semanal';
$primaryActionPermission = 'painel_semanal.adicionar';
$requiredPermission = 'painel_semanal.visualizar';

$pageContent = __DIR__ . '/pages/operational.php';

require __DIR__ . '/includes/shell.php';
