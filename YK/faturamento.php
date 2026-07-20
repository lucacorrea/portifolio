<?php
$pageKey = 'faturamento';
$activePage = 'faturamento';
$pageTitle = 'Notas e Faturamento';
$pageSubtitle = 'Documentos fiscais, recibos e prontidão da integração';
$primaryActionLabel = 'Configuração Fiscal';
$primaryActionIcon = 'bi-shield-lock';
$primaryActionTarget = 'configuracoes-fiscais.php';
$primaryActionPermission = 'nota_fiscal.configurar';
$requiredAnyPermission = [
  'nota_fiscal.visualizar',
  'recibo.visualizar',
  'boleto.visualizar',
];
$pageContent = __DIR__ . '/pages/faturamento.php';

require __DIR__ . '/includes/shell.php';
