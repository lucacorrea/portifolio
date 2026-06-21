<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';
require_once __DIR__ . '/../actions/usuario-action-common.php';

$userService = $application->userManagement();

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'status' => (string) ($_GET['status'] ?? ''),
    'profile_id' => (int) ($_GET['profile_id'] ?? 0),
];

$users = $userService->listUsers($filters);
$summary = $userService->userSummary();
$profiles = $userService->activeProfiles();

$canCreate = $authorization->can('usuario.criar');
$canEdit = $authorization->can('usuario.editar');
$canActivate = $authorization->can('usuario.ativar');
$canBlock = $authorization->can('usuario.bloquear');
$canDeactivate = $authorization->can('usuario.desativar');
$canResetPassword = $authorization->can(
    'usuario.redefinir_senha'
);

$userFormRecovery = user_consume_form_recovery();

function user_recovery_data(
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

function user_recovery_error(
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

function user_recovery_value(
    array $data,
    string $key,
    string $default = ''
): string {
    $value = $data[$key] ?? $default;

    if (!is_scalar($value)) {
        return $default;
    }

    return (string) $value;
}

function user_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return 'Nunca';
    }

    try {
        return (new DateTimeImmutable($value))
            ->format('d/m/Y H:i');
    } catch (Throwable) {
        return '-';
    }
}

function user_status_label(
    string $status,
    bool $temporaryLocked
): string {
    if ($temporaryLocked) {
        return 'Bloqueio temporário';
    }

    return match ($status) {
        'ativo' => 'Ativo',
        'inativo' => 'Inativo',
        'bloqueado' => 'Bloqueado',
        default => 'Desconhecido',
    };
}

function user_status_class(
    string $status,
    bool $temporaryLocked
): string {
    if ($temporaryLocked) {
        return 'amber';
    }

    return match ($status) {
        'ativo' => 'green',
        'inativo' => 'gray',
        'bloqueado' => 'red',
        default => 'gray',
    };
}

function user_status_badge(
    string $status,
    bool $temporaryLocked
): string {
    return sprintf(
        '<span class="badge-soft badge-%s">%s</span>',
        h(user_status_class($status, $temporaryLocked)),
        h(user_status_label($status, $temporaryLocked))
    );
}
?>

<div class="page-body users-page">


<?php
metric_grid([
    [
        'Total de usuários',
        (string) ($summary['total'] ?? 0),
        'bi-people',
        '#2563EB',
        'cadastrados',
    ],
    [
        'Usuários ativos',
        (string) ($summary['active'] ?? 0),
        'bi-person-check',
        '#16A34A',
        'com acesso permitido',
    ],
    [
        'Usuários inativos',
        (string) ($summary['inactive'] ?? 0),
        'bi-person-dash',
        '#D97706',
        'sem acesso',
    ],
    [
        'Usuários bloqueados',
        (string) ($summary['blocked'] ?? 0),
        'bi-person-lock',
        '#DC2626',
        'bloqueados manualmente',
    ],
    [
        'Bloqueios temporários',
        (string) ($summary['temporary_locked'] ?? 0),
        'bi-clock-history',
        '#7C3AED',
        'por tentativas falhas',
    ],
]);
?>

<form
    class="filter-bar"
    method="get"
    action="usuarios.php"
>
    <div class="search-wrap">
        <i class="bi bi-search"></i>

        <input
            class="search-input"
            type="search"
            name="search"
            value="<?= h($filters['search']) ?>"
            placeholder="Buscar por nome, usuário ou e-mail"
            maxlength="150"
        >
    </div>

    <select
        class="filter-select"
        name="status"
        aria-label="Filtrar por status"
    >
        <option value="">
            Todos os status
        </option>

        <option
            value="ativo"
            <?= $filters['status'] === 'ativo'
                ? 'selected'
                : '' ?>
        >
            Ativos
        </option>

        <option
            value="inativo"
            <?= $filters['status'] === 'inativo'
                ? 'selected'
                : '' ?>
        >
            Inativos
        </option>

        <option
            value="bloqueado"
            <?= $filters['status'] === 'bloqueado'
                ? 'selected'
                : '' ?>
        >
            Bloqueados
        </option>
    </select>

    <select
        class="filter-select"
        name="profile_id"
        aria-label="Filtrar por perfil"
    >
        <option value="0">
            Todos os perfis
        </option>

        <?php foreach ($profiles as $profile): ?>
            <?php
            $profileId = $profile->id();

            if ($profileId === null) {
                continue;
            }
            ?>

            <option
                value="<?= h((string) $profileId) ?>"
                <?= $filters['profile_id'] === $profileId
                    ? 'selected'
                    : '' ?>
            >
                <?= h($profile->name()) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button
        class="btn-filter btn-filter-primary"
        type="submit"
    >
        <i class="bi bi-funnel"></i>
        Filtrar
    </button>

    <a
        class="btn-filter btn-filter-ghost"
        href="usuarios.php"
    >
        <i class="bi bi-x-lg"></i>
        Limpar filtros
    </a>
</form>

