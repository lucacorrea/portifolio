<?php

declare(strict_types=1);

$pageTitle = 'Serviços';
$pageSubtitle = 'Cadastro dos serviços oferecidos pela empresa';
$activePage = 'servicos';

$primaryActionLabel = 'Novo serviço';
$primaryActionIcon = 'bi-tools';
$primaryActionTarget = '#modal-servico';
$primaryActionPermission = 'servico.criar';

$requiredPermission = 'servico.visualizar';
$pageContent = __DIR__ . '/pages/servicos.php';

require __DIR__ . '/includes/shell.php';
