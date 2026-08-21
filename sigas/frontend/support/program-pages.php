<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** @return array<string,mixed> */
function sigas_program_table(string $title, array $columns, array $rows, string $kicker = 'Operação'): array
{
    return [
        'type' => 'table',
        'kicker' => $kicker,
        'title' => $title,
        'description' => 'Acompanhamento organizado no padrão operacional do SIGAS.',
        'columns' => array_map(static fn (string $label, string $key): array => ['key' => $key, 'label' => $label], $columns, array_keys($columns)),
        'rows' => $rows,
        'primary' => array_key_first($columns),
    ];
}

/** @return array<string,mixed> */
function sigas_program_page(string $title, string $description, array $stats, array $blocks, array $actions = [], array $filters = []): array
{
    return sigas_frontend_page([
        'title' => $title,
        'description' => $description,
        'actions' => $actions,
        'stats' => $stats,
        'filters' => $filters,
        'blocks' => $blocks,
        'demo' => true,
        'show_states' => false,
    ]);
}

/** @return array{type:string,title:string,items:array<int,array<string,string>>} */
function sigas_program_flow(string $title, array $items): array
{
    return ['type' => 'timeline', 'kicker' => 'Fluxo', 'title' => $title, 'items' => $items];
}