<section class="panel">

    <div class="panel-header">
        <div class="panel-title">
            <i class="bi bi-people"></i>
            Usuários cadastrados
        </div>

        <?php if ($canCreate): ?>
            <button
                class="btn-new-os"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#modal-usuario"
            >
                <i class="bi bi-person-plus"></i>
                <span>Novo usuário</span>
            </button>
        <?php endif; ?>
    </div>

    <?php if ($users === []): ?>

        <?php
        empty_state(
            'Nenhum usuário encontrado',
            'Ajuste os filtros ou cadastre um novo usuário.'
        );
        ?>

    <?php else: ?>

        <div class="table-panel-wrap">
            <table class="os-table users-table">

                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Contato</th>
                        <th>Perfil</th>
                        <th>Status</th>
                        <th>Senha</th>
                        <th>Último acesso</th>
                        <th>Cadastrado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $isCurrentUser = (
                            $currentUser->id() === $user->id()
                        );

                        $temporaryLocked =
                            $user->isTemporarilyLocked();

                        $statusLabel = user_status_label(
                            $user->status(),
                            $temporaryLocked
                        );
                        ?>

                        <tr>
                            <td>
                                <div
                                    class="d-flex align-items-center gap-2"
                                >
                                    <span class="user-avatar small">
                                        <?= h($user->initials()) ?>
                                    </span>

                                    <div>
                                        <strong>
                                            <?= h($user->name()) ?>
                                        </strong>

                                        <?php if ($isCurrentUser): ?>
                                            <span
                                                class="badge-soft badge-blue ms-1"
                                            >
                                                Você
                                            </span>
                                        <?php endif; ?>

                                        <div class="text-muted small">
                                            @<?= h($user->username()) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <div>
                                    <?= h($user->email()) ?>
                                </div>

                                <div class="text-muted small">
                                    <?= h(
                                        $user->phone()
                                        ?? 'Sem telefone'
                                    ) ?>
                                </div>
                            </td>

                            <td>
                                <span class="badge-soft badge-blue">
                                    <?= h($user->profileName()) ?>
                                </span>
                            </td>

                            <td>
                                <?= user_status_badge(
                                    $user->status(),
                                    $temporaryLocked
                                ) ?>

                                <?php
                                if (
                                    $temporaryLocked
                                    && $user->lockedUntil() !== null
                                ):
                                ?>
                                    <div class="text-muted small mt-1">
                                        Até
                                        <?= h(
                                            user_date(
                                                $user->lockedUntil()
                                            )
                                        ) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (
                                    $user->mustChangePassword()
                                ): ?>
                                    <span
                                        class="badge-soft badge-amber"
                                    >
                                        Troca pendente
                                    </span>
                                <?php else: ?>
                                    <span
                                        class="badge-soft badge-green"
                                    >
                                        Definida
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= h(
                                    user_date(
                                        $user->lastAccess()
                                    )
                                ) ?>
                            </td>

                            <td>
                                <?= h(
                                    user_date(
                                        $user->createdAt()
                                    )
                                ) ?>
                            </td>

                            <td>
                                <div class="dropdown">
                                    <button
                                        class="btn-action"
                                        type="button"
                                        data-bs-toggle="dropdown"
                                        aria-label="Ações do usuário <?= h($user->name()) ?>"
                                    >
                                        <i
                                            class="bi bi-three-dots-vertical"
                                        ></i>
                                    </button>

                                    <ul
                                        class="dropdown-menu dropdown-menu-end"
                                    >
                                        <li>
                                            <button
                                                class="dropdown-item js-user-view"
                                                type="button"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modal-usuario-view"
                                                data-user-id="<?= h((string) $user->id()) ?>"
                                                data-user-name="<?= h($user->name()) ?>"
                                                data-user-username="<?= h($user->username()) ?>"
                                                data-user-email="<?= h($user->email()) ?>"
                                                data-user-phone="<?= h($user->phone() ?? '') ?>"
                                                data-user-profile="<?= h($user->profileName()) ?>"
                                                data-user-profile-id="<?= h((string) $user->profileId()) ?>"
                                                data-user-status="<?= h($user->status()) ?>"
                                                data-user-status-label="<?= h($statusLabel) ?>"
                                                data-user-must-change="<?= $user->mustChangePassword() ? '1' : '0' ?>"
                                                data-user-failed-attempts="<?= h((string) $user->failedAttempts()) ?>"
                                                data-user-locked-until="<?= h(user_date($user->lockedUntil())) ?>"
                                                data-user-last-access="<?= h(user_date($user->lastAccess())) ?>"
                                                data-user-created-at="<?= h(user_date($user->createdAt())) ?>"
                                            >
                                                <i class="bi bi-eye"></i>
                                                Visualizar
                                            </button>
                                        </li>

                                        <?php if ($canEdit): ?>
                                            <li>
                                                <button
                                                    class="dropdown-item js-user-edit"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modal-usuario-edit"
                                                    data-user-id="<?= h((string) $user->id()) ?>"
                                                    data-user-name="<?= h($user->name()) ?>"
                                                    data-user-username="<?= h($user->username()) ?>"
                                                    data-user-email="<?= h($user->email()) ?>"
                                                    data-user-phone="<?= h($user->phone() ?? '') ?>"
                                                    data-user-profile-id="<?= h((string) $user->profileId()) ?>"
                                                    data-user-status="<?= h($user->status()) ?>"
                                                    data-user-must-change="<?= $user->mustChangePassword() ? '1' : '0' ?>"
                                                >
                                                    <i class="bi bi-pencil"></i>
                                                    Editar
                                                </button>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($canResetPassword): ?>
                                            <li>
                                                <button
                                                    class="dropdown-item js-user-reset-password"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modal-usuario-password"
                                                    data-user-id="<?= h((string) $user->id()) ?>"
                                                    data-user-name="<?= h($user->name()) ?>"
                                                >
                                                    <i class="bi bi-key"></i>
                                                    Redefinir senha
                                                </button>
                                            </li>
                                        <?php endif; ?>

                                        <?php if (
                                            $canActivate
                                            || $canDeactivate
                                            || $canBlock
                                        ): ?>
                                            <li>
                                                <hr
                                                    class="dropdown-divider"
                                                >
                                            </li>
                                        <?php endif; ?>

                                        <?php if (
                                            $temporaryLocked
                                            && $canActivate
                                        ): ?>
                                            <li>
                                                <button
                                                    class="dropdown-item js-user-status"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modal-usuario-status"
                                                    data-user-id="<?= h((string) $user->id()) ?>"
                                                    data-user-name="<?= h($user->name()) ?>"
                                                    data-user-operation="desbloquear"
                                                >
                                                    <i class="bi bi-unlock"></i>
                                                    Remover bloqueio temporário
                                                </button>
                                            </li>
                                        <?php endif; ?>

                                        <?php if (
                                            $user->status() !== 'ativo'
                                            && $canActivate
                                        ): ?>
                                            <li>
                                                <button
                                                    class="dropdown-item text-success js-user-status"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modal-usuario-status"
                                                    data-user-id="<?= h((string) $user->id()) ?>"
                                                    data-user-name="<?= h($user->name()) ?>"
                                                    data-user-operation="ativo"
                                                >
                                                    <i class="bi bi-person-check"></i>
                                                    Ativar usuário
                                                </button>
                                            </li>
                                        <?php endif; ?>

                                        <?php if (
                                            !$isCurrentUser
                                            && $user->status() !== 'inativo'
                                            && $canDeactivate
                                        ): ?>
                                            <li>
                                                <button
                                                    class="dropdown-item js-user-status"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modal-usuario-status"
                                                    data-user-id="<?= h((string) $user->id()) ?>"
                                                    data-user-name="<?= h($user->name()) ?>"
                                                    data-user-operation="inativo"
                                                >
                                                    <i class="bi bi-person-dash"></i>
                                                    Desativar usuário
                                                </button>
                                            </li>
                                        <?php endif; ?>

                                        <?php if (
                                            !$isCurrentUser
                                            && $user->status() !== 'bloqueado'
                                            && $canBlock
                                        ): ?>
                                            <li>
                                                <button
                                                    class="dropdown-item text-danger js-user-status"
                                                    type="button"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modal-usuario-status"
                                                    data-user-id="<?= h((string) $user->id()) ?>"
                                                    data-user-name="<?= h($user->name()) ?>"
                                                    data-user-operation="bloqueado"
                                                >
                                                    <i class="bi bi-person-lock"></i>
                                                    Bloquear usuário
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

