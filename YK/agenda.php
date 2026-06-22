<?php
declare(strict_types=1);

$activePage = 'agenda';
$pageTitle = 'Agenda';
$pageSubtitle = 'Atendimentos e lembretes por data e horário';
$primaryActionLabel = 'Novo Lembrete';
$primaryActionIcon = 'bi-alarm';
$primaryActionTarget = '#modal-lembrete';
$primaryActionPermission = 'agenda.criar_lembrete';
$requiredPermission = 'agenda.visualizar';
$pageContent = __DIR__ . '/pages/agenda.php';
$pageScripts = ['assets/js/agenda.js'];

require __DIR__ . '/includes/shell.php';
