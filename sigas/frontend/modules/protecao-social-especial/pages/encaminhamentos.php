<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
$table = $demo['protected_table'];
return sigas_frontend_page([
    'title' => 'Encaminhamentos',
    'description' => 'Fila protegida de articulações externas, usando apenas referências mascaradas.',
    'actions' => [['label' => 'Novo encaminhamento', 'icon' => 'send-plus', 'primary' => true]],
    'stats' => $stats([
        ['Abertos', '93', 'Total agregado', 'send'],
        ['Prioridade alta', '21', 'Sem identificação', 'exclamation-triangle'],
        ['Retornos pendentes', '34', 'Acompanhar rede', 'clock-history'],
        ['Concluídos no mês', '76', 'Consolidado', 'check-circle'],
    ]),
    'filters' => [
        ['label' => 'Destino', 'options' => ['Saúde', 'Justiça', 'Segurança', 'Educação', 'Acolhimento']],
        ['label' => 'Situação', 'options' => ['Enviado', 'Com retorno', 'Concluído']],
    ],
    'search_placeholder' => 'Pesquisar somente por referência mascarada',
    'blocks' => [
        $table('Fila de encaminhamentos', ['referencia' => 'Referência', 'destino' => 'Destino', 'data' => 'Envio', 'situacao' => 'Situação'], [
            ['referencia' => 'ENC-***-071', 'destino' => 'Saúde', 'data' => '01/08/2026', 'situacao' => 'Com retorno'],
            ['referencia' => 'ENC-***-084', 'destino' => 'Justiça', 'data' => '02/08/2026', 'situacao' => 'Enviado'],
            ['referencia' => 'ENC-***-096', 'destino' => 'Acolhimento', 'data' => '03/08/2026', 'situacao' => 'Concluído'],
        ]),
        ['type' => 'chart', 'title' => 'Encaminhamentos por destino', 'chart' => 'doughnut', 'labels' => ['Saúde', 'Justiça', 'Segurança', 'Educação', 'Acolhimento'], 'values' => [29, 18, 14, 17, 15]],
    ],
    'modal' => ['title' => 'Resumo protegido do encaminhamento', 'fields' => []],
]);
