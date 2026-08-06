<?php

declare(strict_types=1);

require __DIR__
    . '/funcionario-action-common.php';

employee_require_post_request();

[$application, $session] =
    employee_action_context(
        'funcionario.editar'
    );

try {
    $employeeId =
        employee_posted_positive_int(
            'id'
        );

    $status = trim(
        (string) (
            $_POST['status']
            ?? ''
        )
    );

    if (
        !in_array(
            $status,
            [
                'ativo',
                'inativo',
            ],
            true
        )
    ) {
        throw new InvalidArgumentException(
            'Status de funcionário inválido.'
        );
    }

    $service =
        $application
            ->employeeManagement();

    $employee =
        $service->getEmployee(
            $employeeId
        );

    $service->updateEmployeeStatus(
        $employeeId,
        $status
    );

    $session->flash(
        'success',
        $status === 'ativo'
            ? (
                'Funcionário '
                . $employee->name()
                . ' reativado com sucesso.'
            )
            : (
                'Funcionário '
                . $employee->name()
                . ' inativado com sucesso.'
            )
    );
} catch (InvalidArgumentException $exception) {
    $session->flash(
        'danger',
        $exception->getMessage()
    );
} catch (Throwable $exception) {
    error_log(
        'Employee status update failed: '
        . $exception->getMessage()
    );

    $session->flash(
        'danger',
        'Não foi possível alterar o status do funcionário.'
    );
}

employee_redirect(
    $application,
    'funcionarios.php'
);