<?php
$createRecoveryData = user_recovery_data(
    $userFormRecovery,
    'create'
);
$createRecoveryError = user_recovery_error(
    $userFormRecovery,
    'create'
);
$createRecoveryStatus = user_recovery_value(
    $createRecoveryData,
    'status',
    'ativo'
);
$createRecoveryProfileId = user_recovery_value(
    $createRecoveryData,
    'profile_id'
);
$createMustChangeChecked = $createRecoveryError === null
    || user_recovery_value(
        $createRecoveryData,
        'must_change_password'
    ) === '1';
?>

<div
    class="modal fade"
    id="modal-usuario"
    tabindex="-1"
    aria-labelledby="modal-usuario-title"
    aria-hidden="true"
>
    <div
        class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"
    >
        <form
            class="modal-content visual-modal"
            method="post"
            action="actions/usuario-salvar.php"
            autocomplete="off"
        >
            <div class="modal-header">
                <div>
                    <h2
                        class="modal-title fs-5"
                        id="modal-usuario-title"
                    >
                        Novo usuário
                    </h2>

                    <p class="text-muted small mb-0">
                        Cadastre um novo acesso ao sistema.
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

                <div
                    class="alert alert-danger <?= $createRecoveryError === null ? 'd-none' : '' ?>"
                    id="create-user-form-error"
                    role="alert"
                >
                    <?= h($createRecoveryError ?? '') ?>
                </div>

                <section class="form-section">
                    <h3 class="form-section-title">
                        Dados do usuário
                    </h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label
                                class="form-label"
                                for="create-user-name"
                            >
                                Nome completo
                            </label>

                            <input
                                class="form-control-os"
                                id="create-user-name"
                                type="text"
                                name="name"
                                value="<?= h(user_recovery_value($createRecoveryData, 'name')) ?>"
                                maxlength="150"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label
                                class="form-label"
                                for="create-user-phone"
                            >
                                Telefone
                            </label>

                            <input
                                class="form-control-os"
                                id="create-user-phone"
                                type="text"
                                name="phone"
                                value="<?= h(user_recovery_value($createRecoveryData, 'phone')) ?>"
                                maxlength="30"
                                placeholder="(92) 99999-9999"
                            >
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label
                                class="form-label"
                                for="create-user-email"
                            >
                                E-mail
                            </label>

                            <input
                                class="form-control-os"
                                id="create-user-email"
                                type="email"
                                name="email"
                                value="<?= h(user_recovery_value($createRecoveryData, 'email')) ?>"
                                maxlength="150"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label
                                class="form-label"
                                for="create-user-username"
                            >
                                Nome de usuário
                            </label>

                            <input
                                class="form-control-os"
                                id="create-user-username"
                                type="text"
                                name="username"
                                value="<?= h(user_recovery_value($createRecoveryData, 'username')) ?>"
                                minlength="3"
                                maxlength="80"
                                pattern="[a-zA-Z0-9_.-]{3,80}"
                                required
                            >

                            <small class="text-muted">
                                Letras, números, ponto, hífen ou sublinhado.
                            </small>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h3 class="form-section-title">
                        Perfil e acesso
                    </h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label
                                class="form-label"
                                for="create-user-profile"
                            >
                                Perfil
                            </label>

                            <select
                                class="form-control-os"
                                id="create-user-profile"
                                name="profile_id"
                                required
                            >
                                <option value="">
                                    Selecione
                                </option>

                                <?php foreach ($profiles as $profile): ?>
                                    <?php
                                    $createProfileId =
                                        $profile->id();

                                    if ($createProfileId === null) {
                                        continue;
                                    }
                                    ?>

                                    <option
                                        value="<?= h((string) $createProfileId) ?>"
                                        <?= $createRecoveryProfileId === (string) $createProfileId
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= h($profile->name()) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label
                                class="form-label"
                                for="create-user-status"
                            >
                                Status inicial
                            </label>

                            <select
                                class="form-control-os"
                                id="create-user-status"
                                name="status"
                                required
                            >
                                <option value="ativo">
                                    Ativo
                                </option>

                                <option
                                    value="inativo"
                                    <?= $createRecoveryStatus === 'inativo'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Inativo
                                </option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h3 class="form-section-title">
                        Senha inicial
                    </h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label
                                class="form-label"
                                for="create-user-password"
                            >
                                Senha
                            </label>

                            <input
                                class="form-control-os"
                                id="create-user-password"
                                type="password"
                                name="password"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                required
                            >

                            <small class="text-muted">
                                Mínimo de 8 caracteres, com letra e número.
                            </small>
                        </div>

                        <div class="form-group">
                            <label
                                class="form-label"
                                for="create-user-password-confirmation"
                            >
                                Confirmar senha
                            </label>

                            <input
                                class="form-control-os"
                                id="create-user-password-confirmation"
                                type="password"
                                name="password_confirmation"
                                minlength="8"
                                maxlength="72"
                                autocomplete="new-password"
                                required
                            >
                        </div>
                    </div>

                    <input
                        type="hidden"
                        name="must_change_password"
                        value="0"
                    >

                    <div class="form-check mt-3">
                        <input
                            class="form-check-input"
                            id="create-user-must-change"
                            type="checkbox"
                            name="must_change_password"
                            value="1"
                            <?= $createMustChangeChecked ? 'checked' : '' ?>
                        >

                        <label
                            class="form-check-label"
                            for="create-user-must-change"
                        >
                            Exigir alteração da senha no próximo acesso
                        </label>
                    </div>
                </section>
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
                    Cadastrar usuário
                </button>
            </div>
        </form>
    </div>
