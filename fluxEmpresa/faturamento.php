<?php
$pageKey = 'faturamento';
$activePage = 'faturamento';
$pageTitle = 'Notas e Faturamento';
$pageSubtitle = 'Documentos fiscais, recibos e prontidão da integração';
$primaryActionLabel = 'Novo recibo';
$primaryActionIcon = 'bi-receipt-cutoff';
$primaryActionTarget = '#modal-standalone-receipt';
$primaryActionPermission = 'recibo.emitir';
$requiredAnyPermission = [
  'nota_fiscal.visualizar',
  'recibo.visualizar',
  'recibo.emitir',
  'boleto.visualizar',
];
$pageContent = __DIR__ . '/pages/faturamento.php';
$pageScripts = ['assets/js/faturamento.js'];

require __DIR__ . '/includes/shell.php';
