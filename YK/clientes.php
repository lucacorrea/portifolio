<?php

declare(strict_types=1);

$pageTitle = 'Clientes';
$pageSubtitle = 'Cadastro de clientes e contatos';
$activePage = 'clientes';

$primaryActionLabel = 'Novo cliente';
$primaryActionIcon = 'bi-person-plus';
$primaryActionTarget = '#modal-cliente';
$primaryActionPermission = 'cliente.criar';

$requiredPermission = 'cliente.visualizar';
$pageContent = __DIR__ . '/pages/clientes.php';

require __DIR__ . '/includes/shell.php';