</div>


<?php endif; ?>

<div
    class="modal fade"
    id="modal-usuario-view"
    tabindex="-1"
    aria-labelledby="modal-usuario-view-title"
    aria-hidden="true"
>
    <div
        class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"
    >
        <div class="modal-content visual-modal">
            <div class="modal-header">
                <div>
                    <h2
                        class="modal-title fs-5"
                        id="modal-usuario-view-title"
                    >
                        Dados do usuário
                    </h2>


                <p
                    class="text-muted small mb-0"
                    id="view-user-subtitle"
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
                            Nome
                        </label>

                        <div
                            class="form-control-os"
                            id="view-user-name"
                        ></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Nome de usuário
                        </label>

                        <div
                            class="form-control-os"
                            id="view-user-username"
                        ></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            E-mail
                        </label>

                        <div
                            class="form-control-os"
                            id="view-user-email"
                        ></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Telefone
                        </label>

                        <div
                            class="form-control-os"
                            id="view-user-phone"
                        ></div>
                    </div>
                </div>
            </section>

            <section class="form-section">
                <h3 class="form-section-title">
                    Acesso ao sistema
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Perfil
                        </label>

                        <div
                            class="form-control-os"
                            id="view-user-profile"
                        ></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Status
                        </label>

                        <div
                            class="form-control-os"
                            id="view-user-status"
                        ></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Situação da senha
                        </label>

                        <div
                            class="form-control-os"
                            id="view-user-password-status"
                        ></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Tentativas falhas
                        </label>

                        <div
                            class="form-control-os"
                            id="view-user-failed-attempts"
                        ></div>
                    </div>
                </div>
            </section>

            <section class="form-section">
                <h3 class="form-section-title">
                    Histórico
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            Último acesso
                        </label>

                        <div
                            class="form-control-os"
                            id="view-user-last-access"
                        ></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Cadastrado em
                        </label>

                        <div
                            class="form-control-os"
                            id="view-user-created-at"
                        ></div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Bloqueado até
                    </label>

                    <div
                        class="form-control-os"
                        id="view-user-locked-until"
                    ></div>
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

<?php
$editRecoveryData = user_recovery_data(
    $userFormRecovery,
    'edit'
);
$editRecoveryError = user_recovery_error(
    $userFormRecovery,
    'edit'
);
$editRecoveryStatus = user_recovery_value(
    $editRecoveryData,
    'status',
    'ativo'
);
$editRecoveryProfileId = user_recovery_value(
    $editRecoveryData,
    'profile_id'
);
$editMustChangeChecked = user_recovery_value(
    $editRecoveryData,
    'must_change_password'
) === '1';
?>

<div
    class="modal fade"
    id="modal-usuario-edit"
    tabindex="-1"
    aria-labelledby="modal-usuario-edit-title"
    aria-hidden="true"
