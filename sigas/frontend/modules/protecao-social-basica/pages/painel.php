<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Painel',
    'description' => 'Visão consolidada dos serviços, programas e unidades da Proteção Social Básica.',
    'actions' => [['label' => 'Atualizar painel', 'icon' => 'arrow-repeat', 'primary' => true]],
    'stats' => $stats([
        ['Famílias acompanhadas', '3.842', '+6,4% no trimestre', 'house-heart'],
        ['Atendimentos no mês', '1.286', 'Rede básica consolidada', 'clipboard2-pulse'],
        ['Encaminhamentos abertos', '174', '32 com prioridade', 'send'],
        ['Cobertura territorial', '92%', 'Urbana e rural', 'geo-alt'],
    ]),
    'blocks' => [
        ['type' => 'chart', 'title' => 'Atendimentos por unidade', 'chart' => 'bar', 'labels' => ['CRAS 1', 'CRAS 2', 'CAIC', 'Darquilana', 'Casa do Cidadão'], 'values' => [382, 341, 196, 224, 143]],
        ['type' => 'info', 'title' => 'Alertas da rede básica', 'items' => [
            ['icon' => 'inboxes', 'title' => 'Solicitações aguardando triagem', 'text' => '74 registros na fila integrada.', 'badge' => 'Atenção'],
            ['icon' => 'calendar-check', 'title' => 'Agenda territorial', 'text' => '12 ações coletivas previstas para os próximos 15 dias.'],
            ['icon' => 'check-circle', 'title' => 'Cadastros revisados', 'text' => '89% das revisões mensais concluídas.', 'badge' => 'Em dia'],
        ]],
        ['type' => 'timeline', 'title' => 'Próximas ações integradas', 'items' => [
            ['date' => '05 ago', 'title' => 'Reunião de referência dos CRAS', 'text' => 'Alinhamento dos fluxos de acolhida e encaminhamento.'],
            ['date' => '08 ago', 'title' => 'Ação comunitária rural', 'text' => 'Atendimento itinerante e atualização cadastral.'],
            ['date' => '12 ago', 'title' => 'Monitoramento mensal', 'text' => 'Consolidação dos indicadores das unidades.'],
        ]],
    ],
]);
