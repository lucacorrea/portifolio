<?php
declare(strict_types=1);

$activePage = 'painel-semanal';

$pageTitle = 'Serviços da Semana';
$pageSubtitle = 'Agenda semanal dos serviços confirmados';

$primaryActionLabel = 'Confirmar novo serviço';
$primaryActionIcon = 'bi-calendar-plus';
$primaryActionTarget = '#modal-week-create';
$primaryActionPermission = 'painel_semanal.adicionar';
$requiredPermission = 'painel_semanal.visualizar';

$pageContent = __DIR__ . '/pages/painel-semanal.php';
$pageScripts = ['assets/js/painel-semanal.js'];

require __DIR__ . '/includes/shell.php';
