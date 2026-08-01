<?php

declare(strict_types=1);

require __DIR__ . '/cliente-action-common.php';

client_require_post_request();
[$application, $session] = client_action_context('cliente.excluir');

try {
    $user = $application->authorization()->requireLogin();
    $application->clientManagement()->deleteClient(
        client_posted_positive_int('id'),
        $user->id()
    );
    $session->flash('success', 'Cliente excluído com o histórico preservado.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Client soft deletion failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível excluir o cliente.');
}

client_redirect($application);
