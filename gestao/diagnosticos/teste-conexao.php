<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);

require_once dirname(__DIR__) . '/config/conexao.php';

try {
    gestao_pdo()->query('SELECT 1');
    echo "Conexao PDO OK\n";
} catch (Throwable $e) {
    error_log('[gestao diagnostico] Falha na conexao PDO: ' . $e->getMessage());
    http_response_code(500);
    echo "Falha na conexao PDO. Veja o error_log da hospedagem.\n";
}
