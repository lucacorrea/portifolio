<?php

declare(strict_types=1);

$pageTitle = 'Orçamentos';
$pageSubtitle = 'Criação e acompanhamento de propostas comerciais';
$activePage = 'orcamentos';

$primaryActionLabel = 'Novo orçamento';
$primaryActionIcon = 'bi-file-earmark-plus';
$primaryActionTarget = '#modal-orcamento';
$primaryActionPermission = 'orcamento.criar';

$requiredPermission = 'orcamento.visualizar';
$pageContent = __DIR__ . '/pages/orcamentos.php';

require __DIR__ . '/includes/shell.php';
