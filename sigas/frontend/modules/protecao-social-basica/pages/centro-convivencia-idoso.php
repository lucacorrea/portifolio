<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
$table = $demo['table'];
return sigas_frontend_page([
    'title' => 'Centro de Convivência do Idoso',
    'description' => 'Gestão visual de grupos, oficinas, frequência e atividades de convivência.',
    'actions' => [['label' => 'Nova atividade', 'icon' => 'calendar-plus', 'primary' => true]],
    'stats' => $stats([
        ['Participantes ativos', '328', 'Grupos regulares', 'person-hearts'],
        ['Atividades no mês', '42', 'Oficinas e encontros', 'calendar-event'],
        ['Frequência média', '86%', 'Últimos 30 dias', 'calendar-check'],
        ['Novas acolhidas', '19', 'No mês', 'person-plus'],
    ]),
    'filters' => [['label' => 'Atividade', 'options' => ['Convivência', 'Artesanato', 'Movimento', 'Cultura']]],
    'blocks' => [
        $table('Programação de atividades', ['referencia' => 'Atividade', 'grupo' => 'Grupo', 'data' => 'Data', 'vagas' => 'Vagas'], [
            ['referencia' => 'Oficina de memória', 'grupo' => 'Convivência A', 'data' => '05/08/2026', 'vagas' => '24'],
            ['referencia' => 'Movimento e saúde', 'grupo' => 'Vida Ativa', 'data' => '06/08/2026', 'vagas' => '30'],
            ['referencia' => 'Artes e saberes', 'grupo' => 'Criatividade', 'data' => '08/08/2026', 'vagas' => '18'],
        ]),
        ['type' => 'chart', 'title' => 'Participação por atividade', 'chart' => 'bar', 'labels' => ['Convivência', 'Movimento', 'Artesanato', 'Cultura'], 'values' => [112, 86, 74, 56]],
    ],
]);
