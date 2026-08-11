<?php

declare(strict_types=1);

require __DIR__ . '/os-action-common.php';

os_require_post_request();

[$application, $session] = os_action_context(
    'os.excluir'
);

$orderId = 0;
$reason = '';

try {
    $user = $application
        ->authorization()
        ->requireLogin();

    $orderId = os_posted_positive_int('id');

    $reason = trim(
        (string) ($_POST['motivo'] ?? '')
    );

    $application
        ->serviceOrderLifecycle()
        ->softDelete(
            $orderId,
            $reason,
            $user->id()
        );

    $session->flash(
        'success',
        'OS excluída da operação. O histórico e a auditoria foram preservados.'
    );

    os_redirect_back($application);
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery(
        'delete',
        [
            'id' => $orderId,
            'motivo' => $reason,
        ],
        $exception->getMessage()
    );

    $session->flash(
        'danger',
        $exception->getMessage()
    );

    os_redirect_back(
        $application,
        'ordens-servico.php',
        [
            'modal' => 'delete',
        ]
    );
} catch (Throwable $exception) {
    error_log(
        'OS soft deletion failed: '
        . $exception->getMessage()
    );

    $message =
        'Não foi possível excluir a OS. Nenhuma alteração parcial foi mantida.';

    os_store_form_recovery(
        'delete',
        [
            'id' => $orderId,
            'motivo' => $reason,
        ],
        $message
    );

    $session->flash(
        'danger',
        $message
    );

    os_redirect_back(
        $application,
        'ordens-servico.php',
        [
            'modal' => 'delete',
        ]
    );
}