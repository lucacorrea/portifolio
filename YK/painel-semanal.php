<?php
declare(strict_types=1);

$activePage = 'painel-semanal';

$pageTitle = 'Serviços da Semana';
$pageSubtitle = 'Distribuição semanal dos atendimentos por dupla';

$primaryActionLabel = 'Adicionar serviço';
$primaryActionIcon = 'bi-calendar-plus';
$primaryActionHref = 'ordens-servico.php?modal=create';
$primaryActionPermission = 'painel_semanal.adicionar';
$requiredPermission = 'painel_semanal.visualizar';

$pageContent = __DIR__ . '/pages/painel-semanal.php';
$pageScripts = ['assets/js/painel-semanal.js'];

require __DIR__ . '/includes/shell.php';
