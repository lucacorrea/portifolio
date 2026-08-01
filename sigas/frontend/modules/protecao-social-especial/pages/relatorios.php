<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Relatórios',
    'description' => 'Relatórios exclusivamente consolidados para gestão da Proteção Social Especial.',
    'actions' => [['label' => 'Gerar relatório visual', 'icon' => 'file-earmark-bar-graph', 'primary' => true]],
    'stats' => $stats([
        ['Relatórios disponíveis', '12', 'Modelos agregados', 'file-earmark-bar-graph'],
        ['Atualizados no mês', '8', 'Referência atual', 'arrow-repeat'],
        ['Indicadores monitorados', '34', 'Sem dados pessoais', 'graph-up'],
        ['Exportações demonstrativas', '21', 'No mês', 'download'],
    ]),
    'blocks' => [
        ['type' => 'info', 'title' => 'Relatórios gerenciais', 'items' => [
            ['icon' => 'bar-chart', 'title' => 'Atendimentos consolidados', 'text' => 'Totais por período, eixo e território.', 'badge' => 'Agregado'],
            ['icon' => 'diagram-3', 'title' => 'Articulação da rede', 'text' => 'Encaminhamentos por destino e situação.'],
            ['icon' => 'clock-history', 'title' => 'Acompanhamentos', 'text' => 'Indicadores de prazo e revisão técnica.'],
            ['icon' => 'shield-check', 'title' => 'Proteção de dados', 'text' => 'Exportações não incluem nomes ou narrativas sensíveis.', 'badge' => 'Protegido'],
        ]],
        ['type' => 'settings', 'title' => 'Parâmetros do relatório', 'fields' => [
            ['label' => 'Período', 'value' => 'Julho de 2026'],
            ['label' => 'Abrangência', 'value' => 'Todos os territórios'],
            ['label' => 'Formato', 'value' => 'Resumo gerencial'],
        ]],
    ],
]);
