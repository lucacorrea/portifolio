<?php

declare(strict_types=1);

function sigas_frontend_escape(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array<string, mixed> $definition */
function sigas_frontend_page(array $definition): array
{
    return array_replace_recursive([
        'title' => 'Página',
        'description' => 'Ambiente visual do SIGAS.',
        'actions' => [],
        'stats' => [],
        'filters' => [],
        'blocks' => [],
        'states' => ['loading', 'empty', 'error', 'success', 'no-results', 'blocked', 'maintenance'],
        'modal' => ['title' => 'Detalhes', 'fields' => []],
        'demo' => true,
        'show_states' => true,
    ], $definition);
}

function sigas_frontend_asset(string $path): string
{
    $absolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $version = is_file($absolute) ? (string) filemtime($absolute) : '1';
    return sigas_frontend_escape($path . '?v=' . $version);
}
