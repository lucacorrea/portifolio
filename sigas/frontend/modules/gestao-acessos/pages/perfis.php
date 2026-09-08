<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\GovernanceAccessLevelRepository;
use App\Repositories\PermissionRepository;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\GovernanceAccessLevelService;
use App\Services\PermissionService;

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

$pdo = Database::connection();
$levels = new AccessLevelRepository($pdo);
$service = new GovernanceAccessLevelService(
    new GovernanceAccessLevelRepository($pdo),
    new AuthorizationService(new PermissionService(new PermissionRepository($pdo)), $levels),
    new AuditService(new AuditLogRepository($pdo)),
);
$data = $service->page();
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];

$pageExtraStyles[] = 'assets/css/modules/gestao-acessos-users.css';
$pageExtraScripts[] = 'assets/js/modules/gestao-acessos-levels.js';
$openNewModal = isset($_GET['novo']) && (string) $_GET['novo'] === '1';

ob_start();
?>
<div class="alert alert-info d-flex align-items-start gap-2" role="status">
    <i class="bi bi-shield-lock mt-1"></i>
    <div>
        <strong>Nível define autorização; cargo define função institucional.</strong>
        <div>Administrador e Suporte são níveis protegidos. Os demais podem ser criados, editados, ativados ou inativados sem exclusão física.</div>
    </div>
</div>

