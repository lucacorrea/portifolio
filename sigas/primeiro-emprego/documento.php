<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Storage;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/frontend/modules/primeiro-emprego/lib/access.php';

if (!pe_can('primeiro_emprego.visualizar')) {
    http_response_code(403);
    exit('Acesso negado.');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    http_response_code(400);
    exit('Documento inválido.');
}

$pdo = Database::connection();
$stmt = $pdo->prepare('SELECT arquivo_path,nome_original,mime_type,size_bytes FROM pe_documentos WHERE id=:id LIMIT 1');
$stmt->execute(['id' => $id]);
$doc = $stmt->fetch();
if (!$doc || empty($doc['arquivo_path'])) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

try {
    $path = Storage::resolveDocumentPath((string) $doc['arquivo_path']);
} catch (Throwable) {
    http_response_code(404);
    exit('Arquivo indisponível.');
}
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Arquivo indisponível.');
}

$name = basename((string) ($doc['nome_original'] ?: 'documento'));
$name = preg_replace('/[^\pL\pN._ -]+/u', '_', $name) ?: 'documento';
header('Content-Type: ' . ((string) ($doc['mime_type'] ?: 'application/octet-stream')));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($path);
exit;
