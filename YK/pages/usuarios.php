<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$userService = $application->userManagement();

$filters = [
    'search' => trim(
        (string) ($_GET['search'] ?? '')
    ),
    'status' => (string) (
        $_GET['status'] ?? ''
    ),
    'profile_id' => (int) (
        $_GET['profile_id'] ?? 0
    ),
];

$users = $userService->listUsers($filters);
$summary = $userService->userSummary();
$profiles = $userService->activeProfiles();

$canCreate = $authorization->can(
    'usuario.criar'
);

$canEdit = $authorization->can(
    'usuario.editar'
);

$canActivate = $authorization->can(
    'usuario.ativar'
);

$canBlock = $authorization->can(
    'usuario.bloquear'
);

$canDeactivate = $authorization->can(
    'usuario.desativar'
);

$canResetPassword = $authorization->can(
    'usuario.redefinir_senha'
);

/**
 * Formata datas armazenadas no banco.
 */
function user_date(?string $value): string
{
    if (
        $value === null
        || trim($value) === ''
    ) {
        return 'Nunca';
    }

    try {
        return (
            new DateTimeImmutable($value)
        )->format('d/m/Y H:i');
    } catch (Throwable) {
        return '-';
    }
}

/**
 * Retorna o texto visual do status.
 */
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

/**
 * Retorna a classe visual do status.
 */
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

/**
 * Renderiza um badge de status.
 */
function user_status_badge(
    string $status,
    bool $temporaryLocked
): string {
    $label = user_status_label(
        $status,
        $temporaryLocked
    );

    $class = user_status_class(
        $status,
        $temporaryLocked
    );

    return sprintf(
        '<span class="badge-soft badge-%s">%s</span>',
        h($class),
        h($label)
    );
}
?>

<div class="page-body users-page">

    <?php
    metric_grid([
        [
            'Total de usuários',
            (string) $summary['total'],
            'bi-people',
            '#2563EB',
            'cadastrados',
        ],
        [
            'Usuários ativos',
            (string) $summary['active'],
            'bi-person-check',
            '#16A34A',
            'com acesso permitido',
        ],
        [
            'Usuários inativos',
            (string) $summary['inactive'],
            'bi-person-dash',
            '#D97706',
            'sem acesso',
        ],
        [
            'Usuários bloqueados',
            (string) $summary['blocked'],
            'bi-person-lock',
            '#DC2626',
            'bloqueados manualmente',
        ],
        [
            'Bloqueios temporários',
            (string) $summary['temporary_locked'],
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
                    ? ' selected'
                    : '' ?>
            >
                Ativos
            </option>

            <option
                value="inativo"
                <?= $filters['status'] === 'inativo'
                    ? ' selected'
                    : '' ?>
            >
                Inativos
            </option>

            <option
                value="bloqueado"
                <?= $filters['status'] === 'bloqueado'
                    ? ' selected'
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
                        ? ' selected'
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
                <a
                    class="btn-filter btn-filter-primary"
                    href="usuario-formulario.php"
                >
                    <i class="bi bi-person-plus"></i>
                    Novo usuário
                </a>
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
                                $currentUser->id()
                                === $user->id()
                            );

                            $temporaryLocked =
                                $user->isTemporarilyLocked();
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
                                    <?php
                                    if (
                                        $user->mustChangePassword()
                                    ):
                                    ?>
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
                                                <a
                                                    class="dropdown-item"
                                                    href="usuario-formulario.php?id=<?= h((string) $user->id()) ?>&view=1"
                                                >
                                                    <i class="bi bi-eye"></i>
                                                    Visualizar
                                                </a>
                                            </li>

                                            <?php if ($canEdit): ?>
                                                <li>
                                                    <a
                                                        class="dropdown-item"
                                                        href="usuario-formulario.php?id=<?= h((string) $user->id()) ?>"
                                                    >
                                                        <i
                                                            class="bi bi-pencil"
                                                        ></i>
                                                        Editar
                                                    </a>
                                                </li>
                                            <?php endif; ?>

                                            <?php
                                            if ($canResetPassword):
                                            ?>
                                                <li>
                                                    <a
                                                        class="dropdown-item"
                                                        href="usuario-redefinir-senha.php?id=<?= h((string) $user->id()) ?>"
                                                    >
                                                        <i
                                                            class="bi bi-key"
                                                        ></i>
                                                        Redefinir senha
                                                    </a>
                                                </li>
                                            <?php endif; ?>

                                            <?php
                                            if (
                                                $canActivate
                                                || $canBlock
                                                || $canDeactivate
                                            ):
                                            ?>
                                                <li>
                                                    <hr
                                                        class="dropdown-divider"
                                                    >
                                                </li>

                                                <li>
                                                    <span
                                                        class="dropdown-item-text small text-muted"
                                                    >
                                                        Alteração de status será ativada na próxima etapa.
                                                    </span>
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