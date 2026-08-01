<?php

declare(strict_types=1);

require __DIR__ . '/usuario-action-common.php';

user_require_post_request();

$operation = trim(
    (string) ($_POST['status'] ?? '')
);

$permissions = [
    'ativo' => 'usuario.ativar',
    'desbloquear' => 'usuario.ativar',
    'inativo' => 'usuario.desativar',
    'bloqueado' => 'usuario.bloquear',
];

if (!isset($permissions[$operation])) {
    [
        $application,
        $session,
    ] = user_action_context('usuario.visualizar');

    $session->flash(
        'danger',
        'Operação de status inválida.'
    );

    user_redirect(
        $application,
        'usuarios.php'
    );
}

[
    $application,
    $session,
    $currentUser,
] = user_action_context(
    $permissions[$operation]
);

try {
    $userId = user_posted_positive_int('id');

    if (
        $currentUser->id() === $userId
        && in_array($operation, ['inativo', 'bloqueado'], true)
    ) {
        throw new InvalidArgumentException(
            'Você não pode desativar ou bloquear a própria conta.'
        );
    }

    $service = $application->userManagement();

    if ($operation === 'desbloquear') {
        $service->unlockUser($userId);

        $session->flash(
            'success',
            'Bloqueio temporário removido com sucesso.'
        );
    } else {
        $service->changeUserStatus(
            $userId,
            $operation
        );

        $session->flash(
            'success',
            'Status do usuário atualizado com sucesso.'
        );
    }
} catch (InvalidArgumentException $exception) {
    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    error_log(
        'User status change failed: '
        . $exception->getMessage()
    );

    $session->flash(
        'danger',
        'Não foi possível alterar o status do usuário.'
    );
}

user_redirect(
    $application,
    'usuarios.php'
);
