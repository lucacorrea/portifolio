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
    'table' => static fn (
        string $title,
        array $columns,
        array $rows,
        string $primary = 'referencia'
    ): array => [
        'type' => 'table',
        'title' => $title,
        'description' => 'Registros demonstrativos para validação da experiência visual.',
        'columns' => array_map(
            static fn (string $key, string $label): array => ['key' => $key, 'label' => $label],
            array_keys($columns),
            array_values($columns)
        ),
        'rows' => $rows,
        'primary' => $primary,
    ],
    'territories' => ['Centro', 'Zona Norte', 'Zona Sul', 'Zona Rural', 'Ribeirinha'],
    'service_statuses' => ['Em análise', 'Em acompanhamento', 'Concluído'],
];
