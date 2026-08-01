<?php

declare(strict_types=1);

$page = max(
    1,
    (int) ($_GET['page'] ?? 1)
);

$search = trim(
    (string) ($_GET['q'] ?? '')
);

$status = trim(
    (string) ($_GET['status'] ?? '')
);

$result = $application
    ->adminCompanies()
    ->list(
        [
            'search' => $search,
            'status' => $status,
        ],
        $page,
        20
    );

$schemaReady = $application
    ->adminCompanies()
    ->ready();

$adminContent = static function () use (
    $result,
    $page,
    $search,
    $status,
    $schemaReady,
    $csrf
): void {
    ?>
    <section class="admin-panel">
        <?php if (!$schemaReady): ?>
            <div
                class="alert alert-danger"
                role="alert"
            >
                A estrutura administrativa do banco ainda não foi
                atualizada. Verifique as migrations administrativas.
            </div>
        <?php endif; ?>

        <div class="admin-panel-heading">
            <form
                class="admin-filters"
                method="get"
            >
                <label
                    class="visually-hidden"
                    for="company-search"
                >
                    Buscar empresa
                </label>

                <input
                    id="company-search"
                    name="q"
                    value="<?= admin_h($search) ?>"
                    placeholder="Buscar empresa"
                >

                <label
                    class="visually-hidden"
                    for="company-status"
                >
                    Status
                </label>

                <select
                    id="company-status"
                    name="status"
                >
                    <option value="">
                        Todos os status
                    </option>

                    <?php foreach (
                        [
                            'pendente' => 'Pendente',
                            'ativo' => 'Ativa',
                            'inativo' => 'Inativa',
                            'bloqueado' => 'Bloqueada',
                        ]
                        as $statusKey => $statusLabel
                    ): ?>
                        <option
                            value="<?= admin_h($statusKey) ?>"
                            <?= $status === $statusKey
                                ? 'selected'
                                : '' ?>
                        >
                            <?= admin_h($statusLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Filtrar
                </button>
            </form>

            <a
                class="btn btn-primary"
                href="<?= admin_url(
                    'empresa.php?nova=1'
                ) ?>"
            >
                <i class="bi bi-plus-lg"></i>
                Nova empresa
            </a>
        </div>

        <?php if ($result['items'] === []): ?>
            <?php
            admin_empty(
                'Nenhuma empresa encontrada',
                'Ajuste os filtros ou cadastre uma nova empresa.'
            );
            ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table os-table">
                    <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Documento</th>
                        <th>Segmento</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                    </thead>

                    <tbody>
                    <?php foreach (
                        $result['items']
                        as $company
                    ): ?>
                        <?php
                        $companyId = (int) (
                            $company['id']
                            ?? 0
                        );

                        $companyName = trim(
                            (string) (
                                $company['nome_fantasia']
                                ?? ''
                            )
                        );

                        if ($companyName === '') {
                            $companyName = trim(
                                (string) (
                                    $company['razao_social']
                                    ?? ''
                                )
                            );
                        }

                        if ($companyName === '') {
                            $companyName = 'Empresa';
                        }

                        $companyStatus = (string) (
                            $company['status']
                            ?? 'pendente'
                        );

                        /*
                         * Suporte pode entrar em empresa ativa,
                         * pendente ou inativa.
                         *
                         * Empresa bloqueada não pode ser acessada.
                         */
                        $canOpenOperationalPanel =
                            $companyStatus !== 'bloqueado';
                        ?>

                        <tr>
                            <td>
                                <?= admin_h($companyName) ?>
                            </td>

                            <td>
                                <?= admin_h(
                                    $company['documento']
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <?= admin_h(
                                    $company['segmento']
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <?= admin_badge(
                                    $companyStatus
                                ) ?>
                            </td>

                            <td>
                                <div
                                    class="dropdown table-action-dropdown"
                                >
                                    <button
                                        class="btn-action"
                                        type="button"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                        aria-label="Ações da empresa <?= admin_h(
                                            $companyName
                                        ) ?>"
                                    >
                                        <i class="bi bi-three-dots"></i>
                                    </button>

                                    <ul
                                        class="dropdown-menu dropdown-menu-end"
                                    >
                                        <li>
                                            <a
                                                class="dropdown-item"
                                                href="<?= admin_url(
                                                    'empresa.php?id='
                                                    . $companyId
                                                ) ?>"
                                            >
                                                <i
                                                    class="bi bi-pencil-square"
                                                ></i>

                                                Visualizar e editar
                                            </a>
                                        </li>

                                        <li>
                                            <hr
                                                class="dropdown-divider"
                                            >
                                        </li>

                                        <li>
                                            <?php if (
                                                $canOpenOperationalPanel
                                            ): ?>
                                                <form
                                                    method="post"
                                                    action="<?= admin_url(
                                                        'actions/empresa-entrar.php'
                                                    ) ?>"
                                                    class="dropdown-item-form"
                                                    data-admin-direct-access
                                                >
                                                    <?= $csrf->field() ?>

                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value="<?= $companyId ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="dropdown-item"
                                                    >
                                                        <i
                                                            class="bi bi-box-arrow-in-right"
                                                        ></i>

                                                        Entrar no painel
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button
                                                    type="button"
                                                    class="dropdown-item"
                                                    disabled
                                                    aria-disabled="true"
                                                    title="Reative a empresa antes de acessar"
                                                >
                                                    <i
                                                        class="bi bi-lock"
                                                    ></i>

                                                    Empresa bloqueada
                                                </button>
                                            <?php endif; ?>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            admin_pagination(
                $page,
                (int) $result['total'],
                20,
                'empresas.php',
                [
                    'q' => $search,
                    'status' => $status,
                ]
            );
            ?>
        <?php endif; ?>
    </section>
    <?php
};