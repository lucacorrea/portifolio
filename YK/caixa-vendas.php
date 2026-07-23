<?php
$pageKey = 'caixa-vendas';
$activePage = 'caixa-vendas';
$pageTitle = 'Vendas do Caixa';
$pageSubtitle = 'Histórico e estorno das vendas de peças';
$requiredPermission = 'venda_avulsa.visualizar';
$pageContent = __DIR__ . '/pages/caixa-vendas.php';
$pageScripts = ['assets/js/caixa-vendas.js'];

require __DIR__ . '/includes/shell.php';
