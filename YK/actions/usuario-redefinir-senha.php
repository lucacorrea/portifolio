<?php

declare(strict_types=1);

require __DIR__ . '/usuario-action-common.php';

user_require_post_request();

[
    $application,
    $session,
] = user_action_context(
    'usuario.redefinir_senha'
);

try {
    $userId = user_posted_positive_int('id');

    $application
        ->userManagement()
        ->resetUserPassword(
            $userId,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirmation'] ?? ''),
            user_posted_bool('must_change_password', false)
        );

    $session->flash(
        'success',
        'Senha redefinida com sucesso.'
    );
} catch (InvalidArgumentException $exception) {
    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    error_log(
        'User password reset failed: '
        . $exception->getMessage()
    );

    $session->flash(
        'danger',
        'Não foi possível redefinir a senha do usuário.'
    );
}

user_redirect(
    $application,
    'usuarios.php'
);
