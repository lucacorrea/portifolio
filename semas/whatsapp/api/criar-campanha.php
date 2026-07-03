<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['POST']);
wpe_require_permission('criar_campanha');

$input = wpe_input();
wpe_require_csrf($input);

try {
    $service = wpe_service($pdo);
    $result = $service->criarCampanha($input);
    wpe_json(true, 'Campanha criada e fila preparada.', $result, [], 201);
} catch (RuntimeException $e) {
    wpe_json(false, $e->getMessage(), [], [], 422);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel criar a campanha.', [], [], 500);
}
