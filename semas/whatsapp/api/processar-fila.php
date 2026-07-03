<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../services/EmpregoCentralService.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        fwrite(STDERR, "Conexao indisponivel.\n");
        exit(1);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $campanhaId = (int)($argv[1] ?? 0);
    $limite = (int)($argv[2] ?? 10);
    if ($campanhaId <= 0) {
        fwrite(STDERR, "Uso: php processarFila.php CAMPANHA_ID [LIMITE]\n");
        exit(1);
    }
    $service = new EmpregoCentralService($pdo);
    $service->ensureSchema();
    echo json_encode($service->processarFila($campanhaId, $limite), JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit;
}

require_once __DIR__ . '/_bootstrap.php';

wpe_require_method(['POST']);
wpe_require_permission('enviar_campanha');

$input = wpe_input();
wpe_require_csrf($input);

try {
    $campanhaId = (int)($input['campanha_id'] ?? 0);
    if ($campanhaId <= 0) {
        wpe_json(false, 'Campanha obrigatoria.', [], [], 422);
    }
    $limite = (int)($input['limite'] ?? 10);
    $service = wpe_service($pdo);
    wpe_json(true, 'Lote processado.', $service->processarFila($campanhaId, $limite));
} catch (RuntimeException $e) {
    wpe_json(false, $e->getMessage(), [], [], 409);
} catch (Throwable $e) {
    wpe_json(false, 'Nao foi possivel processar a fila.', [], [], 500);
}
