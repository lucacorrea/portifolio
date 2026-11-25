<?php
/**
 * OBRAS360 — download protegido de anexos.
 * Distingue anexo ausente no banco, arquivo físico ausente e falha interna.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/data.php';

try {
    require_login();
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($id) || $id < 1) throw new HttpError('Identificador do anexo inválido.', 404);

    $st = db()->prepare('SELECT * FROM attachments WHERE id=? AND deleted_at IS NULL LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) throw new HttpError('Anexo não encontrado no banco de dados.', 404);
    if (!record_row((string)$row['module'], (string)$row['record_code'])) {
        throw new HttpError('O registro associado a este anexo não está disponível.', 404);
    }

    $stored = (string)$row['stored_name'];
    if (!preg_match('/^[a-f0-9]{40}\.(?:pdf|jpg|jpeg|png|doc|docx)$/iD', $stored)) {
        error_log('[OBRAS360 DOWNLOAD] Nome armazenado inválido, id=' . $id);
        throw new HttpError('Anexo com referência inválida. Solicite verificação ao suporte.', 404);
    }

    $found = false;
    $missingIn = [];
    foreach (obras_upload_dirs() as $candidate) {
        $dir = realpath($candidate);
        if ($dir === false || !is_dir($dir)) continue;
        $path = realpath($dir . DIRECTORY_SEPARATOR . $stored);
        if (!$path || !str_starts_with($path, $dir . DIRECTORY_SEPARATOR) || !is_file($path)) continue;
        $found = $path;
        break;
    }
    if ($found === false) {
        error_log('[OBRAS360 DOWNLOAD] Arquivo físico ausente, id=' . $id . ', módulo=' . $row['module'] . ', código=' . $row['record_code']);
        throw new HttpError('O anexo consta no banco, mas o arquivo não foi localizado na hospedagem. O suporte precisa verificar o armazenamento ou restaurar o arquivo do backup.', 404);
    }
    if (!is_readable($found)) {
        error_log('[OBRAS360 DOWNLOAD] Sem permissão de leitura no anexo, id=' . $id);
        throw new HttpError('O servidor não possui permissão para ler este anexo. Informe ao suporte.', 503);
    }

    $handle = @fopen($found, 'rb');
    if ($handle === false) {
        error_log('[OBRAS360 DOWNLOAD] Não foi possível abrir o arquivo físico, id=' . $id);
        throw new HttpError('Não foi possível abrir o anexo no servidor. Informe ao suporte.', 503);
    }
    $size = @filesize($found);
    if ($size === false) {
        fclose($handle);
        throw new HttpError('Não foi possível obter o tamanho do anexo. Informe ao suporte.', 503);
    }

    // Evita a expressão regular malformada da versão anterior e protege os cabeçalhos HTTP.
    $name = trim(str_replace(["\r", "\n", '"', "\\", '/'], '_', (string)$row['original_name']));
    if ($name === '') $name = 'anexo-' . $id;
    $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
    if (!is_string($ascii) || $ascii === '') $ascii = 'anexo-' . $id;

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('Content-Length: ' . $size);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    fpassthru($handle);
    fclose($handle);
    exit;
} catch (HttpError $e) {
    http_response_code($e->httpCode);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
} catch (Throwable $e) {
    $ref = strtoupper(bin2hex(random_bytes(4)));
    error_log('[OBRAS360 DOWNLOAD] ref=' . $ref . ' erro=' . get_class($e) . ' detalhe=' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Falha técnica ao recuperar o anexo. Informe ao suporte a referência ' . $ref . '.';
}
