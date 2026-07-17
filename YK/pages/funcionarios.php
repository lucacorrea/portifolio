<?php

declare(strict_types=1);

use App\Workforce\Entity\Employee;

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/funcionario-action-common.php';

$employeeService = $application->employeeManagement();

$search = trim((string) ($_GET['search'] ?? ''));
$employees = $employeeService->listEmployees($search);
$totalEmployees = $search === ''
    ? count($employees)
    : count($employeeService->listEmployees());

$canCreate = $authorization->can('funcionario.criar');
$canEdit = $authorization->can('funcionario.editar');

$employeeFormRecovery = employee_consume_form_recovery();

function employee_date(string $value): string
{
    if (trim($value) === '') {
        return '-';
    }

    try {
        return (new DateTimeImmutable($value))
            ->format('d/m/Y H:i');
    } catch (Throwable) {
        return '-';
    }
}

function employee_initials(string $name): string
{
    $parts = preg_split(
        '/\s+/u',
        trim($name)
    ) ?: [];

    $initials = '';

    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= substr($part, 0, 1);
    }

    return strtoupper($initials ?: 'F');
}

function employee_recovery_data(
    ?array $recovery,
    string $modal
): array {
    if (
        $recovery === null
        || ($recovery['modal'] ?? '') !== $modal
        || !isset($recovery['data'])
        || !is_array($recovery['data'])
    ) {
        return [];
    }

    return $recovery['data'];
}

function employee_recovery_error(
    ?array $recovery,
    string $modal
): ?string {
    if (
        $recovery === null
        || ($recovery['modal'] ?? '') !== $modal
        || !isset($recovery['error'])
        || !is_string($recovery['error'])
    ) {
        return null;
    }

    return $recovery['error'];
}

function employee_recovery_value(
    array $data,
    string $key
): string {
    $value = $data[$key] ?? '';

    return is_scalar($value)
        ? (string) $value
        : '';
}

$createRecoveryData = employee_recovery_data(
    $employeeFormRecovery,
    'create'
);
$createRecoveryError = employee_recovery_error(
    $employeeFormRecovery,
    'create'
);

$editRecoveryData = employee_recovery_data(
    $employeeFormRecovery,
    'edit'
);
$editRecoveryError = employee_recovery_error(
    $employeeFormRecovery,
    'edit'
);
?>

<div class="page-body employees-page">

<?php
metric_grid([
    [
        'Total de funcionários',
        (string) $totalEmployees,
        'bi-person-badge',
        '#2563EB',
        'cadastrados',
    ],
]);
?>

<form
    class="filter-bar"
    method="get"
    action="funcionarios.php"
    data-live-filter="employees"
    data-live-regions="metrics results"
>
    <div class="search-wrap">
        <i class="bi bi-search"></i>

        <input
            class="search-input"
            type="search"
            name="search"
            value="<?= h($search) ?>"
            placeholder="Buscar por código ou nome"
            maxlength="150"
        >
    </div>

    <button
        class="btn-filter btn-filter-primary"
        type="submit"
    >
        <i class="bi bi-funnel"></i>
        Filtrar
    </button>

    <a
        class="btn-filter btn-filter-ghost"
        href="funcionarios.php"
        data-live-filter-clear
    >
        <i class="bi bi-x-lg"></i>
        Limpar filtros
    </a>
</form>

<section class="panel" data-live-region="results">
    <div class="panel-header">
        <div class="panel-title">
            <i class="bi bi-person-badge"></i>
            Funcionários cadastrados
        </div>

        <?php if ($canCreate): ?>
            <button
                class="btn-new-os"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#modal-funcionario"
            >
                <i class="bi bi-person-plus"></i>
                <span>Novo funcionário</span>
            </button>
        <?php endif; ?>
    </div>

    <?php if ($employees === []): ?>
        <?php
        empty_state(
            'Nenhum funcionário encontrado',
            'Cadastre o primeiro funcionário ou ajuste a pesquisa.'
        );
        ?>
    <?php else: ?>
        <div class="table-panel-wrap">
            <table class="os-table employees-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nome</th>
                        <th>Cadastrado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <?php /** @var Employee $employee */ ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= h($employee->displayCode()) ?>
                                </strong>
                            </td>

                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="user-avatar small">
                                        <?= h(employee_initials($employee->name())) ?>
                                    </span>

                                    <strong>
                                        <?= h($employee->name()) ?>
                                    </strong>
                                </div>
                            </td>

                            <td>
                                <?= h(employee_date($employee->createdAt())) ?>
                            </td>

                            <td class="table-actions-cell">
                                <div class="dropdown table-action-dropdown">
                                    <button
                                        class="btn-action"
                                        type="button"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                        aria-label="Ações do funcionário <?= h($employee->name()) ?>"
                                    >
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>

                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button
                                                class="dropdown-item js-employee-view"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modal-funcionario-view"
                                                data-employee-id="<?= h((string) $employee->id()) ?>"
                                                data-employee-code="<?= h($employee->displayCode()) ?>"
                                                data-employee-name="<?= h($employee->name()) ?>"
                                                data-employee-created-at="<?= h(employee_date($employee->createdAt())) ?>"
                                                data-employee-updated-at="<?= h(employee_date($employee->updatedAt())) ?>"
                                            >
                                                <i class="bi bi-eye"></i>
                                                Visualizar
                                            </button>
                                        </li>

                                        <?php if ($canEdit): ?>
                                            <li>
                                                <button
                                                    class="dropdown-item js-employee-edit"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modal-funcionario-edit"
                                                    data-employee-id="<?= h((string) $employee->id()) ?>"
                                                    data-employee-code="<?= h($employee->displayCode()) ?>"
                                                    data-employee-name="<?= h($employee->name()) ?>"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                    Editar
                                                </button>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

