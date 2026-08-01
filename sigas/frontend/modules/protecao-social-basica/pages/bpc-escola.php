<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
$table = $demo['table'];
return sigas_frontend_page([
    'title' => 'BPC na Escola',
    'description' => 'Monitoramento de acesso e permanência escolar e das barreiras identificadas.',
    'stats' => $stats([
        ['Pessoas acompanhadas', '214', 'Faixa escolar', 'mortarboard'],
        ['Questionários concluídos', '176', '82% da referência', 'clipboard2-check'],
        ['Barreiras em acompanhamento', '63', 'Rede intersetorial', 'exclamation-diamond'],
        ['Encaminhamentos ativos', '41', 'Educação, saúde e assistência', 'send'],
    ]),
    'filters' => [
        ['label' => 'Barreira', 'options' => ['Acessibilidade', 'Transporte', 'Saúde', 'Documentação']],
        ['label' => 'Situação', 'options' => $demo['service_statuses']],
    ],
    'blocks' => [
        $table('Barreiras e encaminhamentos', ['referencia' => 'Referência', 'barreira' => 'Barreira', 'rede' => 'Rede acionada', 'situacao' => 'Situação'], [
            ['referencia' => 'BPC-E-0082', 'barreira' => 'Transporte', 'rede' => 'Educação', 'situacao' => 'Em acompanhamento'],
            ['referencia' => 'BPC-E-0117', 'barreira' => 'Acessibilidade', 'rede' => 'Educação e Obras', 'situacao' => 'Em análise'],
            ['referencia' => 'BPC-E-0149', 'barreira' => 'Documentação', 'rede' => 'Assistência', 'situacao' => 'Concluído'],
        ]),
        ['type' => 'chart', 'title' => 'Barreiras identificadas', 'chart' => 'doughnut', 'labels' => ['Transporte', 'Acessibilidade', 'Saúde', 'Documentação'], 'values' => [28, 19, 10, 6]],
    ],
]);
