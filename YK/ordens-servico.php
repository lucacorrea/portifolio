<?php
declare(strict_types=1);

$activePage = 'ordens';
$pageTitle = 'Ordens de Serviço';
$pageSubtitle = 'Controle dos atendimentos técnicos';
$primaryActionLabel = 'Nova OS';
$primaryActionIcon = 'bi-plus-lg';
$primaryActionTarget = '#modal-os';
$primaryActionPermission = 'os.criar';
$requiredPermission = 'os.visualizar';
$pageContent = __DIR__ . '/pages/ordens-servico.php';

require __DIR__ . '/includes/shell.php';