>
    <div
        class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"
    >
        <form
            class="modal-content visual-modal"
            method="post"
            action="actions/usuario-salvar.php"
            autocomplete="off"
        >
            <div class="modal-header">
                <div>
                    <h2
                        class="modal-title fs-5"
                        id="modal-usuario-edit-title"
                    >
                        Editar usuário
                    </h2>

                    <p
                        class="text-muted small mb-0"
                        id="edit-user-subtitle"
                    ><?= h(user_recovery_value($editRecoveryData, 'name')) ?></p>
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

                <div
                    class="alert alert-danger <?= $editRecoveryError === null ? 'd-none' : '' ?>"
                    id="edit-user-form-error"
                    role="alert"
                >
                    <?= h($editRecoveryError ?? '') ?>
                </div>

                <input
                    type="hidden"
                    name="id"
                    id="edit-user-id"
                    value="<?= h(user_recovery_value($editRecoveryData, 'id')) ?>"
                >

                <section class="form-section">
                    <h3 class="form-section-title">
                        Dados do usuário
                    </h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label
                                class="form-label"
                                for="edit-user-name"
                            >
                                Nome completo
                            </label>

                            <input
                                class="form-control-os"
                                id="edit-user-name"
                                type="text"
                                name="name"
                                value="<?= h(user_recovery_value($editRecoveryData, 'name')) ?>"
                                maxlength="150"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label
                                class="form-label"
                                for="edit-user-phone"
                            >
                                Telefone
                            </label>

                            <input
                                class="form-control-os"
                                id="edit-user-phone"
                                type="text"
                                name="phone"
                                value="<?= h(user_recovery_value($editRecoveryData, 'phone')) ?>"
                                maxlength="30"
                            >
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label
                                class="form-label"
                                for="edit-user-email"
                            >
                                E-mail
                            </label>

                            <input
                                class="form-control-os"
                                id="edit-user-email"
                                type="email"
                                name="email"
                                value="<?= h(user_recovery_value($editRecoveryData, 'email')) ?>"
                                maxlength="150"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label
                                class="form-label"
                                for="edit-user-username"
                            >
                                Nome de usuário
                            </label>

                            <input
                                class="form-control-os"
                                id="edit-user-username"
                                type="text"
                                name="username"
                                value="<?= h(user_recovery_value($editRecoveryData, 'username')) ?>"
                                minlength="3"
                                maxlength="80"
                                pattern="[a-zA-Z0-9_.-]{3,80}"
                                required
                            >
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h3 class="form-section-title">
                        Perfil e acesso
                    </h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label
                                class="form-label"
                                for="edit-user-profile"
                            >
                                Perfil
                            </label>

                            <select
                                class="form-control-os"
                                id="edit-user-profile"
                                name="profile_id"
                                required
                            >
                                <?php foreach ($profiles as $profile): ?>
                                    <?php
                                    $editProfileId =
                                        $profile->id();

                                    if ($editProfileId === null) {
                                        continue;
                                    }
                                    ?>

                                    <option
                                        value="<?= h((string) $editProfileId) ?>"
                                        <?= $editRecoveryProfileId === (string) $editProfileId
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= h($profile->name()) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label
                                class="form-label"
                                for="edit-user-status"
                            >
                                Status
                            </label>

                            <select
                                class="form-control-os"
                                id="edit-user-status"
                                name="status"
                                required
                            >
                                <option
                                    value="ativo"
                                    <?= $editRecoveryStatus === 'ativo'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Ativo
                                </option>

                                <option
                                    value="inativo"
                                    <?= $editRecoveryStatus === 'inativo'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Inativo
                                </option>

                                <option
                                    value="bloqueado"
                                    <?= $editRecoveryStatus === 'bloqueado'
                                        ? 'selected'
                                        : '' ?>
                                >
                                    Bloqueado
                                </option>
                            </select>
                        </div>
                    </div>

                    <input
                        type="hidden"
                        name="must_change_password"
                        value="0"
                    >

                    <div class="form-check mt-3">
                        <input
                            class="form-check-input"
                            id="edit-user-must-change"
                            type="checkbox"
                            name="must_change_password"
                            value="1"
                            <?= $editMustChangeChecked ? 'checked' : '' ?>
                        >

                        <label
                            class="form-check-label"
                            for="edit-user-must-change"
                        >
                            Exigir alteração da senha no próximo acesso
                        </label>
                    </div>
                </section>
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

<?php if ($canResetPassword): ?>

<?php
$passwordRecoveryData = user_recovery_data(
    $userFormRecovery,
    'password'
);
$passwordRecoveryError = user_recovery_error(
    $userFormRecovery,
    'password'
);
$passwordMustChangeChecked = $passwordRecoveryError === null
    || user_recovery_value(
        $passwordRecoveryData,
        'must_change_password'
    ) === '1';
?>

<div
    class="modal fade"
    id="modal-usuario-password"
    tabindex="-1"
    aria-labelledby="modal-usuario-password-title"
    aria-hidden="true"
