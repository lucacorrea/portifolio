<?php

declare(strict_types=1);

$pageTitle = 'Fornecedores';
$pageSubtitle = 'Cadastro de fornecedores e contatos';
$activePage = 'fornecedores';

$primaryActionLabel = 'Novo fornecedor';
$primaryActionIcon = 'bi-truck';
$primaryActionTarget = '#modal-fornecedor';
$primaryActionPermission = 'fornecedor.criar';

$requiredPermission = 'fornecedor.visualizar';
$pageContent = __DIR__ . '/pages/fornecedores.php';
$pageScripts = ['assets/js/fornecedores.js'];

require __DIR__ . '/includes/shell.php';
