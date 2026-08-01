<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Centro Integrado Darquilana Amorim',
    'description' => 'Acompanhamento dos serviços integrados, agenda comunitária e articulação territorial.',
    'actions' => [['label' => 'Agendar atividade', 'icon' => 'calendar-plus', 'primary' => true]],
    'stats' => $stats([
        ['Atendimentos integrados', '684', 'No mês', 'clipboard2-pulse'],
        ['Famílias referenciadas', '512', 'Território de cobertura', 'house-heart'],
        ['Atividades coletivas', '31', 'Agenda atual', 'calendar-event'],
        ['Parcerias ativas', '12', 'Rede local', 'diagram-3'],
    ]),
    'blocks' => [
        ['type' => 'timeline', 'title' => 'Agenda comunitária', 'items' => [
            ['date' => '04 ago', 'title' => 'Acolhida coletiva', 'text' => 'Apresentação dos serviços e orientações gerais.'],
            ['date' => '07 ago', 'title' => 'Oficina para famílias', 'text' => 'Fortalecimento de vínculos e acesso a direitos.'],
            ['date' => '11 ago', 'title' => 'Rede no território', 'text' => 'Reunião de articulação intersetorial.'],
        ]],
        ['type' => 'info', 'title' => 'Serviços integrados', 'items' => [
            ['icon' => 'people', 'title' => 'Acolhida social', 'text' => 'Escuta inicial e orientação de acesso.'],
            ['icon' => 'person-workspace', 'title' => 'Atendimento técnico', 'text' => 'Acompanhamento conforme demanda.'],
            ['icon' => 'geo-alt', 'title' => 'Ação territorial', 'text' => 'Atividades próximas às comunidades.'],
        ]],
    ],
]);
