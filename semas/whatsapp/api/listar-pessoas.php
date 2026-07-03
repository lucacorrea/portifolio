<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['GET', 'POST']);
wpe_require_permission('visualizar');

try {
    $input = wpe_input();
    $page = (int)($input['pagina'] ?? $input['page'] ?? 1);
    $perPage = (int)($input['por_pagina'] ?? $input['per_page'] ?? 20);
    $service = wpe_service($pdo);
    wpe_json(true, 'Pessoas listadas.', $service->listarPessoas($input, $page, $perPage));
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel listar as pessoas filtradas.', [], [], 500);
}
