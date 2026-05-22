<?php
$pageKey = 'agenda';
$activePage = 'agenda';
$pageTitle = 'Agenda';
$pageSubtitle = 'Visualize atendimentos por data, horário e técnico';
$primaryActionLabel = 'Novo Agendamento';
$primaryActionIcon = 'bi-calendar-plus';
$primaryActionHandler = "openEntityModal('agenda')";
$pageContent = __DIR__ . '/pages/operational.php';
$pageScripts = ['assets/js/osmais-app.js'];

require __DIR__ . '/includes/shell.php';
