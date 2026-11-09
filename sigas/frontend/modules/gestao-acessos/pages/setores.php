<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\GovernanceSectorRepository;
use App\Repositories\PermissionRepository;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\GovernanceSectorService;
use App\Services\PermissionService;

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

$pdo = Database::connection();
$levels = new AccessLevelRepository($pdo);
$service = new GovernanceSectorService(
    new GovernanceSectorRepository($pdo),
    new AuthorizationService(new PermissionService(new PermissionRepository($pdo)), $levels),
    new AuditService(new AuditLogRepository($pdo)),
);
$data = $service->page();
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$modules = is_array($data['modules'] ?? null) ? $data['modules'] : [];
$openNewModal = isset($_GET['novo']) && (string) $_GET['novo'] === '1';

$pageExtraStyles[] = 'assets/css/modules/gestao-acessos-users.css';
$pageExtraScripts[] = 'assets/js/modules/gestao-acessos-sectors.js';

ob_start();
?>
<div class="alert alert-info d-flex align-items-start gap-2" role="status">
    <i class="bi bi-diagram-3 mt-1"></i>
    <div>
        <strong>Setor define origem organizacional e barreira dos módulos operacionais.</strong>
        <div>
            CRAS, CREAS, SEMAS e demais unidades continuam sendo usados na trajetória da pessoa e no vínculo dos usuários.
            Governança e Acessos não é liberada por setor: ela continua protegida por nível e permissões administrativas.
        </div>
    </div>
</div>

<section class="content-card frontend-data-card" data-governance-sectors>
    <div class="card-heading">
        <div>
            <div class="card-kicker">Estrutura organizacional</div>
            <h2>Setores cadastrados</h2>
            <p>Gerencie unidades reais do SIGAS e defina quais programas cada setor pode acessar por padrão.</p>
        </div>
        <button class="btn btn-primary" type="button" data-sector-new data-bs-toggle="modal" data-bs-target="#governanceSectorModal">
            <i class="bi bi-plus-circle"></i> Novo setor
        </button>
    </div>

    <div class="table-responsive">
        <table class="data-table align-middle">
            <thead>
                <tr>
                    <th>Setor</th>
                    <th>Identificador</th>
                    <th>Usuários</th>
                    <th>Módulos liberados</th>
                    <th>Situação</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="6" class="text-center py-5 text-secondary">Nenhum setor cadastrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $sector): ?>
                        <?php
                            $id = (int) ($sector['id'] ?? 0);
                            $active = (bool) ($sector['active'] ?? false);
                            $protected = (bool) ($sector['protected'] ?? false);
                            $description = trim((string) ($sector['description'] ?? ''));
                            $users = (int) ($sector['users'] ?? 0);
                            $allowedModules = is_array($sector['allowed_modules'] ?? null) ? $sector['allowed_modules'] : [];
                            $allowedLabels = is_array($sector['allowed_module_labels'] ?? null) ? $sector['allowed_module_labels'] : [];
                        ?>
                        <tr>
                            <td>
                                <strong><?= sigas_frontend_escape((string) ($sector['name'] ?? 'Setor')) ?></strong>
                                <?php if ($description !== ''): ?>
                                    <div class="small text-secondary mt-1"><?= sigas_frontend_escape($description) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><code><?= sigas_frontend_escape((string) ($sector['slug'] ?? '')) ?></code></td>
                            <td><strong><?= $users ?></strong></td>
                            <td>
                                <?php if ($allowedLabels === []): ?>
                                    <span class="text-secondary small">Nenhum módulo operacional</span>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($allowedLabels as $label): ?>
                                            <span class="badge text-bg-light border"><?= sigas_frontend_escape((string) $label) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $active ? 'status-success' : 'status-neutral' ?>">
                                    <?= $active ? 'Ativo' : 'Inativo' ?>
                                </span>
                                <?php if ($protected): ?>
                                    <div class="small text-secondary mt-1"><i class="bi bi-shield-lock"></i> Estrutural</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                    <button
                                        class="btn btn-light btn-sm"
                                        type="button"
                                        data-sector-edit
                                        data-id="<?= $id ?>"
                                        data-name="<?= sigas_frontend_escape((string) ($sector['name'] ?? '')) ?>"
                                        data-slug="<?= sigas_frontend_escape((string) ($sector['slug'] ?? '')) ?>"
                                        data-description="<?= sigas_frontend_escape($description) ?>"
                                        data-modules="<?= sigas_frontend_escape(implode(',', array_map('strval', $allowedModules))) ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#governanceSectorModal"
                                    >
                                        <i class="bi bi-pencil"></i> Editar
                                    </button>

                                    <?php if ($protected && $active): ?>
                                        <button class="btn btn-light btn-sm" type="button" disabled title="Setor estrutural protegido contra inativação">
                                            <i class="bi bi-shield-lock"></i> Protegido
                                        </button>
                                    <?php else: ?>
                                        <button
                                            class="btn <?= $active ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm"
                                            type="button"
                                            data-sector-toggle
                                            data-id="<?= $id ?>"
                                            data-name="<?= sigas_frontend_escape((string) ($sector['name'] ?? '')) ?>"
                                            data-users="<?= $users ?>"
                                            data-action="<?= $active ? 'deactivate' : 'activate' ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#governanceSectorStatusModal"
                                        >
                                            <i class="bi bi-<?= $active ? 'pause-circle' : 'play-circle' ?>"></i>
                                            <?= $active ? 'Inativar' : 'Ativar' ?>
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