</div>

<?php if ($canCreate): ?>
<div
    class="modal fade"
    id="modal-funcionario"
    tabindex="-1"
    aria-labelledby="modal-funcionario-title"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content visual-modal"
            method="post"
            action="actions/funcionario-salvar.php"
            autocomplete="off"
        >
            <div class="modal-header">
                <div>
                    <h2
                        class="modal-title fs-5"
                        id="modal-funcionario-title"
                    >
                        Novo funcionário
                    </h2>

                    <p class="text-muted small mb-0">
                        O código será gerado automaticamente.
                    </p>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Fechar"
                ></button>
            </div>

            <div class="modal-body">
                <?= $csrf->field() ?>
                <?php return_to_field(); ?>

                <div
                    class="alert alert-danger <?= $createRecoveryError === null ? 'd-none' : '' ?>"
                    id="create-employee-form-error"
                    role="alert"
                >
                    <?= h($createRecoveryError ?? '') ?>
                </div>

                <div class="form-group">
                    <label
                        class="form-label"
                        for="create-employee-name"
                    >
                        Nome
                    </label>

                    <input
                        class="form-control-os"
                        id="create-employee-name"
                        type="text"
                        name="name"
                        value="<?= h(employee_recovery_value($createRecoveryData, 'name')) ?>"
                        maxlength="150"
                        required
                    >
                </div>
            </div>

            <div class="modal-footer">
                <button
                    class="btn-modal-cancel"
                    type="button"
                    data-bs-dismiss="modal"
                >
                    Cancelar
                </button>

                <button
                    class="btn-modal-save"
                    type="submit"
                >
                    <i class="bi bi-check-lg"></i>
                    Cadastrar funcionário
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div
    class="modal fade"
    id="modal-funcionario-view"
    tabindex="-1"
    aria-labelledby="modal-funcionario-view-title"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content visual-modal">
            <div class="modal-header">
                <div>
                    <h2
                        class="modal-title fs-5"
                        id="modal-funcionario-view-title"
                    >
                        Dados do funcionário
                    </h2>

                    <p
                        class="text-muted small mb-0"
                        id="view-employee-subtitle"
                    ></p>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Fechar"
                ></button>
            </div>

            <div class="modal-body">
                <section class="form-section">
                    <h3 class="form-section-title">
                        Identificação
                    </h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Código
                            </label>

                            <div
                                class="form-control-os"
                                id="view-employee-code"
                            ></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Nome
                            </label>

                            <div
                                class="form-control-os"
                                id="view-employee-name"
                            ></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                Cadastrado em
                            </label>

                            <div
                                class="form-control-os"
                                id="view-employee-created-at"
                            ></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                Atualizado em
                            </label>

                            <div
                                class="form-control-os"
                                id="view-employee-updated-at"
                            ></div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="modal-footer">
                <button
                    class="btn-modal-cancel"
                    type="button"
                    data-bs-dismiss="modal"
                >
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($canEdit): ?>
<div
    class="modal fade"
    id="modal-funcionario-edit"
    tabindex="-1"
    aria-labelledby="modal-funcionario-edit-title"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">
        <form
            class="modal-content visual-modal"
            method="post"
            action="actions/funcionario-salvar.php"
            autocomplete="off"
        >
            <div class="modal-header">
                <div>
                    <h2
                        class="modal-title fs-5"
                        id="modal-funcionario-edit-title"
                    >
                        Editar funcionário
                    </h2>

                    <p
                        class="text-muted small mb-0"
                        id="edit-employee-subtitle"
                    >
                        <?= h(employee_recovery_value($editRecoveryData, 'code')) ?>
                    </p>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Fechar"
                ></button>
            </div>

            <div class="modal-body">
                <?= $csrf->field() ?>
                <?php return_to_field(); ?>

                <div
                    class="alert alert-danger <?= $editRecoveryError === null ? 'd-none' : '' ?>"
                    id="edit-employee-form-error"
                    role="alert"
                >
                    <?= h($editRecoveryError ?? '') ?>
                </div>

                <input
                    type="hidden"
                    name="id"
                    id="edit-employee-id"
                    value="<?= h(employee_recovery_value($editRecoveryData, 'id')) ?>"
                >

                <input
                    type="hidden"
                    name="code"
                    id="edit-employee-code-hidden"
                    value="<?= h(employee_recovery_value($editRecoveryData, 'code')) ?>"
                >

                <div class="form-group">
                    <label
                        class="form-label"
                        for="edit-employee-code"
                    >
                        Código
                    </label>

                    <input
                        class="form-control-os"
                        id="edit-employee-code"
                        type="text"
                        value="<?= h(employee_recovery_value($editRecoveryData, 'code')) ?>"
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label
                        class="form-label"
                        for="edit-employee-name"
                    >
                        Nome
                    </label>

                    <input
                        class="form-control-os"
                        id="edit-employee-name"
                        type="text"
                        name="name"
                        value="<?= h(employee_recovery_value($editRecoveryData, 'name')) ?>"
                        maxlength="150"
                        required
                    >
                </div>
            </div>

            <div class="modal-footer">
                <button
                    class="btn-modal-cancel"
                    type="button"
                    data-bs-dismiss="modal"
                >
                    Cancelar
                </button>

                <button
                    class="btn-modal-save"
                    type="submit"
                >
                    <i class="bi bi-check-lg"></i>
                    Salvar alterações
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const employeeRecoveryModal = <?= json_encode(
        $employeeFormRecovery['modal'] ?? null,
        JSON_HEX_TAG
        | JSON_HEX_APOS
        | JSON_HEX_AMP
        | JSON_HEX_QUOT
    ) ?>;

    function text(id, value) {
        const element = document.getElementById(id);

        if (element) {
            element.textContent = value || '-';
        }
    }

    function value(id, newValue) {
        const element = document.getElementById(id);

        if (element) {
            element.value = newValue || '';
        }
    }

    function hideError(id) {
        const element = document.getElementById(id);

        if (!element) {
            return;
        }

        element.textContent = '';
        element.classList.add('d-none');
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest(
            '.js-employee-view, .js-employee-edit'
        );

        if (!button) {
            return;
        }

        if (button.classList.contains('js-employee-view')) {
                text(
                    'view-employee-subtitle',
                    button.dataset.employeeCode
                );
                text(
                    'view-employee-code',
                    button.dataset.employeeCode
                );
                text(
                    'view-employee-name',
                    button.dataset.employeeName
                );
                text(
                    'view-employee-created-at',
                    button.dataset.employeeCreatedAt
                );
                text(
                    'view-employee-updated-at',
                    button.dataset.employeeUpdatedAt
                );
        }

        if (button.classList.contains('js-employee-edit')) {
                hideError('edit-employee-form-error');
                text(
                    'edit-employee-subtitle',
                    button.dataset.employeeCode
                );
                value(
                    'edit-employee-id',
                    button.dataset.employeeId
                );
                value(
                    'edit-employee-code-hidden',
                    button.dataset.employeeCode
                );
                value(
                    'edit-employee-code',
                    button.dataset.employeeCode
                );
                value(
                    'edit-employee-name',
                    button.dataset.employeeName
                );
        }
    });

    const createModal = document.getElementById(
        'modal-funcionario'
    );

    if (createModal) {
        const createForm = createModal.querySelector('form');

        createModal.addEventListener(
            'show.bs.modal',
            function (event) {
                if (!event.relatedTarget) {
                    return;
                }

                if (createForm) {
                    createForm.reset();
                }

                hideError('create-employee-form-error');
            }
        );
    }

    const recoveryTargets = {
        create: 'modal-funcionario',
        edit: 'modal-funcionario-edit'
    };

    if (
        employeeRecoveryModal
        && recoveryTargets[employeeRecoveryModal]
        && window.bootstrap
    ) {
        const modalElement = document.getElementById(
            recoveryTargets[employeeRecoveryModal]
        );

        if (modalElement) {
            bootstrap.Modal
                .getOrCreateInstance(modalElement)
                .show();
        }
    }
});
</script>
