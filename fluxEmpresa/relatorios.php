<?php

declare(strict_types=1);

$pageKey = 'relatorios';
$activePage = 'relatorios';
$pageTitle = 'Relatórios';
$pageSubtitle = 'Visão geral, clientes, serviços, produção, metas e comissão';

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

$pageScripts = [
    'assets/js/pages/relatorios.js',
    'assets/js/pages/relatorio-impressao.js',
];

$pageContent = __DIR__ . '/pages/relatorios.php';

require __DIR__ . '/includes/shell.php';