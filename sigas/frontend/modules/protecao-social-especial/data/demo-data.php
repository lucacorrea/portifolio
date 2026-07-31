<?php

declare(strict_types=1);

return [
    'stats' => static fn (array $items): array => array_map(
        static fn (array $item): array => [
            'label' => $item[0],
            'value' => $item[1],
            'detail' => $item[2],
            'icon' => $item[3],
        ],
        $items
    ),
    'protected_table' => static fn (
        string $title,
        array $columns,
        array $rows,
        string $primary = 'referencia'
    ): array => [
        'type' => 'table',
        'title' => $title,
        'description' => 'Dados demonstrativos, agregados ou identificados por referências mascaradas.',
        'columns' => array_map(
            static fn (string $key, string $label): array => ['key' => $key, 'label' => $label],
            array_keys($columns),
            array_values($columns)
        ),
        'rows' => $rows,
        'primary' => $primary,
    ],
    'protected_filters' => [
        ['label' => 'Prioridade', 'options' => ['Imediata', 'Alta', 'Regular']],
        ['label' => 'Situação', 'options' => ['Em avaliação', 'Em acompanhamento', 'Encaminhado']],
    ],
    'masked_references' => ['PSE-***-042', 'PSE-***-117', 'PSE-***-208'],
];
