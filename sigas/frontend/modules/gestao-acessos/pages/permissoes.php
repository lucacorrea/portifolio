<?php

declare(strict_types=1);

use App\Core\Csrf;

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

/** @var \App\Services\GovernancePermissionService $permissionService */
$permissionService = require dirname(__DIR__) . '/permissions-bootstrap.php';
$data = $permissionService->page();
$modules = is_array($data['modules'] ?? null) ? $data['modules'] : [];
$operational = is_array($data['operational'] ?? null) ? $data['operational'] : [];
$legacy = is_array($data['legacy'] ?? null) ? $data['legacy'] : [];
$openNewModal = isset($_GET['novo']) && (string) $_GET['novo'] === '1';

$pageExtraStyles[] = 'assets/css/modules/gestao-acessos-users.css';
$pageExtraScripts[] = 'assets/js/modules/gestao-acessos-permissions.js';

ob_start();
?>
<div class="alert alert-info d-flex align-items-start gap-2" role="status">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        <strong>Permissão é uma regra de autorização, não uma funcionalidade nova.</strong>
        <div>
            Criar uma permissão adiciona o slug ao catálogo e permite vinculá-lo aos níveis. A funcionalidade correspondente só será realmente protegida quando o código consultar esse slug. Depois de criada, a permissão preserva <strong>slug e módulo imutáveis</strong> para evitar quebra silenciosa de autorização.
        </div>
    </div>
</div>

