<?php

declare(strict_types=1);

require __DIR__ . '/cliente-action-common.php';

client_require_post_request();
[$application, $session] = client_action_context('cliente.desativar');

try {
    $clientId = client_posted_positive_int('id');
    $status = (string) ($_POST['status'] ?? '');
    if (!in_array($status, ['ativo', 'inativo'], true)) {
        throw new InvalidArgumentException('Status inválido.');
    }
    $application->clientManagement()->changeClientStatus($clientId, $status);
    $session->flash('success', $status === 'ativo' ? 'Cliente ativado com sucesso.' : 'Cliente desativado com sucesso.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Client status failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível alterar o status do cliente.');
}

client_redirect($application, 'clientes.php');