>
    <div
        class="modal-dialog modal-dialog-centered"
    >
        <form
            class="modal-content visual-modal"
            method="post"
            action="actions/usuario-redefinir-senha.php"
            autocomplete="off"
        >
            <div class="modal-header">
                <div>
                    <h2
                        class="modal-title fs-5"
                        id="modal-usuario-password-title"
                    >
                        Redefinir senha
                    </h2>

                    <p
                        class="text-muted small mb-0"
                        id="password-user-subtitle"
                    ><?= h(user_recovery_value($passwordRecoveryData, 'name')) ?></p>
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

                <div
                    class="alert alert-danger <?= $passwordRecoveryError === null ? 'd-none' : '' ?>"
                    id="password-user-form-error"
                    role="alert"
                >
                    <?= h($passwordRecoveryError ?? '') ?>
                </div>

                <input
                    type="hidden"
                    name="id"
                    id="password-user-id"
                    value="<?= h(user_recovery_value($passwordRecoveryData, 'id')) ?>"
                >

                <input
                    type="hidden"
                    name="user_name"
                    id="password-user-name"
                    value="<?= h(user_recovery_value($passwordRecoveryData, 'name')) ?>"
                >

                <div class="form-group mb-3">
                    <label
                        class="form-label"
                        for="password-user-new"
                    >
                        Nova senha
                    </label>

                    <input
                        class="form-control-os"
                        id="password-user-new"
                        type="password"
                        name="password"
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        required
                    >

                    <small class="text-muted">
                        Mínimo de 8 caracteres, com letra e número.
                    </small>
                </div>

                <div class="form-group">
                    <label
                        class="form-label"
                        for="password-user-confirmation"
                    >
                        Confirmar nova senha
                    </label>

                    <input
                        class="form-control-os"
                        id="password-user-confirmation"
                        type="password"
                        name="password_confirmation"
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <input
                    type="hidden"
                    name="must_change_password"
                    value="0"
                >

                <div class="form-check mt-3">
                    <input
                        class="form-check-input"
                        id="password-user-must-change"
                        type="checkbox"
                        name="must_change_password"
                        value="1"
                        <?= $passwordMustChangeChecked ? 'checked' : '' ?>
                    >

                    <label
                        class="form-check-label"
                        for="password-user-must-change"
                    >
                        Exigir troca da senha no próximo acesso
                    </label>
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
                    <i class="bi bi-key"></i>
                    Redefinir senha
                </button>
            </div>
        </form>
    </div>
</div>


<?php endif; ?>

<?php if (
    $canActivate
    || $canDeactivate
    || $canBlock
): ?>


<div
    class="modal fade"
    id="modal-usuario-status"
    tabindex="-1"
    aria-labelledby="modal-usuario-status-title"
    aria-hidden="true"
>
    <div
        class="modal-dialog modal-dialog-centered"
    >
        <form
            class="modal-content visual-modal"
            method="post"
            action="actions/usuario-status.php"
        >
            <div class="modal-header">
                <div>
                    <h2
                        class="modal-title fs-5"
                        id="modal-usuario-status-title"
                    >
                        Alterar status
                    </h2>

                    <p
                        class="text-muted small mb-0"
                        id="status-user-subtitle"
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
                <?= $csrf->field() ?>

                <input
                    type="hidden"
                    name="id"
                    id="status-user-id"
                >

                <input
                    type="hidden"
                    name="status"
                    id="status-user-operation"
                >

                <div
                    class="alert alert-warning mb-0"
                    id="status-user-message"
                ></div>
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
                    id="status-user-submit"
                >
                    Confirmar
                </button>
            </div>
        </form>
    </div>
</div>


