<?php

declare(strict_types=1);

$pageDefinition = [
    'title' => 'Documentação',
    'description' => 'Conferência visual dos documentos exigidos, entregues e pendentes.',
    'actions' => [['label' => 'Registrar conferência', 'icon' => 'folder-check', 'primary' => true]],
    'stats' => pe_demo_stats('documentacao'),
    'search_placeholder' => 'Pesquisar participante ou pendência',
    'filters' => [
        ['label' => 'Situação', 'options' => ['Regular', 'Pendente', 'Revisar']],
        ['label' => 'Pendências', 'options' => ['0', 'Comprovante escolar', '2 documentos']],
    ],
    'blocks' => [[
        'type' => 'table',
        'kicker' => 'Conferência documental',
        'title' => 'Situação dos participantes',
        'primary' => 'participante',
        'columns' => [
            ['key' => 'participante', 'label' => 'Participante'], ['key' => 'exigidos', 'label' => 'Documentos exigidos'],
            ['key' => 'entregues', 'label' => 'Documentos entregues'], ['key' => 'pendencias', 'label' => 'Pendências'],
            ['key' => 'validade', 'label' => 'Validade'], ['key' => 'situacao', 'label' => 'Situação'],
        ],
        'rows' => pe_demo_documents(),
    ]],
    'modal' => ['title' => 'Detalhes da documentação'],
];
