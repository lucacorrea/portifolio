<?php

declare(strict_types=1);

use App\Access\DTO\UserFormData;

/*
 * Carrega as funções comuns:
 * sessão, autenticação, CSRF, permissões e redirect.
 */
require __DIR__ . '/usuario-action-common.php';

user_require_post_request();

/*
 * ID vazio representa cadastro.
 * ID preenchido representa edição.
 */
$rawUserId = trim(
    (string) ($_POST['id'] ?? '')
);

$isEditing = $rawUserId !== '';

$requiredPermission = $isEditing
    ? 'usuario.editar'
    : 'usuario.criar';

[
    $application,
    $session,
    $currentUser,
] = user_action_context(
    $requiredPermission
);

try {
    $userId = null;

    if ($isEditing) {
        $userId = user_posted_positive_int('id');
    }

    $data = UserFormData::fromArray(
        [
            'profile_id' => $_POST['profile_id'] ?? null,
            'name' => $_POST['name'] ?? '',
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'phone' => $_POST['phone'] ?? null,
            'status' => $_POST['status'] ?? 'ativo',
            'must_change_password' =>
                $_POST['must_change_password'] ?? false,
            'password' => $_POST['password'] ?? '',
            'password_confirmation' =>
                $_POST['password_confirmation'] ?? '',
        ],
        /*
         * No cadastro a senha é obrigatória.
         * Na edição, a senha fica opcional.
         */
        passwordRequired: !$isEditing
    );

    $service = $application->userManagement();

    if ($userId !== null) {
        /*
         * Impede que o usuário conectado desative
         * ou bloqueie a própria conta pelo modal.
         */
        if (
            $currentUser->id() === $userId
            && $data->status() !== 'ativo'
        ) {
            throw new InvalidArgumentException(
                'Você não pode desativar ou bloquear a própria conta.'
            );
        }

        $service->updateUser(
            $userId,
            $data
        );

        $session->flash(
            'success',
            'Usuário atualizado com sucesso.'
        );
    } else {
        $service->createUser($data);

        $session->flash(
            'success',
            'Usuário cadastrado com sucesso.'
        );
    }
} catch (InvalidArgumentException $exception) {
    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    error_log(
        'User save failed: '
        . $exception->getMessage()
    );

    $session->flash(
        'danger',
        'Não foi possível salvar o usuário. Verifique os dados e tente novamente.'
    );
}

user_redirect(
    $application,
    'usuarios.php'
);