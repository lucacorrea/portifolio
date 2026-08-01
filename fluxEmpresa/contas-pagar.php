<?php

declare(strict_types=1);

$pageTitle = 'Contas a Pagar';
$pageSubtitle = 'Controle de compromissos com fornecedores';
$activePage = 'contas-pagar';

$primaryActionLabel = 'Nova conta';
$primaryActionIcon = 'bi-plus-square';
$primaryActionTarget = '#modal-conta-pagar';
$primaryActionPermission = 'contas_pagar.criar';

$requiredPermission = 'contas_pagar.visualizar';
$pageContent = __DIR__ . '/pages/contas-pagar.php';
$pageScripts = ['assets/js/contas-pagar.js'];

require __DIR__ . '/includes/shell.php';
