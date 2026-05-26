<?php
$pageKey = 'tecnicos';
$activePage = 'tecnicos';
$pageTitle = 'Técnicos';
$pageSubtitle = 'Gerencie responsáveis técnicos e disponibilidade';
$primaryActionLabel = 'Novo Técnico';
$primaryActionIcon = 'bi-person-badge';
$primaryActionHandler = "openEntityModal('tecnico')";
$pageContent = __DIR__ . '/pages/operational.php';
$pageScripts = ['assets/js/osmais-app.js'];

require __DIR__ . '/includes/shell.php';
