<?php
$pageKey = 'relatorios';
$activePage = 'relatorios';
$pageTitle = 'Relatórios';
$pageSubtitle = 'Acompanhe produção, metas e prêmio estimado da equipe';
$primaryActionLabel = 'Configurar meta';
$primaryActionIcon = 'bi-bullseye';
$primaryActionTarget = '#modal-configurar-meta';
$primaryActionPermission = 'relatorio.meta_comissao.configurar';
$requiredAnyPermission = [
  'relatorio.operacional',
  'relatorio.financeiro',
  'relatorio.estoque',
  'relatorio.produtividade',
  'relatorio.funcionarios',
  'relatorio.comissao.visualizar',
  'relatorio.meta_comissao.configurar',
];
$pageContent = __DIR__ . '/pages/relatorios.php';

require __DIR__ . '/includes/shell.php';
