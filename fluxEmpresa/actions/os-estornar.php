<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();
[$application, $session] = os_action_context('os.estornar');

try {
    $user = $application->authorization()->requireLogin();
    $application->serviceOrderLifecycle()->reverse(
        os_posted_positive_int('id'),
        (string) ($_POST['motivo'] ?? ''),
        $user->id()
    );
    $session->flash('success', 'OS estornada com compensação de estoque e financeiro.');
} catch (InvalidArgumentException $exception) {
    $session->flash('danger', $exception->getMessage());
} catch (Throwable $exception) {
    error_log('OS reversal failed: ' . $exception->getMessage());
    $session->flash('danger', 'Não foi possível estornar a OS.');
}

os_redirect_back($application);