<section class="content-card frontend-data-card" data-governance-levels>
    <div class="card-heading">
        <div>
            <div class="card-kicker">Autorização</div>
            <h2>Perfis e níveis de acesso</h2>
            <p>Administre os níveis usados pelos usuários. As permissões de cada nível continuam sendo configuradas na Matriz de Acesso.</p>
        </div>
        <button class="btn btn-primary" type="button" data-level-new data-bs-toggle="modal" data-bs-target="#governanceLevelModal">
            <i class="bi bi-plus-circle"></i> Novo nível
        </button>
    </div>

    <div class="table-responsive">
        <table class="data-table align-middle">
            <thead>
                <tr>
                    <th>Nível</th>
                    <th>Identificador</th>
                    <th>Prioridade</th>
                    <th>Usuários</th>
                    <th>Permissões</th>
                    <th>Situação</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="7" class="text-center py-5 text-secondary">Nenhum nível cadastrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $level): ?>
                        <?php
                            $id = (int) ($level['id'] ?? 0);
                            $active = (bool) ($level['active'] ?? false);
                            $protected = (bool) ($level['protected'] ?? false);
                            $description = (string) ($level['description'] ?? '');
                            $users = (int) ($level['users'] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <strong><?= sigas_frontend_escape((string) ($level['name'] ?? 'Nível')) ?></strong>
                                <?php if ($description !== ''): ?>
                                    <div class="small text-secondary mt-1"><?= sigas_frontend_escape($description) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><code><?= sigas_frontend_escape((string) ($level['slug'] ?? '')) ?></code></td>
                            <td><?= (int) ($level['priority'] ?? 0) ?></td>
                            <td><?= $users ?></td>
                            <td><?= (int) ($level['permissions'] ?? 0) ?></td>
                            <td>
                                <?php if ($protected): ?>
                                    <span class="status-badge status-success"><i class="bi bi-shield-check"></i>Protegido</span>
                                <?php else: ?>
                                    <span class="status-badge <?= $active ? 'status-success' : 'status-neutral' ?>">
                                        <?= $active ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($protected): ?>
                                    <span class="small text-secondary">Somente consulta</span>
                                <?php else: ?>
                                    <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                        <button
                                            class="btn btn-light btn-sm"
                                            type="button"
                                            data-level-edit
                                            data-id="<?= $id ?>"
                                            data-name="<?= sigas_frontend_escape((string) ($level['name'] ?? '')) ?>"
                                            data-slug="<?= sigas_frontend_escape((string) ($level['slug'] ?? '')) ?>"
                                            data-description="<?= sigas_frontend_escape($description) ?>"
                                            data-priority="<?= (int) ($level['priority'] ?? 0) ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#governanceLevelModal"
                                        >
                                            <i class="bi bi-pencil"></i> Editar
                                        </button>
                                        <a class="btn btn-light btn-sm" href="governanca-acessos/matriz-acesso.php?nivel=<?= $id ?>">
                                            <i class="bi bi-grid-3x3-gap"></i> Permissões
                                        </a>
                                        <button
                                            class="btn <?= $active ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm"
                                            type="button"
                                            data-level-toggle
                                            data-id="<?= $id ?>"
                                            data-name="<?= sigas_frontend_escape((string) ($level['name'] ?? '')) ?>"
                                            data-users="<?= $users ?>"
                                            data-action="<?= $active ? 'deactivate' : 'activate' ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#governanceLevelStatusModal"
                                        >
                                            <i class="bi bi-<?= $active ? 'pause-circle' : 'play-circle' ?>"></i>
                                            <?= $active ? 'Inativar' : 'Ativar' ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal fade ga-user-admin-modal" id="governanceLevelModal" tabindex="-1" aria-labelledby="governanceLevelModalTitle" aria-hidden="true" data-auto-open="<?= $openNewModal ? '1' : '0' ?>">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form data-governance-level-form novalidate>
                <div class="modal-header">
                    <div>
                        <div class="eyebrow"><i class="bi bi-person-gear"></i> Governança</div>
                        <h2 class="modal-title fs-5" id="governanceLevelModalTitle" data-level-modal-title>Novo nível</h2>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= sigas_frontend_escape(Csrf::token('governance-access-level')) ?>">
                    <input type="hidden" name="acao" value="create" data-level-action>
                    <input type="hidden" name="nivel_id" value="" data-level-id>
                    <div class="alert d-none" role="alert" data-governance-level-alert></div>

                    <div class="mb-3">
                        <label class="form-label" for="governanceLevelName">Nome do nível *</label>
                        <input class="form-control" id="governanceLevelName" name="nome" type="text" minlength="2" maxlength="120" required data-level-name>
                    </div>
                    <div class="mb-3 d-none" data-level-slug-group>
                        <label class="form-label" for="governanceLevelSlug">Identificador interno</label>
                        <input class="form-control" id="governanceLevelSlug" type="text" readonly data-level-slug>
                        <div class="form-text">O identificador é imutável para não quebrar regras de autorização existentes.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="governanceLevelDescription">Descrição</label>
                        <textarea class="form-control" id="governanceLevelDescription" name="descricao" rows="3" maxlength="255" data-level-description></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="governanceLevelPriority">Prioridade *</label>
                        <input class="form-control" id="governanceLevelPriority" name="prioridade" type="number" min="1" max="9999" required data-level-priority>
                        <div class="form-text">Valores menores aparecem primeiro na organização dos níveis. As permissões são definidas separadamente na matriz.</div>
                    </div>
                    <div>
                        <label class="form-label" for="governanceLevelReason">Justificativa *</label>
                        <textarea class="form-control" id="governanceLevelReason" name="motivo" rows="3" minlength="5" maxlength="500" required data-level-reason placeholder="Informe o motivo administrativo da criação ou alteração."></textarea>
                        <div class="form-text">A justificativa será registrada na auditoria.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> <span data-level-submit-label>Criar nível</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade ga-user-admin-modal" id="governanceLevelStatusModal" tabindex="-1" aria-labelledby="governanceLevelStatusTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form data-governance-level-status-form novalidate>
                <div class="modal-header">
                    <div>
                        <div class="eyebrow"><i class="bi bi-shield-exclamation"></i> Alteração de situação</div>
                        <h2 class="modal-title fs-5" id="governanceLevelStatusTitle" data-level-status-title>Alterar nível</h2>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= sigas_frontend_escape(Csrf::token('governance-access-level')) ?>">
                    <input type="hidden" name="acao" value="" data-level-status-action>
                    <input type="hidden" name="nivel_id" value="" data-level-status-id>
                    <div class="alert d-none" role="alert" data-governance-level-status-alert></div>
                    <div class="ga-governance-note mb-3" data-level-status-note></div>
                    <label class="form-label" for="governanceLevelStatusReason">Justificativa *</label>
                    <textarea class="form-control" id="governanceLevelStatusReason" name="motivo" rows="3" minlength="5" maxlength="500" required placeholder="Informe o motivo da alteração de situação."></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit" data-level-status-submit>Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$pageCustomContent = (string) ob_get_clean();

return sigas_frontend_page([
    'title' => 'Perfis e níveis',
    'description' => 'Gestão real dos níveis de autorização do SIGAS, com identificadores imutáveis, auditoria e proteção dos níveis administrativos.',
    'actions' => [
        ['label' => 'Permissões', 'icon' => 'key', 'href' => 'governanca-acessos/permissoes.php'],
        ['label' => 'Matriz por nível', 'icon' => 'grid-3x3-gap', 'primary' => true, 'href' => 'governanca-acessos/matriz-acesso.php'],
    ],
    'stats' => is_array($data['stats'] ?? null) ? $data['stats'] : [],
    'filters' => [],
    'blocks' => [],
    'demo' => false,
    'show_states' => false,
]);
