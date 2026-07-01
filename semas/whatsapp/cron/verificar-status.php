<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso negado.');
}

require_once __DIR__ . '/../WhatsappService.php';

$service = new WhatsappService();
$status = $service->verificarConexao();
echo json_encode($status, JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($status['sucesso'] ? 0 : 1);