<div class="modal fade ga-user-admin-modal" id="governanceSectorModal" tabindex="-1" aria-labelledby="governanceSectorModalTitle" aria-hidden="true" data-auto-open="<?= $openNewModal ? '1' : '0' ?>">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form data-governance-sector-form novalidate>
                <div class="modal-header">
                    <div>
                        <div class="eyebrow"><i class="bi bi-diagram-3"></i> Governança</div>
                        <h2 class="modal-title fs-5" id="governanceSectorModalTitle" data-sector-modal-title>Novo setor</h2>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= sigas_frontend_escape(Csrf::token('governance-sector')) ?>">
                    <input type="hidden" name="acao" value="create" data-sector-action>
                    <input type="hidden" name="setor_id" value="" data-sector-id>
                    <div class="alert d-none" role="alert" data-governance-sector-alert></div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="governanceSectorName">Nome do setor *</label>
                            <input class="form-control" id="governanceSectorName" name="nome" type="text" minlength="2" maxlength="150" required data-sector-name>
                        </div>
                        <div class="col-12 d-none" data-sector-slug-group>
                            <label class="form-label" for="governanceSectorSlug">Identificador interno</label>
                            <input class="form-control" id="governanceSectorSlug" type="text" readonly data-sector-slug>
                            <div class="form-text">O identificador permanece imutável para preservar vínculos, trajetória e regras existentes.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="governanceSectorDescription">Descrição</label>
                            <textarea class="form-control" id="governanceSectorDescription" name="descricao" rows="3" maxlength="255" data-sector-description></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Módulos operacionais liberados por padrão</label>
                            <div class="row g-2">
                                <?php foreach ($modules as $moduleKey => $definition): ?>
                                    <div class="col-12 col-md-6">
                                        <label class="border rounded-3 p-3 d-flex gap-3 align-items-start h-100 bg-white">
                                            <input
                                                class="form-check-input mt-1"
                                                type="checkbox"
                                                name="modulos[]"
                                                value="<?= sigas_frontend_escape((string) $moduleKey) ?>"
                                                data-sector-module
                                            >
                                            <span>
                                                <strong class="d-block"><?= sigas_frontend_escape((string) ($definition['label'] ?? $moduleKey)) ?></strong>
                                                <span class="small text-secondary">Usuários do setor ainda precisam possuir as permissões do próprio nível.</span>
                                            </span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text mt-2">Deixar um módulo desmarcado bloqueia o acesso por setor, salvo exceção individual autorizada na tela de usuários.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="governanceSectorReason">Justificativa administrativa *</label>
                            <textarea class="form-control" id="governanceSectorReason" name="motivo" rows="3" minlength="5" maxlength="500" required data-sector-reason placeholder="Informe o motivo da criação ou alteração."></textarea>
                            <div class="form-text">A justificativa será registrada na auditoria.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> <span data-sector-submit-label>Criar setor</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade ga-user-admin-modal" id="governanceSectorStatusModal" tabindex="-1" aria-labelledby="governanceSectorStatusTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form data-governance-sector-status-form novalidate>
                <div class="modal-header">
                    <div>
                        <div class="eyebrow"><i class="bi bi-shield-exclamation"></i> Alteração de situação</div>
                        <h2 class="modal-title fs-5" id="governanceSectorStatusTitle" data-sector-status-title>Alterar setor</h2>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= sigas_frontend_escape(Csrf::token('governance-sector')) ?>">
                    <input type="hidden" name="acao" value="" data-sector-status-action>
                    <input type="hidden" name="setor_id" value="" data-sector-status-id>
                    <div class="alert d-none" role="alert" data-governance-sector-status-alert></div>
                    <div class="ga-governance-note mb-3" data-sector-status-note></div>
                    <label class="form-label" for="governanceSectorStatusReason">Justificativa *</label>
                    <textarea class="form-control" id="governanceSectorStatusReason" name="motivo" rows="3" minlength="5" maxlength="500" required placeholder="Informe o motivo da alteração de situação."></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit" data-sector-status-submit>Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$pageCustomContent = (string) ob_get_clean();

return sigas_frontend_page([
    'title' => 'Setores',
    'description' => 'Gestão real das unidades organizacionais, vínculos de usuários e módulos operacionais do SIGAS.',
    'actions' => [
        ['label' => 'Usuários', 'icon' => 'people', 'href' => 'governanca-acessos/usuarios.php'],
        ['label' => 'Matriz de acesso', 'icon' => 'grid-3x3-gap', 'primary' => true, 'href' => 'governanca-acessos/matriz-acesso.php'],
    ],
    'stats' => is_array($data['stats'] ?? null) ? $data['stats'] : [],
    'filters' => [],
    'blocks' => [],
    'demo' => false,
    'show_states' => false,
]);
