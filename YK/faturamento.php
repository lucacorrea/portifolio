<?php
$pageKey = 'faturamento';
$activePage = 'faturamento';
$pageTitle = 'Notas e Faturamento';
$pageSubtitle = 'Controle visual de notas, valores e status financeiro';
$primaryActionLabel = 'Novo Recibo';
$primaryActionIcon = 'bi-receipt-cutoff';
$primaryActionTarget = '#modal-recibo';
$primaryActionPermission = 'recibo.emitir';
$requiredAnyPermission = [
  'nota_fiscal.visualizar',
  'recibo.visualizar',
  'boleto.visualizar',
];
$pageContent = __DIR__ . '/pages/operational.php';

require __DIR__ . '/includes/shell.php';
