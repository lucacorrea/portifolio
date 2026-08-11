<?php

declare(strict_types=1);

require __DIR__
    . '/painel-semanal-action-common.php';

os_require_post_request();

[$application, $session] =
    os_action_context(
        'painel_semanal.adicionar'
    );

$redirectTarget =
    painel_semanal_return_target();

try {
    $user = $application
        ->authorization()
        ->requireLogin();

    $planning = $application
        ->weeklyServicePlanning()
        ->create(
            $_POST,
            $user->id()
        );

    $session->flash(
        'success',
        $planning['code']
        . ' cadastrado e aguardando confirmação. '
        . 'Nenhuma Ordem de Serviço foi criada.'
    );
} catch (InvalidArgumentException $exception) {
    os_store_form_recovery(
        'create',
        $_POST,
        $exception->getMessage()
    );

    $redirectTarget =
        painel_semanal_return_target(
            'create'
        );

    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    error_log(
        'Weekly service planning create failed: '
        . $exception->getMessage()
    );

    $message =
        'Não foi possível cadastrar o serviço semanal.';

    os_store_form_recovery(
        'create',
        $_POST,
        $message
    );

    $redirectTarget =
        painel_semanal_return_target(
            'create'
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