<?php
$pageKey = 'orcamentos';
$activePage = 'orcamentos';
$pageTitle = 'Orçamentos';
$pageSubtitle = 'Gere orçamentos profissionais e envie para o cliente';
$primaryActionLabel = 'Novo Orçamento';
$primaryActionIcon = 'bi-file-earmark-plus';
$primaryActionHandler = "openEntityModal('orcamento')";
$pageContent = __DIR__ . '/pages/operational.php';
$pageScripts = ['assets/js/osmais-app.js'];
$includePdfVendor = true;

require __DIR__ . '/includes/shell.php';
