<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Violações de direitos',
    'description' => 'Monitoramento exclusivamente agregado por tipologia, território e situação de resposta.',
    'stats' => $stats([
        ['Registros no período', '132', 'Total agregado', 'exclamation-octagon'],
        ['Em avaliação', '29', 'Sem dados pessoais', 'search'],
        ['Com rede acionada', '84', 'Resposta intersetorial', 'diagram-3'],
        ['Reavaliados no prazo', '91%', 'Indicador consolidado', 'check-circle'],
    ]),
    'filters' => [
        ['label' => 'Território', 'options' => ['Urbano', 'Rural', 'Ribeirinho']],
        ['label' => 'Situação', 'options' => ['Em avaliação', 'Rede acionada', 'Acompanhando']],
    ],
    'blocks' => [
        ['type' => 'chart', 'title' => 'Distribuição por tipologia agregada', 'chart' => 'doughnut', 'labels' => ['Negligência', 'Violência física', 'Violência psicológica', 'Patrimonial', 'Outras'], 'values' => [38, 27, 31, 14, 22]],
        ['type' => 'chart', 'title' => 'Resposta por território', 'chart' => 'bar', 'labels' => ['Urbano', 'Rural', 'Ribeirinho'], 'values' => [79, 34, 19]],
        ['type' => 'info', 'title' => 'Proteção da informação', 'items' => [
            ['icon' => 'eye-slash', 'title' => 'Sem relatos na visão geral', 'text' => 'Narrativas e identificadores pessoais não aparecem nesta página.', 'badge' => 'Privacidade'],
            ['icon' => 'shield-check', 'title' => 'Acesso por autorização', 'text' => 'Consulta detalhada deve permanecer protegida no servidor.'],
        ]],
    ],
]);
