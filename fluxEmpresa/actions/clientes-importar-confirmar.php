<?php

declare(strict_types=1);

require __DIR__ . '/cliente-action-common.php';

client_require_post_request();
[$application, $session] = client_action_context('cliente.importar');

try {
    $token = trim((string) ($_POST['import_token'] ?? ''));
    $result = $application->clientImport()->confirm($token, session_id());
    client_clear_import_preview();

    $message = $result['imported'] . ' cliente(s) importado(s) com sucesso.';
    if ($result['skipped'] > 0) {
        $message .= ' ' . $result['skipped'] . ' já existente(s) foi(ram) ignorado(s).';
    }
    $session->flash('success', $message);
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
    client_redirect($application, 'clientes.php?modal=import-preview');
} catch (Throwable $exception) {
    error_log('Client PDF import failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível concluir a importação. Nenhum cliente foi gravado.');
    client_redirect($application, 'clientes.php?modal=import-preview');
}

client_redirect($application, 'clientes.php');
