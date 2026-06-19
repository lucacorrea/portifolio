<?php
$pageKey = 'relatorios';
$activePage = 'relatorios';
$pageTitle = 'Relatórios';
$pageSubtitle = 'Acompanhe indicadores operacionais e financeiros';
$primaryActionLabel = 'Exportar visual';
$primaryActionIcon = 'bi-download';
$primaryActionTarget = '#modal-relatorio';
$primaryActionPermission = 'relatorio.exportar';
$requiredAnyPermission = [
  'relatorio.operacional',
  'relatorio.financeiro',
  'relatorio.estoque',
  'relatorio.produtividade',
  'relatorio.funcionarios',
];
$pageContent = __DIR__ . '/pages/operational.php';

require __DIR__ . '/includes/shell.php';
