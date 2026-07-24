<?php

declare(strict_types=1);

require __DIR__ . '/cliente-action-common.php';

const CLIENT_IMPORT_MAX_FILE_SIZE = 5 * 1024 * 1024;

client_require_post_request();
$contentLength = filter_var($_SERVER['CONTENT_LENGTH'] ?? null, FILTER_VALIDATE_INT);
if (is_int($contentLength) && $contentLength > 6 * 1024 * 1024) {
    http_response_code(413);
    exit('O arquivo enviado excede o limite permitido.');
}
[$application, $session] = client_action_context('cliente.importar');

try {
    $file = $_FILES['client_pdf'] ?? null;
    if (!is_array($file)) {
        throw new InvalidArgumentException('Selecione um arquivo PDF de até 5 MB.');
    }
    $error = filter_var($file['error'] ?? null, FILTER_VALIDATE_INT);
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'O PDF excede o limite configurado no servidor.',
            UPLOAD_ERR_FORM_SIZE => 'O PDF excede o limite de 5 MB.',
            UPLOAD_ERR_PARTIAL => 'O envio do PDF foi interrompido. Tente novamente.',
            UPLOAD_ERR_NO_FILE => 'Selecione um arquivo PDF.',
        ];
        throw new InvalidArgumentException($messages[$error] ?? 'Não foi possível receber o PDF.');
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $originalName = basename((string) ($file['name'] ?? ''));
    $size = is_file($temporaryPath) ? filesize($temporaryPath) : false;
    if (!is_int($size) || $size <= 0 || $size > CLIENT_IMPORT_MAX_FILE_SIZE) {
        throw new InvalidArgumentException('O PDF deve ter no máximo 5 MB.');
    }
    if (!is_uploaded_file($temporaryPath) || preg_match('/\.pdf$/i', $originalName) !== 1) {
        throw new InvalidArgumentException('Envie um arquivo PDF válido.');
    }
    $handle = fopen($temporaryPath, 'rb');
    $signature = is_resource($handle) ? fread($handle, 5) : false;
    if (is_resource($handle)) {
        fclose($handle);
    }
    if ($signature !== '%PDF-') {
        throw new InvalidArgumentException('O arquivo enviado não possui uma assinatura PDF válida.');
    }
    if (class_exists(finfo::class)) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (!in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
            throw new InvalidArgumentException('O tipo do arquivo enviado não é PDF.');
        }
    }

    $preview = $application->clientImport()->analyze(
        $temporaryPath,
        $originalName,
        session_id()
    );
    client_store_import_preview($preview);
    $session->flash('success', 'PDF analisado. Revise o resumo antes de confirmar a importação.');
    client_redirect($application, 'clientes.php?modal=import-preview');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Client PDF analysis failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível analisar o PDF de clientes.');
}

client_redirect($application, 'clientes.php?modal=import');