<section class="content-card frontend-data-card" data-governance-permissions>
    <div class="card-heading">
        <div>
            <div class="card-kicker">Catálogo operacional</div>
            <h2>Permissões dos módulos</h2>
            <p>Crie regras, ajuste descrições e controle a disponibilidade sem excluir vínculos históricos.</p>
        </div>
        <button class="btn btn-primary" type="button" data-permission-new data-bs-toggle="modal" data-bs-target="#governancePermissionModal">
            <i class="bi bi-plus-circle"></i> Nova permissão
        </button>
    </div>

    <div class="table-responsive">
        <table class="data-table align-middle">
            <thead>
                <tr>
                    <th>Permissão</th>
                    <th>Slug</th>
                    <th>Módulo</th>
                    <th>Níveis</th>
                    <th>Situação</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($operational === []): ?>
                    <tr><td colspan="6" class="text-center py-5 text-secondary">Nenhuma permissão operacional cadastrada.</td></tr>
                <?php else: ?>
                    <?php foreach ($operational as $permission): ?>
                        <?php
                            $id = (int) ($permission['id'] ?? 0);
                            $active = (bool) ($permission['active'] ?? false);
                            $protected = (bool) ($permission['protected'] ?? false);
                            $description = trim((string) ($permission['description'] ?? ''));
                            $levels = trim((string) ($permission['levels'] ?? ''));
                            $slug = (string) ($permission['slug'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <strong><?= sigas_frontend_escape((string) ($permission['name'] ?? 'Permissão')) ?></strong>
                                <div class="small text-secondary mt-1"><?= sigas_frontend_escape($description !== '' ? $description : 'Sem descrição') ?></div>
                            </td>
                            <td><code><?= sigas_frontend_escape($slug) ?></code></td>
                            <td><?= sigas_frontend_escape((string) ($permission['module_label'] ?? 'Módulo')) ?></td>
                            <td>
                                <strong><?= (int) ($permission['levels_count'] ?? 0) ?></strong>
                                <div class="small text-secondary"><?= sigas_frontend_escape($levels !== '' ? $levels : 'Nenhum nível vinculado') ?></div>
                            </td>
                            <td>
                                <span class="status-badge <?= $active ? 'status-success' : 'status-neutral' ?>">
                                    <?= $active ? 'Ativa' : 'Inativa' ?>
                                </span>
                                <?php if ($protected): ?>
                                    <div class="small text-secondary mt-1"><i class="bi bi-shield-lock"></i> Estrutural</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                                    <button
                                        class="btn btn-light btn-sm"
                                        type="button"
                                        data-permission-edit
                                        data-id="<?= $id ?>"
                                        data-name="<?= sigas_frontend_escape((string) ($permission['name'] ?? '')) ?>"
                                        data-description="<?= sigas_frontend_escape($description) ?>"
                                        data-module="<?= sigas_frontend_escape((string) ($permission['module'] ?? '')) ?>"
                                        data-module-label="<?= sigas_frontend_escape((string) ($permission['module_label'] ?? '')) ?>"
                                        data-slug="<?= sigas_frontend_escape($slug) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#governancePermissionModal"
                                    >
                                        <i class="bi bi-pencil"></i> Editar
                                    </button>

                                    <?php if (!$protected || !$active): ?>
                                        <button
                                            class="btn <?= $active ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm"
                                            type="button"
                                            data-permission-toggle
                                            data-id="<?= $id ?>"
                                            data-action="<?= $active ? 'deactivate' : 'activate' ?>"
                                            data-name="<?= sigas_frontend_escape((string) ($permission['name'] ?? '')) ?>"
                                            data-slug="<?= sigas_frontend_escape($slug) ?>"
                                            data-is-view="<?= str_ends_with($slug, '.visualizar') ? '1' : '0' ?>"
                                        >
                                            <i class="bi bi-<?= $active ? 'pause-circle' : 'play-circle' ?>"></i>
                                            <?= $active ? 'Inativar' : 'Ativar' ?>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-light btn-sm" type="button" disabled title="Permissão estrutural protegida contra inativação">
                                            <i class="bi bi-shield-lock"></i> Protegida
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="content-card frontend-data-card mt-3">
    <div class="card-heading">
        <div>
            <div class="card-kicker">Compatibilidade</div>
            <h2>Permissões internas e legadas</h2>
            <p>Continuam no banco para preservar funções históricas, porém não podem ser alteradas por esta tela.</p>
        </div>
        <span class="badge text-bg-secondary">Somente leitura</span>
    </div>

    <div class="table-responsive">
        <table class="data-table align-middle">
            <thead>
                <tr>
                    <th>Permissão</th>
                    <th>Slug</th>
                    <th>Área interna</th>
                    <th>Níveis</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($legacy === []): ?>
                    <tr><td colspan="5" class="text-center py-4 text-secondary">Nenhuma permissão interna ou legada.</td></tr>
                <?php else: ?>
                    <?php foreach ($legacy as $permission): ?>
                        <tr>
                            <td><strong><?= sigas_frontend_escape((string) ($permission['name'] ?? 'Permissão')) ?></strong></td>
                            <td><code><?= sigas_frontend_escape((string) ($permission['slug'] ?? '')) ?></code></td>
                            <td><?= sigas_frontend_escape((string) ($permission['module_label'] ?? 'Interna')) ?></td>
                            <td><?= sigas_frontend_escape((string) (($permission['levels'] ?? '') !== '' ? $permission['levels'] : 'Nenhum nível')) ?></td>
                            <td>
                                <span class="status-badge <?= (bool) ($permission['active'] ?? false) ? 'status-success' : 'status-neutral' ?>">
                                    <?= (bool) ($permission['active'] ?? false) ? 'Ativa' : 'Inativa' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div
    class="modal fade ga-user-admin-modal"
    id="governancePermissionModal"
    tabindex="-1"
    aria-labelledby="governancePermissionModalTitle"
    aria-hidden="true"
    data-auto-open="<?= $openNewModal ? '1' : '0' ?>"
>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form data-governance-permission-form novalidate>
                <div class="modal-header">
                    <div>
                        <div class="eyebrow"><i class="bi bi-key"></i> Governança</div>
                        <h2 class="modal-title fs-5" id="governancePermissionModalTitle" data-permission-modal-title>Nova permissão</h2>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= sigas_frontend_escape(Csrf::token('governance-permission')) ?>">
                    <input type="hidden" name="acao" value="create" data-permission-action>
                    <input type="hidden" name="permissao_id" value="" data-permission-id>
                    <div class="alert d-none" role="alert" data-governance-permission-alert></div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="governancePermissionName">Nome da permissão *</label>
                            <input class="form-control" id="governancePermissionName" name="nome" type="text" minlength="3" maxlength="160" required data-permission-name>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="governancePermissionModule">Módulo *</label>
                            <select class="form-select" id="governancePermissionModule" name="modulo" required data-permission-module>
                                <option value="">Selecione o módulo</option>
                                <?php foreach ($modules as $moduleKey => $moduleLabel): ?>
                                    <option value="<?= sigas_frontend_escape((string) $moduleKey) ?>"><?= sigas_frontend_escape((string) $moduleLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">O módulo fica imutável após a criação.</div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <label class="form-label" for="governancePermissionActionKey">Identificador da ação *</label>
                            <input class="form-control" id="governancePermissionActionKey" name="acao_chave" type="text" minlength="2" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" required placeholder="ex.: exportar" data-permission-action-key>
                            <div class="form-text">Use letras minúsculas, números e underline. Também fica imutável.</div>
                        </div>
                        <div class="col-12">
                            <div class="ga-governance-note">
                                <i class="bi bi-code-slash"></i>
                                <div>
                                    <strong>Slug resultante</strong>
                                    <code data-permission-slug-preview>selecione_modulo.acao</code>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="governancePermissionDescription">Descrição</label>
                            <textarea class="form-control" id="governancePermissionDescription" name="descricao" rows="3" maxlength="255" data-permission-description></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="governancePermissionReason">Justificativa administrativa *</label>
                            <textarea class="form-control" id="governancePermissionReason" name="motivo" rows="2" minlength="5" maxlength="500" required data-permission-reason></textarea>
                            <div class="form-text">A justificativa será registrada na auditoria.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-check2-circle"></i> <span data-permission-submit-label>Criar permissão</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$pageCustomContent = (string) ob_get_clean();

return sigas_frontend_page([
    'title' => 'Permissões por módulo',
    'description' => 'Catálogo real de regras autorizáveis dos módulos operacionais do SIGAS.',
    'actions' => [
        [
            'label' => 'Níveis de usuário',
            'icon' => 'person-gear',
            'href' => 'governanca-acessos/perfis.php',
        ],
        [
            'label' => 'Matriz de acesso',
            'icon' => 'grid-3x3-gap',
            'primary' => true,
            'href' => 'governanca-acessos/matriz-acesso.php',
        ],
    ],
    'stats' => is_array($data['stats'] ?? null) ? $data['stats'] : [],
    'filters' => [],
    'blocks' => [],
    'demo' => false,
    'show_states' => false,
]);
