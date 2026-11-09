<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\GovernanceAccessMatrixRepository;
use App\Repositories\PermissionRepository;
use App\Services\AuditService;
use App\Services\AuthorizationService;
use App\Services\GovernanceAccessMatrixService;
use App\Services\PermissionService;

require_once dirname(__DIR__, 3) . '/support/program-pages.php';

$pdo = Database::connection();
$levelRepository = new AccessLevelRepository($pdo);
$matrixService = new GovernanceAccessMatrixService(
    new GovernanceAccessMatrixRepository($pdo),
    $levelRepository,
    new AuthorizationService(new PermissionService(new PermissionRepository($pdo)), $levelRepository),
    new AuditService(new AuditLogRepository($pdo)),
);
$data = $matrixService->page();
$levels = $data['levels'];
$modules = $data['modules'];

$requestedLevelId = filter_input(INPUT_GET, 'nivel', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$selectedLevel = null;

if (is_int($requestedLevelId) && $requestedLevelId > 0) {
    foreach ($levels as $level) {
        if ((int) ($level['id'] ?? 0) === $requestedLevelId) {
            $selectedLevel = $level;
            break;
        }
    }
}

if ($selectedLevel === null) {
    foreach ($levels as $level) {
        if (!(bool) ($level['protected'] ?? false)) {
            $selectedLevel = $level;
            break;
        }
    }
}

if ($selectedLevel === null && $levels !== []) {
    $selectedLevel = $levels[0];
}

$selectedLevelId = (int) ($selectedLevel['id'] ?? 0);
$selectedGrants = array_fill_keys(array_map('intval', $selectedLevel['grants'] ?? []), true);
$selectedProtected = (bool) ($selectedLevel['protected'] ?? false);
$selectedUsers = (int) ($selectedLevel['users'] ?? 0);

$totalModulePermissions = 0;
$selectedModulePermissions = 0;
foreach ($modules as $module) {
    foreach ($module['permissions'] as $permission) {
        $totalModulePermissions++;
        if (isset($selectedGrants[(int) ($permission['id'] ?? 0)])) {
            $selectedModulePermissions++;
        }
    }
}

$pageExtraStyles[] = 'assets/css/modules/gestao-acessos-matrix.css';
$pageExtraScripts[] = 'assets/js/modules/gestao-acessos-matrix.js';

ob_start();
?>
<section class="ga-matrix-shell" data-governance-access-matrix>
    <div class="content-card ga-matrix-level-card">
        <div class="card-heading">
            <div>
                <div class="card-kicker">Regra-base de autorização</div>
                <h2>Selecione o nível que deseja configurar</h2>
                <p>A matriz altera somente as permissões dos módulos operacionais. Exceções individuais dos usuários permanecem separadas.</p>
            </div>
            <span class="status-badge <?= $selectedProtected ? 'status-neutral' : 'status-success' ?>">
                <i class="bi bi-<?= $selectedProtected ? 'lock' : 'shield-check' ?>"></i>
                <?= $selectedProtected ? 'Nível protegido' : 'Editável' ?>
            </span>
        </div>

        <div class="ga-matrix-level-toolbar">
            <div class="ga-matrix-level-select">
                <label class="form-label" for="matrixLevelSelect">Nível de acesso</label>
                <select class="form-select" id="matrixLevelSelect" data-matrix-level-select>
                    <?php foreach ($levels as $level): ?>
                        <option
                            value="<?= (int) ($level['id'] ?? 0) ?>"
                            <?= (int) ($level['id'] ?? 0) === $selectedLevelId ? 'selected' : '' ?>
                        >
                            <?= sigas_frontend_escape((string) ($level['name'] ?? 'Nível')) ?>
                            <?= (bool) ($level['protected'] ?? false) ? ' · protegido' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ga-matrix-level-summary">
                <article><small>Usuários vinculados</small><strong><?= $selectedUsers ?></strong></article>
                <article><small>Permissões dos módulos</small><strong><?= $selectedModulePermissions ?>/<?= $totalModulePermissions ?></strong></article>
                <article><small>Escopo</small><strong><?= $selectedProtected ? 'Global protegido' : 'Setor + exceções' ?></strong></article>
            </div>
        </div>
    </div>

    <?php if ($selectedProtected): ?>
        <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-shield-lock mt-1"></i>
            <div>
                <strong><?= sigas_frontend_escape((string) ($selectedLevel['name'] ?? 'Este nível')) ?> é protegido.</strong>
                <div>Administrador e Suporte mantêm o acesso global necessário à recuperação e administração do SIGAS. A matriz abaixo é somente leitura para evitar bloqueio administrativo acidental.</div>
            </div>
        </div>
    <?php endif; ?>

    <form class="ga-matrix-form" data-matrix-form novalidate>
        <input type="hidden" name="_csrf" value="<?= sigas_frontend_escape(Csrf::token('governance-access-matrix')) ?>">
        <input type="hidden" name="nivel_id" value="<?= $selectedLevelId ?>">

        <div class="alert d-none ga-matrix-alert" role="alert" data-matrix-alert></div>

        <div class="ga-matrix-grid">
            <?php foreach ($modules as $module): ?>
                <?php
                    $moduleEditable = (bool) ($module['editable'] ?? false) && !$selectedProtected;
                    $permissions = is_array($module['permissions'] ?? null) ? $module['permissions'] : [];
                    $viewPermission = null;
                    $actionPermissions = [];
                    foreach ($permissions as $permission) {
                        if ((bool) ($permission['is_view'] ?? false) && $viewPermission === null) {
                            $viewPermission = $permission;
                        } else {
                            $actionPermissions[] = $permission;
                        }
                    }
                    $moduleKey = (string) ($module['key'] ?? '');
                ?>
                <section class="content-card ga-matrix-module" data-matrix-module="<?= sigas_frontend_escape($moduleKey) ?>">
                    <div class="ga-matrix-module-head">
                        <div>
                            <span class="card-kicker">Módulo</span>
                            <h3><?= sigas_frontend_escape((string) ($module['label'] ?? 'Módulo')) ?></h3>
                        </div>
                        <?php if (!$moduleEditable): ?>
                            <span class="status-badge status-neutral"><i class="bi bi-lock"></i>Protegido</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($viewPermission !== null): ?>
                        <?php $viewId = (int) ($viewPermission['id'] ?? 0); ?>
                        <label class="ga-matrix-permission ga-matrix-permission--view">
                            <span class="ga-matrix-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="permission_ids[]"
                                    value="<?= $viewId ?>"
                                    data-matrix-view
                                    <?= isset($selectedGrants[$viewId]) ? 'checked' : '' ?>
                                    <?= !$moduleEditable ? 'disabled' : '' ?>
                                >
                            </span>
                            <span>
                                <strong>Acesso ao módulo</strong>
                                <small><?= sigas_frontend_escape((string) ($viewPermission['name'] ?? 'Visualizar')) ?></small>
                                <code><?= sigas_frontend_escape((string) ($viewPermission['slug'] ?? '')) ?></code>
                            </span>
                        </label>
                    <?php endif; ?>

                    <div class="ga-matrix-actions">
                        <?php if ($actionPermissions === []): ?>
                            <p class="text-secondary small mb-0">Este módulo não possui outras ações cadastradas.</p>
                        <?php else: ?>
                            <?php foreach ($actionPermissions as $permission): ?>
                                <?php $permissionId = (int) ($permission['id'] ?? 0); ?>
                                <label class="ga-matrix-permission">
                                    <span class="ga-matrix-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="permission_ids[]"
                                            value="<?= $permissionId ?>"
                                            data-matrix-action
                                            <?= isset($selectedGrants[$permissionId]) ? 'checked' : '' ?>
                                            <?= !$moduleEditable ? 'disabled' : '' ?>
                                        >
                                    </span>
                                    <span>
                                        <strong><?= sigas_frontend_escape((string) ($permission['name'] ?? 'Permissão')) ?></strong>
                                        <?php if (trim((string) ($permission['description'] ?? '')) !== ''): ?>
                                            <small><?= sigas_frontend_escape((string) $permission['description']) ?></small>
                                        <?php endif; ?>
                                        <code><?= sigas_frontend_escape((string) ($permission['slug'] ?? '')) ?></code>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <?php if (!$selectedProtected): ?>
            <section class="content-card ga-matrix-save-card">
                <div>
                    <div class="card-kicker">Auditoria obrigatória</div>
                    <h3>Justificativa da alteração</h3>
                    <p>Usuários vinculados a este nível terão a versão de autorização atualizada e suas sessões ativas serão encerradas quando houver mudança efetiva.</p>
                </div>
                <div class="ga-matrix-save-fields">
                    <textarea
                        class="form-control"
                        name="motivo"
                        rows="3"
                        minlength="5"
                        maxlength="500"
                        required
                        placeholder="Ex.: Ajuste das permissões do nível Técnico conforme fluxo operacional aprovado."
                    ></textarea>
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-shield-check"></i> Salvar matriz deste nível
                    </button>
                </div>
            </section>
        <?php endif; ?>
    </form>
</section>
<?php
$pageCustomContent = (string) ob_get_clean();

return sigas_frontend_page([
    'title' => 'Matriz de acesso por módulo',
    'description' => 'Configure a regra-base de permissões por nível. Setor e exceções individuais continuam sendo camadas independentes de autorização.',
    'actions' => [
        [
            'label' => 'Níveis de usuário',
            'icon' => 'person-gear',
            'href' => 'governanca-acessos/perfis.php',
        ],
        [
            'label' => 'Permissões por módulo',
            'icon' => 'key',
            'href' => 'governanca-acessos/permissoes.php',
        ],
    ],
    'stats' => [
        ['label' => 'Níveis ativos', 'value' => (string) count($levels), 'detail' => 'Inclui níveis protegidos', 'icon' => 'person-gear'],
        ['label' => 'Módulos controlados', 'value' => (string) count($modules), 'detail' => 'Catálogo operacional', 'icon' => 'grid-3x3-gap'],
        ['label' => 'Nível selecionado', 'value' => (string) ($selectedLevel['name'] ?? '—'), 'detail' => $selectedProtected ? 'Somente leitura' : 'Matriz editável', 'icon' => 'shield-lock'],
        ['label' => 'Usuários afetados', 'value' => (string) $selectedUsers, 'detail' => 'Sessões serão renovadas se houver alteração', 'icon' => 'people'],
    ],
    'filters' => [],
    'blocks' => [],
    'demo' => false,
    'show_states' => false,
]);
