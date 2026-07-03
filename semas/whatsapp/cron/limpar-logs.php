<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso negado.');
}

$dias = max(7, (int)($argv[1] ?? 30));
$limite = time() - ($dias * 86400);
$dirs = [
    __DIR__ . '/../storage/logs',
    __DIR__ . '/../bridge/logs',
];

$removidos = 0;
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    foreach (glob($dir . '/*.log') ?: [] as $file) {
        if (is_file($file) && filemtime($file) < $limite) {
            unlink($file);
            $removidos++;
        }
    }
}

echo json_encode(['sucesso' => true, 'removidos' => $removidos], JSON_UNESCAPED_UNICODE) . PHP_EOL;
