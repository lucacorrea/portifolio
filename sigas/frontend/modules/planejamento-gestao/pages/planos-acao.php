<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/data/demo-data.php';
$data = sigas_planejamento_demo_data();

return sigas_frontend_page([
    'title' => 'Planos de ação',
    'description' => 'Organize objetivos, responsáveis, etapas e execução dos instrumentos de planejamento.',
    'actions' => [
        ['label' => 'Exportar síntese', 'icon' => 'download'],
        ['label' => 'Novo plano', 'icon' => 'plus-lg', 'primary' => true],
    ],
    'stats' => [
        ['label' => 'Planos cadastrados', 'value' => '11', 'detail' => '8 em execução', 'icon' => 'journals'],
        ['label' => 'Execução média', 'value' => '67%', 'detail' => '+5 p.p. no trimestre', 'icon' => 'graph-up-arrow'],
        ['label' => 'Em atenção', 'value' => '2', 'detail' => 'Com prazo próximo', 'icon' => 'exclamation-diamond'],
        ['label' => 'Setores envolvidos', 'value' => '6', 'detail' => 'Atuação integrada', 'icon' => 'diagram-3'],
    ],
    'search_placeholder' => 'Pesquisar plano, setor ou responsável',
    'filters' => [
        ['label' => 'Setor', 'options' => ['Gestão', 'Proteção Básica', 'Vigilância']],
        ['label' => 'Ano', 'options' => ['2026', '2027', '2028']],
        ['label' => 'Situação', 'options' => ['Em execução', 'Atenção', 'Concluído']],
    ],
    'blocks' => [
        ['type' => 'table', 'title' => 'Carteira de planos', 'description' => 'Acompanhamento visual de prazo e percentual executado.', 'columns' => [
            ['key' => 'plano', 'label' => 'Plano'], ['key' => 'setor', 'label' => 'Setor'], ['key' => 'periodo', 'label' => 'Período'], ['key' => 'responsavel', 'label' => 'Responsável'], ['key' => 'metas', 'label' => 'Metas'], ['key' => 'execucao', 'label' => 'Execução'], ['key' => 'situacao', 'label' => 'Situação'], ['key' => 'prazo', 'label' => 'Prazo'],
        ], 'rows' => $data['planos'], 'primary' => 'plano'],
        ['type' => 'timeline', 'title' => 'Etapas do ciclo de planejamento', 'items' => [
            ['date' => 'ETAPA 1', 'title' => 'Diagnóstico e priorização', 'text' => 'Leitura de cenário, problemas e resultados esperados.'],
            ['date' => 'ETAPA 2', 'title' => 'Pactuação de metas', 'text' => 'Definição de responsáveis, indicadores e prazos.'],
            ['date' => 'ETAPA 3', 'title' => 'Execução e evidências', 'text' => 'Acompanhamento das entregas e documentos comprobatórios.'],
            ['date' => 'ETAPA 4', 'title' => 'Avaliação', 'text' => 'Análise dos resultados e revisão do plano.'],
        ]],
    ],
    'modal' => ['title' => 'Detalhes do plano de ação', 'fields' => ['plano', 'setor', 'periodo', 'responsavel', 'metas', 'execucao', 'situacao', 'prazo']],
]);
