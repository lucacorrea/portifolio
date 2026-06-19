<?php
$pageKey = 'orcamentos';
$activePage = 'orcamentos';
$pageTitle = 'Orçamentos';
$pageSubtitle = 'Gere orçamentos profissionais e envie para o cliente';
$primaryActionLabel = 'Novo Orçamento';
$primaryActionIcon = 'bi-file-earmark-plus';
$primaryActionTarget = '#modal-orcamento';
$primaryActionPermission = 'orcamento.criar';
$requiredPermission = 'orcamento.visualizar';
$pageContent = __DIR__ . '/pages/operational.php';

require __DIR__ . '/includes/shell.php';
