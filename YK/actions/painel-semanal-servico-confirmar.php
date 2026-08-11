<?php

declare(strict_types=1);

require __DIR__
    . '/painel-semanal-action-common.php';

os_require_post_request();

[$application, $session] =
    os_action_context(
        'os.criar'
    );

$redirectTarget =
    painel_semanal_return_target();

$planningId = 0;

try {
    $user = $application
        ->authorization()
        ->requireLogin();

    $planningId =
        os_posted_positive_int('id');

    $result = $application
        ->weeklyServicePlanning()
        ->confirm(
            $planningId,
            $user->id()
        );

    $session->flash(
        'success',
        $result['planning_code']
        . ' confirmado e convertido na '
        . $result['order_number']
        . '.'
    );
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery(
        'confirm',
        [
            'id' => $planningId,
        ],
        $exception->getMessage()
    );

    $redirectTarget =
        painel_semanal_return_target(
            'confirm'
        );

    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    error_log(
        'Weekly service confirmation failed: '
        . $exception->getMessage()
    );

    $message =
        'Não foi possível confirmar o serviço e gerar a OS. Nenhuma alteração parcial foi mantida.';

    os_store_form_recovery(
        'confirm',
        [
            'id' => $planningId,
        ],
        $message
    );

    $redirectTarget =
        painel_semanal_return_target(
            'confirm'
        );

    $session->flash(
        'danger',
        $message
    );
}

painel_semanal_redirect(
    $application,
    $redirectTarget
);