<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const userFormRecoveryModal = <?= json_encode(
        $userFormRecovery['modal'] ?? null,
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

    function field(id) {
        return document.getElementById(id);
    }

    function showFormError(id, message) {
        const element = field(id);

        if (!element) {
            return;
        }

        element.textContent = message || '';
        element.classList.toggle('d-none', !message);
    }

    function clearFieldValidation(element) {
        if (!element) {
            return;
        }

        element.setCustomValidity('');
        element.classList.remove('is-invalid');
    }

    function clearPasswordValidation(errorId, fields) {
        showFormError(errorId, '');

        fields.forEach(function (element) {
            clearFieldValidation(element);
        });
    }

    function validatePasswordPair(
        event,
        password,
        confirmation,
        errorId
    ) {
        if (!password || !confirmation) {
            return true;
        }

        let invalidField = null;
        let message = '';

        if (
            password.value.length < 8
            || password.value.length > 72
        ) {
            invalidField = password;
            message = 'A senha deve ter entre 8 e 72 caracteres.';
        } else if (!/[A-Za-z]/.test(password.value)) {
            invalidField = password;
            message = 'A senha deve conter pelo menos uma letra.';
        } else if (!/[0-9]/.test(password.value)) {
            invalidField = password;
            message = 'A senha deve conter pelo menos um número.';
        } else if (password.value !== confirmation.value) {
            invalidField = confirmation;
            message = 'A confirmação da senha não corresponde.';
        }

        clearFieldValidation(password);
        clearFieldValidation(confirmation);

        if (!message || !invalidField) {
            showFormError(errorId, '');

            return true;
        }

        event.preventDefault();
        invalidField.setCustomValidity(message);
        invalidField.classList.add('is-invalid');
        showFormError(errorId, message);
        invalidField.focus();
        invalidField.reportValidity();

        return false;
    }

    function bindPasswordValidation(
        form,
        passwordId,
        confirmationId,
        errorId
    ) {
        if (!form) {
            return;
        }

        const password = field(passwordId);
        const confirmation = field(confirmationId);
        const inputs = [password, confirmation].filter(Boolean);

        inputs.forEach(function (input) {
            input.addEventListener('input', function () {
                clearPasswordValidation(errorId, inputs);
            });
        });

        form.addEventListener('submit', function (event) {
            validatePasswordPair(
                event,
                password,
                confirmation,
                errorId
            );
        });
    }

    function positionUserActionMenu(dropdown) {
        const button = dropdown.querySelector('[data-bs-toggle="dropdown"]');
        const menu = dropdown._userActionMenu
            || dropdown.querySelector('.dropdown-menu');

        if (!button || !menu) {
            return;
        }

        dropdown._userActionMenu = menu;
        menu._userActionDropdown = dropdown;

        if (!menu._userActionPlaceholder) {
            const placeholder = document.createComment(
                'user action menu'
            );

            dropdown.appendChild(placeholder);
            menu._userActionPlaceholder = placeholder;
        }

        if (menu.parentElement !== document.body) {
            document.body.appendChild(menu);
        }

        const buttonRect = button.getBoundingClientRect();

        menu.style.position = 'fixed';
        menu.style.inset = 'auto';
        menu.style.transform = 'none';
        menu.style.zIndex = '1060';
        menu.style.visibility = 'hidden';
        menu.style.display = 'block';

        const menuRect = menu.getBoundingClientRect();
        const viewportPadding = 12;
        const left = Math.max(
            viewportPadding,
            Math.min(
                buttonRect.right - menuRect.width,
                window.innerWidth - menuRect.width - viewportPadding
            )
        );

        const tableWrap = button.closest('.table-panel-wrap');
        const tableRect = tableWrap
            ? tableWrap.getBoundingClientRect()
            : null;
        const topAboveTable = tableRect
            ? tableRect.top - menuRect.height - 8
            : buttonRect.top - menuRect.height - 8;
        const topAboveButton = buttonRect.top - menuRect.height - 8;
        const topBelowButton = buttonRect.bottom + 8;
        const top = topAboveTable >= viewportPadding
            ? topAboveTable
            : (
                topAboveButton >= viewportPadding
                    ? topAboveButton
                    : Math.min(
                        topBelowButton,
                        window.innerHeight
                            - menuRect.height
                            - viewportPadding
                    )
            );

        menu.style.left = left + 'px';
        menu.style.top = Math.max(viewportPadding, top) + 'px';
        menu.style.visibility = '';
    }

    function resetUserActionMenu(dropdown) {
        const menu = dropdown._userActionMenu
            || document.body.querySelector(
                '.dropdown-menu[data-user-action-open="1"]'
            )
            || dropdown.querySelector('.dropdown-menu');

        if (!menu) {
            return;
        }

        menu.style.position = '';
        menu.style.inset = '';
        menu.style.transform = '';
        menu.style.zIndex = '';
        menu.style.visibility = '';
        menu.style.display = '';
        menu.style.left = '';
        menu.style.top = '';
        delete menu.dataset.userActionOpen;

        if (menu._userActionPlaceholder) {
            menu._userActionPlaceholder.replaceWith(menu);
            delete menu._userActionPlaceholder;
        }

        delete menu._userActionDropdown;
        delete dropdown._userActionMenu;
    }

    document
        .querySelectorAll('.users-table .dropdown')
        .forEach(function (dropdown) {
            dropdown.addEventListener('shown.bs.dropdown', function () {
                const menu = dropdown._userActionMenu
                    || dropdown.querySelector('.dropdown-menu');

                if (menu) {
                    menu.dataset.userActionOpen = '1';
                }

                positionUserActionMenu(dropdown);
            });

            dropdown.addEventListener('hidden.bs.dropdown', function () {
                resetUserActionMenu(dropdown);
            });
        });

    window.addEventListener('resize', function () {
        document
            .querySelectorAll(
                '.dropdown-menu[data-user-action-open="1"], '
                + '.users-table .dropdown .dropdown-menu.show'
            )
            .forEach(function (menu) {
                const dropdown = menu._userActionDropdown
                    || menu.closest('.dropdown');

                if (dropdown) {
                    positionUserActionMenu(dropdown);
                }
            });
    });

    window.addEventListener(
        'scroll',
        function () {
            document
                .querySelectorAll(
                    '.dropdown-menu[data-user-action-open="1"], '
                    + '.users-table .dropdown .dropdown-menu.show'
                )
                .forEach(function (menu) {
                    const dropdown = menu._userActionDropdown
                        || menu.closest('.dropdown');

                    if (dropdown) {
                        positionUserActionMenu(dropdown);
                    }
                });
        },
        true
    );

    document
        .querySelectorAll('.js-user-view')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                text(
                    'view-user-subtitle',
                    'ID #' + (button.dataset.userId || '')
                );

                text(
                    'view-user-name',
                    button.dataset.userName
                );

                text(
                    'view-user-username',
                    '@' + (button.dataset.userUsername || '')
                );

                text(
                    'view-user-email',
                    button.dataset.userEmail
                );

                text(
                    'view-user-phone',
                    button.dataset.userPhone || 'Sem telefone'
                );

                text(
                    'view-user-profile',
                    button.dataset.userProfile
                );

                text(
                    'view-user-status',
                    button.dataset.userStatusLabel
                );

                text(
                    'view-user-password-status',
                    button.dataset.userMustChange === '1'
                        ? 'Alteração obrigatória pendente'
                        : 'Senha definida'
                );

                text(
                    'view-user-failed-attempts',
                    button.dataset.userFailedAttempts || '0'
                );

                text(
                    'view-user-locked-until',
                    button.dataset.userLockedUntil
                );

                text(
                    'view-user-last-access',
                    button.dataset.userLastAccess
                );

                text(
                    'view-user-created-at',
                    button.dataset.userCreatedAt
                );
            });
        });

    document
        .querySelectorAll('.js-user-edit')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                const id = document.getElementById(
                    'edit-user-id'
                );

                const name = document.getElementById(
                    'edit-user-name'
                );

                const username = document.getElementById(
                    'edit-user-username'
                );

                const email = document.getElementById(
                    'edit-user-email'
                );

                const phone = document.getElementById(
                    'edit-user-phone'
                );

                const profile = document.getElementById(
                    'edit-user-profile'
                );

                const status = document.getElementById(
                    'edit-user-status'
                );

                const mustChange = document.getElementById(
                    'edit-user-must-change'
                );

                if (id) {
                    id.value = button.dataset.userId || '';
                }

                if (name) {
                    name.value = button.dataset.userName || '';
                }

                if (username) {
                    username.value =
                        button.dataset.userUsername || '';
                }

                if (email) {
                    email.value =
                        button.dataset.userEmail || '';
                }

                if (phone) {
                    phone.value =
                        button.dataset.userPhone || '';
                }

                if (profile) {
                    profile.value =
                        button.dataset.userProfileId || '';
                }

                if (status) {
                    status.value =
                        button.dataset.userStatus || 'ativo';
                }

                if (mustChange) {
                    mustChange.checked =
                        button.dataset.userMustChange === '1';
                }

                showFormError('edit-user-form-error', '');

                text(
                    'edit-user-subtitle',
                    button.dataset.userName
                );
            });
        });

    document
        .querySelectorAll('.js-user-reset-password')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                const id = document.getElementById(
                    'password-user-id'
                );

                if (id) {
                    id.value = button.dataset.userId || '';
                }

                const name = document.getElementById(
                    'password-user-name'
                );

                if (name) {
                    name.value = button.dataset.userName || '';
                }

                const password = document.getElementById(
                    'password-user-new'
                );

                const confirmation = document.getElementById(
                    'password-user-confirmation'
                );

                if (password) {
                    password.value = '';
                }

                if (confirmation) {
                    confirmation.value = '';
                }

                clearPasswordValidation(
                    'password-user-form-error',
                    [password, confirmation].filter(Boolean)
                );

                text(
                    'password-user-subtitle',
                    button.dataset.userName
                );
            });
        });

    const statusConfiguration = {
        ativo: {
            title: 'Ativar usuário',
            message:
                'O usuário voltará a ter acesso ao sistema.',
            button: 'Ativar usuário'
        },
        inativo: {
            title: 'Desativar usuário',
            message:
                'O usuário ficará sem acesso até ser ativado novamente.',
            button: 'Desativar usuário'
        },
        bloqueado: {
            title: 'Bloquear usuário',
            message:
                'O usuário será impedido de acessar o sistema.',
            button: 'Bloquear usuário'
        },
        desbloquear: {
            title: 'Remover bloqueio temporário',
            message:
                'As tentativas falhas serão zeradas e o bloqueio temporário será removido.',
            button: 'Remover bloqueio'
        }
    };

    document
        .querySelectorAll('.js-user-status')
        .forEach(function (button) {
            button.addEventListener('click', function () {
                const operation =
                    button.dataset.userOperation || '';

                const configuration =
                    statusConfiguration[operation];

                const id = document.getElementById(
                    'status-user-id'
                );

                const operationInput =
                    document.getElementById(
                        'status-user-operation'
                    );

                const submit =
                    document.getElementById(
                        'status-user-submit'
                    );

                if (id) {
                    id.value = button.dataset.userId || '';
                }

                if (operationInput) {
                    operationInput.value = operation;
                }

                text(
                    'modal-usuario-status-title',
                    configuration
                        ? configuration.title
                        : 'Alterar status'
                );

                text(
                    'status-user-subtitle',
                    button.dataset.userName
                );

                text(
                    'status-user-message',
                    configuration
                        ? configuration.message
                        : 'Confirme a operação.'
                );

                if (submit) {
                    submit.textContent = configuration
                        ? configuration.button
                        : 'Confirmar';
                }
            });
        });

    const createModal = document.getElementById(
        'modal-usuario'
    );

    if (createModal) {
        const createForm = createModal.querySelector('form');

        bindPasswordValidation(
            createForm,
            'create-user-password',
            'create-user-password-confirmation',
            'create-user-form-error'
        );

        createModal.addEventListener(
            'show.bs.modal',
            function (event) {
                if (!event.relatedTarget) {
                    return;
                }

                if (createForm) {
                    createForm.reset();
                }

                clearPasswordValidation(
                    'create-user-form-error',
                    [
                        field('create-user-password'),
                        field('create-user-password-confirmation')
                    ].filter(Boolean)
                );
            }
        );

        createModal.addEventListener(
            'hidden.bs.modal',
            function () {
                if (createForm) {
                    createForm.reset();
                }
            }
        );
    }

    const passwordModal = document.getElementById(
        'modal-usuario-password'
    );

    if (passwordModal) {
        const passwordForm = passwordModal.querySelector('form');

        bindPasswordValidation(
            passwordForm,
            'password-user-new',
            'password-user-confirmation',
            'password-user-form-error'
        );

        passwordModal.addEventListener(
            'hidden.bs.modal',
            function () {
                if (passwordForm) {
                    passwordForm.reset();
                }
            }
        );
    }

    const recoveryTargets = {
        create: 'modal-usuario',
        edit: 'modal-usuario-edit',
        password: 'modal-usuario-password'
    };

    if (
        userFormRecoveryModal
        && recoveryTargets[userFormRecoveryModal]
        && window.bootstrap
    ) {
        const modalElement = document.getElementById(
            recoveryTargets[userFormRecoveryModal]
        );

        if (modalElement) {
            bootstrap.Modal
                .getOrCreateInstance(modalElement)
                .show();
        }
    }
});
</script>
