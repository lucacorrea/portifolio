<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Casa de Acolhimento',
    'description' => 'Indicadores agregados de ocupação, atendimentos e organização da unidade de acolhimento.',
    'actions' => [['label' => 'Registrar atividade', 'icon' => 'calendar-plus', 'primary' => true]],
    'stats' => $stats([
        ['Ocupação atual', '68%', 'Indicador agregado', 'house-lock'],
        ['Acolhimentos ativos', '17', 'Sem identificação', 'people'],
        ['Planos acompanhados', '15', 'Revisão periódica', 'clipboard2-check'],
        ['Atividades no mês', '36', 'Coletivas e individuais', 'calendar-event'],
    ]),
    'blocks' => [
        ['type' => 'chart', 'title' => 'Movimentação mensal agregada', 'chart' => 'line', 'labels' => ['Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul'], 'values' => [14, 16, 15, 18, 17, 17]],
        ['type' => 'info', 'title' => 'Rotinas da unidade', 'items' => [
            ['icon' => 'calendar-check', 'title' => 'Planos revisados', 'text' => '12 revisões concluídas no período.'],
            ['icon' => 'heart-pulse', 'title' => 'Cuidados articulados', 'text' => 'Atendimentos de saúde e assistência monitorados.'],
            ['icon' => 'people', 'title' => 'Atividades de convivência', 'text' => 'Programação semanal ativa.'],
        ]],
    ],
]);
