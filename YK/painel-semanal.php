<?php

declare(strict_types=1);

$activePage = 'painel-semanal';

$pageTitle = 'Painel Semanal de Serviços';

$pageSubtitle =
    'Agenda operacional por funcionário, equipe, dia e horário';

// O painel semanal abre com a navegação lateral recolhida para priorizar a agenda.
$pageBodyClass = 'sidebar-collapsed weekly-panel-shell';

$primaryActionLabel = 'Novo serviço';
$primaryActionIcon = 'bi-calendar-plus';
$primaryActionTarget = '#modal-week-create';

$primaryActionPermission =
    'painel_semanal.adicionar';

$requiredPermission =
    'painel_semanal.visualizar';

$pageContent =
    __DIR__ . '/pages/painel-semanal.php';

$pageScripts = [
    'assets/js/painel-semanal.js',
];

require __DIR__ . '/includes/shell.php';