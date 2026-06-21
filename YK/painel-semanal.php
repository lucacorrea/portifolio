<?php
declare(strict_types=1);

$activePage = 'painel-semanal';

$pageTitle = 'Serviços da Semana';
$pageSubtitle = 'Distribuição semanal dos atendimentos por dupla';

$primaryActionLabel = 'Adicionar serviço';
$primaryActionIcon = 'bi-calendar-plus';
$primaryActionUrl = 'ordens-servico.php?modal=create';
$primaryActionPermission = 'painel_semanal.adicionar';
$requiredPermission = 'painel_semanal.visualizar';

$pageContent = __DIR__ . '/pages/painel-semanal.php';

require __DIR__ . '/includes/shell.php';
