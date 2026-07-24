<?php

declare(strict_types=1);

require __DIR__ . '/servico-action-common.php';

service_require_post_request();
[$application, $session] = service_action_context('servico.excluir');

try {
    $user = $application->authorization()->requireLogin();
    $application->serviceManagement()->deleteService(
        service_posted_positive_int('id'),
        $user->id()
    );
    $session->flash('success', 'Serviço excluído com o histórico preservado.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('Service soft deletion failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível excluir o serviço.');
}

service_redirect($application);
