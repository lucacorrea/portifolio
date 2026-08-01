<?php
declare(strict_types=1);

$activePage = 'agenda';
$pageTitle = 'Agenda';
$pageSubtitle = 'Compromissos e assuntos internos da empresa';
$primaryActionLabel = 'Novo compromisso';
$primaryActionIcon = 'bi-calendar-plus';
$primaryActionTarget = '#modal-lembrete';
$primaryActionPermission = 'agenda.criar_lembrete';
$requiredPermission = 'agenda.visualizar';
$pageContent = __DIR__ . '/pages/agenda.php';
$pageScripts = ['assets/js/agenda.js'];

require __DIR__ . '/includes/shell.php';
