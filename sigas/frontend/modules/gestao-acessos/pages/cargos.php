<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Repositories\CargoRepository;

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

$repository = new CargoRepository(Database::connection());
$schemaReady = $repository->schemaReady();
$rows = $schemaReady ? $repository->all() : [];
$active = 0;
$inactive = 0;
$linkedUsers = 0;

foreach ($rows as $row) {
    if ((int) ($row['ativo'] ?? 0) === 1) {
        $active++;
    } else {
        $inactive++;
    }
    $linkedUsers += (int) ($row['usuarios'] ?? 0);
}

$pageExtraStyles[] = 'assets/css/modules/gestao-acessos-users.css';
$pageExtraScripts[] = 'assets/js/modules/gestao-acessos-cargos.js';
$openNewModal = isset($_GET['novo']) && (string) $_GET['novo'] === '1';

ob_start();
?>
<?php if (!$schemaReady): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-database-exclamation mt-1"></i>
        <div>
            <strong>Catálogo de cargos ainda não inicializado.</strong>
            <div>Execute a migração de Governança de Cargos antes de cadastrar usuários. Até lá, o sistema não aceitará cargo digitado manualmente.</div>
        </div>
    </div>
<?php endif; ?>

<div class="alert alert-info d-flex align-items-start gap-2" role="status">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        <strong>Cargo e nível de acesso são informações diferentes.</strong>
        <div>O cargo representa a função institucional do usuário e não concede permissões. O nível de acesso é definido separadamente em Perfis e níveis e aplicado durante a aprovação do usuário.</div>
    </div>
</div>

<section class="content-card frontend-data-card" data-governance-cargos>
    <div class="card-heading">
        <div>
            <div class="card-kicker">Governança</div>
            <h2>Catálogo de cargos e funções</h2>
            <p>Cadastre funções institucionais uma única vez e reutilize nos usuários, sem misturar cargo com nível de acesso.</p>
        </div>
        <?php if ($schemaReady): ?>
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#governanceCargoModal" data-cargo-new>
                <i class="bi bi-plus-circle"></i> Novo cargo
            </button>
        <?php endif; ?>
    </div>

    <?php if ($schemaReady): ?>
        <div class="table-responsive">
            <table class="data-table align-middle">
                <thead>
                    <tr>
                        <th>Cargo / função</th>
                        <th>Identificador</th>
                        <th>Descrição</th>
                        <th>Usuários</th>
                        <th>Situação</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-secondary">
                                Nenhum cargo cadastrado. Cadastre o primeiro cargo para liberar o cadastro de usuários.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $cargo): ?>
                            <?php
                                $id = (int) ($cargo['id'] ?? 0);
                                $isActive = (int) ($cargo['ativo'] ?? 0) === 1;
                                $description = trim((string) ($cargo['descricao'] ?? ''));
                            ?>
                            <tr>
                                <td><strong><?= sigas_frontend_escape((string) ($cargo['nome'] ?? 'Cargo')) ?></strong></td>
                                <td><code><?= sigas_frontend_escape((string) ($cargo['slug'] ?? '')) ?></code></td>
                                <td><?= sigas_frontend_escape($description !== '' ? $description : 'Sem descrição') ?></td>
                                <td><?= (int) ($cargo['usuarios'] ?? 0) ?></td>
                                <td>
                                    <span class="status-badge <?= $isActive ? 'status-success' : 'status-neutral' ?>">
                                        <?= $isActive ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <button
                                            class="btn btn-light btn-sm"
                                            type="button"
                                            data-cargo-edit
                                            data-id="<?= $id ?>"
                                            data-name="<?= sigas_frontend_escape((string) ($cargo['nome'] ?? '')) ?>"
                                            data-description="<?= sigas_frontend_escape($description) ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#governanceCargoModal"
                                        >
                                            <i class="bi bi-pencil"></i> Editar
                                        </button>
                                        <button
                                            class="btn <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm"
                                            type="button"
                                            data-cargo-toggle
                                            data-id="<?= $id ?>"
                                            data-action="<?= $isActive ? 'deactivate' : 'activate' ?>"
                                            data-name="<?= sigas_frontend_escape((string) ($cargo['nome'] ?? '')) ?>"
                                        >
                                            <i class="bi bi-<?= $isActive ? 'pause-circle' : 'play-circle' ?>"></i>
                                            <?= $isActive ? 'Inativar' : 'Ativar' ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($schemaReady): ?>
<div
    class="modal fade ga-user-admin-modal"
    id="governanceCargoModal"
    tabindex="-1"
    aria-labelledby="governanceCargoModalTitle"
    aria-hidden="true"
    data-auto-open="<?= $openNewModal ? '1' : '0' ?>"
>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form data-governance-cargo-form novalidate>
                <div class="modal-header">
                    <div>
                        <div class="eyebrow"><i class="bi bi-person-badge"></i> Governança</div>
                        <h2 class="modal-title fs-5" id="governanceCargoModalTitle" data-cargo-modal-title>Novo cargo</h2>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= sigas_frontend_escape(Csrf::token('governance-cargo')) ?>">
                    <input type="hidden" name="acao" value="create" data-cargo-action>
                    <input type="hidden" name="cargo_id" value="" data-cargo-id>
                    <div class="alert d-none" role="alert" data-governance-cargo-alert></div>

                    <div class="mb-3">
                        <label class="form-label" for="governanceCargoName">Cargo / função *</label>
                        <input class="form-control" id="governanceCargoName" name="nome" type="text" minlength="2" maxlength="120" required data-cargo-name>
                        <div class="form-text">Use a função institucional completa, por exemplo: Assistente Social, Coordenador(a), Operador(a) de Sistemas.</div>
                    </div>
                    <div>
                        <label class="form-label" for="governanceCargoDescription">Descrição</label>
                        <textarea class="form-control" id="governanceCargoDescription" name="descricao" rows="3" maxlength="255" data-cargo-description></textarea>
                        <div class="form-text">A descrição do cargo é informativa e não altera permissões de acesso.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-check2-circle"></i> <span data-cargo-submit-label>Cadastrar cargo</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php
$pageCustomContent = (string) ob_get_clean();

return sigas_frontend_page([
    'title' => 'Cargos',
    'description' => 'Catálogo institucional de cargos e funções. Cargos não concedem nível ou permissão de acesso.',
    'actions' => $schemaReady ? [
        [
            'label' => 'Novo cargo',
            'icon' => 'plus-circle',
            'primary' => true,
            'href' => 'governanca-acessos/cargos.php?novo=1',
        ],
        [
            'label' => 'Novo usuário',
            'icon' => 'person-plus',
            'href' => 'governanca-acessos/novo-usuario.php',
        ],
    ] : [],
    'stats' => [
        ['label' => 'Cargos cadastrados', 'value' => (string) count($rows), 'detail' => 'Catálogo institucional', 'icon' => 'person-badge'],
        ['label' => 'Ativos', 'value' => (string) $active, 'detail' => 'Disponíveis para usuários', 'icon' => 'check-circle'],
        ['label' => 'Inativos', 'value' => (string) $inactive, 'detail' => 'Fora de novas atribuições', 'icon' => 'pause-circle'],
        ['label' => 'Usuários vinculados', 'value' => (string) $linkedUsers, 'detail' => 'Contas com cargo catalogado', 'icon' => 'people'],
    ],
    'filters' => [],
    'blocks' => [],
    'demo' => false,
    'show_states' => false,
]);
