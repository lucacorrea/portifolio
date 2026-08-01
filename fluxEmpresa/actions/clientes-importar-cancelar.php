<?php

declare(strict_types=1);

require __DIR__ . '/cliente-action-common.php';

client_require_post_request();
[$application, $session] = client_action_context('cliente.importar');

try {
    $token = trim((string) ($_POST['import_token'] ?? ''));
    $application->clientImport()->discard($token, session_id());
    client_clear_import_preview();
    $session->flash('info', 'A análise da importação foi descartada.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Client PDF import discard failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível descartar a análise da importação.');
}

client_redirect($application, 'clientes.php');
