<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

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

                                <option value="inativo">
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
                            checked
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
                    id="edit-user-id"
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
                                <option value="ativo">
                                    Ativo
                                </option>

                                <option value="inativo">
                                    Inativo
                                </option>

                                <option value="bloqueado">
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
                    id="password-user-id"
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
                        checked
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

    function text(id, value) {
        const element = document.getElementById(id);

        if (element) {
            element.textContent = value || '-';
        }
    }

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
                    'view-userLockedUntil',
                    button.dataset.userLockedUntil
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
        createModal.addEventListener(
            'hidden.bs.modal',
            function () {
                const form =
                    createModal.querySelector('form');

                if (form) {
                    form.reset();
                }
            }
        );
    }

    const passwordModal = document.getElementById(
        'modal-usuario-password'
    );

    if (passwordModal) {
        passwordModal.addEventListener(
            'hidden.bs.modal',
            function () {
                const form =
                    passwordModal.querySelector('form');

                if (form) {
                    form.reset();
                }
            }
        );
    }
});
</script>
