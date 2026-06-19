<?php
$pageKey = 'transportadoras';
$activePage = 'transportadoras';
$pageTitle = 'Transportadoras';
$pageSubtitle = 'Cadastro visual de transportadoras e prazos';
$primaryActionLabel = 'Nova Transportadora';
$primaryActionIcon = 'bi-truck';
$primaryActionTarget = '#modal-transportadora';
$primaryActionPermission = 'transportadora.criar';
$requiredPermission = 'transportadora.visualizar';
$pageContent = __DIR__ . '/pages/operational.php';

require __DIR__ . '/includes/shell.php';
