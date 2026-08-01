<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_planejamento_demo_data();

return sigas_frontend_page([
    'title' => 'Monitoramento',
    'description' => 'Compare execução, metas, alertas e pendências dos programas acompanhados pela gestão.',
    'actions' => [['label' => 'Registrar análise', 'icon' => 'journal-check'], ['label' => 'Atualizar ciclo', 'icon' => 'arrow-repeat', 'primary' => true]],
    'stats' => [
        ['label' => 'Programas monitorados', 'value' => '14', 'detail' => 'Ciclo trimestral', 'icon' => 'activity'],
        ['label' => 'Execução média', 'value' => '70%', 'detail' => '+4 p.p.', 'icon' => 'speedometer'],
        ['label' => 'Pendências abertas', 'value' => '9', 'detail' => '3 prioritárias', 'icon' => 'clipboard-x'],
        ['label' => 'Alertas ativos', 'value' => '4', 'detail' => 'Sem bloqueios críticos', 'icon' => 'bell'],
    ],
    'filters' => [
        ['label' => 'Programa', 'options' => ['PAIF', 'Serviço de Convivência', 'Acompanhamento Especializado']],
        ['label' => 'Situação', 'options' => ['Adequada', 'Atenção']],
        ['label' => 'Ciclo', 'options' => ['2º trimestre', '3º trimestre']],
    ],
    'blocks' => [
        ['type' => 'chart', 'title' => 'Comparativo entre setores', 'labels' => ['Gestão', 'Vigilância', 'Básica', 'Especial'], 'values' => [78, 66, 71, 63], 'chart' => 'bar'],
        ['type' => 'table', 'title' => 'Painel de execução', 'description' => 'Programas, metas e alertas consolidados.', 'columns' => [
            ['key' => 'programa', 'label' => 'Programa'], ['key' => 'execucao', 'label' => 'Execução'], ['key' => 'metas', 'label' => 'Metas'], ['key' => 'alertas', 'label' => 'Alertas'], ['key' => 'pendencias', 'label' => 'Pendências'], ['key' => 'evolucao', 'label' => 'Evolução'], ['key' => 'situacao', 'label' => 'Situação'],
        ], 'rows' => $data['monitoramento'], 'primary' => 'programa'],
        ['type' => 'info', 'title' => 'Alertas e providências', 'items' => [
            ['icon' => 'exclamation-triangle', 'title' => 'Capacidade do Serviço de Convivência', 'text' => 'Reavaliar metas e disponibilidade de turmas.', 'badge' => 'Atenção'],
            ['icon' => 'clock-history', 'title' => 'Evidências pendentes', 'text' => 'Três entregas aguardam documentação visual.', 'badge' => '3'],
        ]],
    ],
    'modal' => ['title' => 'Detalhes do monitoramento', 'fields' => ['programa', 'execucao', 'metas', 'alertas', 'pendencias', 'evolucao', 'situacao']],
]);
