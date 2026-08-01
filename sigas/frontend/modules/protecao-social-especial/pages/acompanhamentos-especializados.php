<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/support/helpers.php';
$demo = require dirname(__DIR__) . '/data/demo-data.php';
$stats = $demo['stats'];
$table = $demo['protected_table'];
$refs = $demo['masked_references'];
return sigas_frontend_page([
    'title' => 'Acompanhamentos especializados',
    'description' => 'Fila técnica com referências mascaradas, prioridade e situação do acompanhamento.',
    'actions' => [['label' => 'Novo acompanhamento', 'icon' => 'plus-lg', 'primary' => true]],
    'stats' => $stats([
        ['Ativos', '286', 'Total protegido', 'clipboard2-pulse'],
        ['Prioridade imediata', '14', 'Acesso restrito', 'exclamation-triangle'],
        ['Revisões na semana', '38', 'Agenda técnica', 'calendar-check'],
        ['Planos atualizados', '82%', 'No prazo', 'check-circle'],
    ]),
    'filters' => $demo['protected_filters'],
    'search_placeholder' => 'Pesquisar somente por referência mascarada',
    'blocks' => [
        $table('Fila protegida de acompanhamento', ['referencia' => 'Referência', 'eixo' => 'Eixo', 'prioridade' => 'Prioridade', 'situacao' => 'Situação'], [
            ['referencia' => $refs[0], 'eixo' => 'Família', 'prioridade' => 'Alta', 'situacao' => 'Em acompanhamento'],
            ['referencia' => $refs[1], 'eixo' => 'Infância', 'prioridade' => 'Imediata', 'situacao' => 'Em avaliação'],
            ['referencia' => $refs[2], 'eixo' => 'Pessoa idosa', 'prioridade' => 'Regular', 'situacao' => 'Encaminhado'],
        ]),
        ['type' => 'timeline', 'title' => 'Fluxo técnico protegido', 'items' => [
            ['date' => '1', 'title' => 'Avaliação inicial', 'text' => 'Registro restrito da demanda e do nível de prioridade.'],
            ['date' => '2', 'title' => 'Plano de acompanhamento', 'text' => 'Definição técnica de ações e responsáveis.'],
            ['date' => '3', 'title' => 'Revisão periódica', 'text' => 'Monitoramento de resultados e próximos passos.'],
        ]],
    ],
    'modal' => ['title' => 'Resumo protegido do acompanhamento', 'fields' => []],
]);
