<?php

declare(strict_types=1);

$pageKey = 'relatorios';
$activePage = 'relatorios';

$pageTitle = 'Relatórios';
$pageSubtitle = 'Visão geral, clientes, serviços, produção, metas e comissão';

/*
 * A ação principal continua vinculada à configuração da meta mensal.
 * O shell controla a exibição conforme a permissão abaixo.
 */
$primaryActionLabel = 'Configurar meta';
$primaryActionIcon = 'bi-bullseye';
$primaryActionTarget = '#modal-configurar-meta';
$primaryActionPermission = 'relatorio.meta_comissao.configurar';

/*
 * Permite acessar a página quando o usuário possuir pelo menos
 * uma permissão compatível com alguma seção do relatório.
 *
 * As permissões específicas de cada seção serão verificadas
 * novamente na view e nos endpoints assíncronos.
 */
$requiredAnyPermission = [
    'relatorio.operacional',
    'relatorio.financeiro',
    'relatorio.estoque',
    'relatorio.produtividade',
    'relatorio.funcionarios',
    'relatorio.comissao.visualizar',
    'relatorio.meta_comissao.configurar',
];

/*
 * JavaScript exclusivo da página.
 *
 * Responsável pelo carregamento sob demanda das seções:
 * - clientes;
 * - serviços;
 * - equipe, metas e comissão.
 */
$pageScripts = [
    'assets/js/pages/relatorios.js',
];

$pageContent = __DIR__ . '/pages/relatorios.php';

require __DIR__ . '/includes/shell.php';