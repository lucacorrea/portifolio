<?php

declare(strict_types=1);

use App\Workforce\DTO\EmployeeFormData;

require __DIR__ . '/funcionario-action-common.php';

employee_require_post_request();

$rawEmployeeId = trim(
    (string) ($_POST['id'] ?? '')
);
$isEditing = $rawEmployeeId !== '';

$requiredPermission = $isEditing
    ? 'funcionario.editar'
    : 'funcionario.criar';

[
    $application,
    $session,
] = employee_action_context($requiredPermission);

try {
    $employeeId = null;

    if ($isEditing) {
        $employeeId = employee_posted_positive_int('id');
    }

    $data = EmployeeFormData::fromArray([
        'name' => $_POST['name'] ?? '',
    ]);

    $service = $application->employeeManagement();

    if ($employeeId === null) {
        $employee = $service->createEmployee($data);

        $session->flash(
            'success',
            'Funcionário cadastrado com o código '
            . $employee->displayCode()
            . '.'
        );
    } else {
        $service->updateEmployee(
            $employeeId,
            $data
        );

        $session->flash(
            'success',
            'Funcionário atualizado com sucesso.'
        );
    }
} catch (InvalidArgumentException $exception) {
    employee_store_form_recovery(
        $isEditing ? 'edit' : 'create',
        [
            'id' => $rawEmployeeId,
            'code' => $_POST['code'] ?? '',
            'name' => $_POST['name'] ?? '',
        ],
        $exception->getMessage()
    );

    $session->flash(
        'danger',
        $exception->getMessage()
    );

    employee_redirect(
        $application,
        'funcionarios.php'
    );
} catch (Throwable $exception) {
    error_log(
        'Employee save failed: '
        . $exception->getMessage()
    );

    $session->flash(
        'danger',
        'Não foi possível salvar o funcionário.'
    );
}

employee_redirect(
    $application,
    'funcionarios.php'
);
