<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
$table = $demo['table'];
return sigas_frontend_page([
    'title' => 'Criança Feliz',
    'description' => 'Organização de visitas domiciliares, famílias participantes e desenvolvimento na primeira infância.',
    'actions' => [['label' => 'Planejar visita', 'icon' => 'calendar-plus', 'primary' => true]],
    'stats' => $stats([
        ['Crianças acompanhadas', '486', 'Cadastros ativos', 'emoji-smile'],
        ['Visitas realizadas', '1.124', 'No mês', 'house-check'],
        ['Visitas programadas', '168', 'Próximos 7 dias', 'calendar3'],
        ['Cobertura da meta', '88%', 'Referência mensal', 'bullseye'],
    ]),
    'filters' => [
        ['label' => 'Situação', 'options' => ['Programada', 'Realizada', 'Reagendada']],
        ['label' => 'Território', 'options' => ['CRAS 1', 'CRAS 2', 'Rural']],
    ],
    'blocks' => [
        $table('Agenda de visitas', ['referencia' => 'Família', 'territorio' => 'Território', 'data' => 'Data', 'situacao' => 'Situação'], [
            ['referencia' => 'CF-0241', 'territorio' => 'CRAS 1', 'data' => '04/08/2026', 'situacao' => 'Programada'],
            ['referencia' => 'CF-0318', 'territorio' => 'CRAS 2', 'data' => '05/08/2026', 'situacao' => 'Reagendada'],
            ['referencia' => 'CF-0396', 'territorio' => 'Rural', 'data' => '06/08/2026', 'situacao' => 'Programada'],
        ]),
        ['type' => 'timeline', 'title' => 'Ciclo da visita', 'items' => [
            ['date' => '1', 'title' => 'Planejamento', 'text' => 'Definição do objetivo e atividade da visita.'],
            ['date' => '2', 'title' => 'Realização', 'text' => 'Acompanhamento orientado da família.'],
            ['date' => '3', 'title' => 'Registro', 'text' => 'Síntese e próximos passos do acompanhamento.'],
        ]],
    ],
]);
