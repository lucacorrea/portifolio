<?php

declare(strict_types=1);

$pageTitle = 'Funcionários';
$pageSubtitle = 'Cadastro e identificação dos funcionários';
$activePage = 'funcionarios';

$primaryActionLabel = 'Novo funcionário';
$primaryActionIcon = 'bi-person-plus';
$primaryActionTarget = '#modal-funcionario';
$primaryActionPermission = 'funcionario.criar';

$requiredPermission = 'funcionario.visualizar';
$pageContent = __DIR__ . '/pages/funcionarios.php';

require __DIR__ . '/includes/shell.php';
