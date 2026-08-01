<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
return sigas_frontend_page([
    'title' => 'Pessoa com deficiência',
    'description' => 'Acompanhamento agregado de proteção, acessibilidade e inclusão na rede de serviços.',
    'stats' => $stats([
        ['Acompanhamentos ativos', '43', 'Total agregado', 'universal-access'],
        ['Barreiras monitoradas', '37', 'No período', 'signpost-split'],
        ['Encaminhamentos', '29', 'Rede articulada', 'send'],
        ['Planos revisados', '84%', 'No prazo', 'clipboard2-check'],
    ]),
    'blocks' => [
        ['type' => 'chart', 'title' => 'Barreiras por categoria', 'chart' => 'bar', 'labels' => ['Acessibilidade', 'Comunicação', 'Transporte', 'Serviços', 'Atitudinal'], 'values' => [12, 6, 8, 5, 6]],
        ['type' => 'info', 'title' => 'Frentes de acompanhamento', 'items' => [
            ['icon' => 'universal-access', 'title' => 'Acessibilidade', 'text' => 'Mapeamento de barreiras e apoios necessários.'],
            ['icon' => 'people', 'title' => 'Convivência e participação', 'text' => 'Articulação com serviços e comunidade.'],
            ['icon' => 'briefcase', 'title' => 'Inclusão produtiva', 'text' => 'Encaminhamentos conforme perfil e interesse.'],
        ]],
    ],
]);
