<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso negado.');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/EmpregoCentralService.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "Conexao indisponivel.\n");
    exit(1);
}

$service = new EmpregoCentralService($pdo);
$service->ensureSchema();

$limite = max(1, min(100, (int)($argv[1] ?? 10)));
$stmt = $pdo->query("SELECT id FROM whatsapp_semas_campanhas WHERE status IN ('em_envio','rascunho') ORDER BY id ASC LIMIT 20");
$campanhas = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

$resultado = [];
foreach ($campanhas as $campanhaId) {
    try {
        $resultado[(int)$campanhaId] = $service->processarFila((int)$campanhaId, $limite);
    } catch (Throwable $e) {
        $resultado[(int)$campanhaId] = ['erro' => $e->getMessage()];
    }
}

echo json_encode(['sucesso' => true, 'resultado' => $resultado], JSON_UNESCAPED_UNICODE) . PHP_EOL;
