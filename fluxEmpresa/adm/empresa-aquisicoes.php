<?php

declare(strict_types=1);

require __DIR__ . '/includes/admin-guard.php';
$pageTitle = 'Aquisições do SO';
$pageSubtitle = 'Consulta administrativa por empresa';
$activePage = 'companies';
require __DIR__ . '/pages/empresa-aquisicoes.php';
require __DIR__ . '/includes/shell.php';
