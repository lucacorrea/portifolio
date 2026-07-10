<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$profileId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
  'options' => ['min_range' => 1],
]);

$service = $application->profileManagement();
$loadError = null;
$details = null;

try {
  if (!is_int($profileId)) {
    throw new InvalidArgumentException('ID invalido.');
  }

  $details = $service->getProfile($profileId);
} catch (Throwable $exception) {
  $loadError = 'Perfil não encontrado ou indisponível.';
}

if ($loadError !== null || $details === null || !is_int($profileId)): ?>
  <div class="page-body profiles-page">
    <?php empty_state('Perfil indisponível', $loadError ?? 'Não foi possível carregar as permissões deste perfil.'); ?>
  </div>
  <?php return; ?>
<?php endif;

$profile = $details->profile();
$groups = $service->groupedActivePermissions();
$selectedIds = array_fill_keys($details->permissionIds(), true);
$dependencies = $service->permissionDependencies();
$isProtected = $profile->isProtected();
$totalPermissions = 0;
foreach ($groups as $permissions) {
  $totalPermissions += count($permissions);
}
?>
<div class="page-body profiles-page permissions-page" data-permission-dependencies="<?= h(json_encode($dependencies, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?>">
  <section class="panel">
    <div class="panel-header">
      <div>
        <div class="panel-title"><i class="bi bi-ui-checks-grid"></i><?= h($profile->name()) ?></div>
        <p class="section-note mt-1"><?= h($profile->description() ?? 'Sem descrição') ?></p>
      </div>
      <div class="panel-actions">
        <?= ui_badge($profile->isProtected() ? 'Protegido' : 'Personalizado') ?>
        <?= ui_badge($profile->status() === 'ativo' ? 'Ativo' : 'Inativo') ?>
      </div>
    </div>

    <form method="post" action="actions/perfil-permissoes-salvar.php" data-permission-form data-confirm-title="Salvar permissões" data-confirm-message="Substituir as permissões deste perfil? A mudança valerá na próxima requisição.">
      <div class="permissions-toolbar">
        <?= $csrf->field() ?>
        <?php return_to_field(); ?>
        <input type="hidden" name="id" value="<?= h((string) $profileId) ?>">
        <div class="permission-counter"><strong data-permission-selected><?= h((string) count($selectedIds)) ?></strong> de <strong data-permission-total><?= h((string) $totalPermissions) ?></strong> permissões selecionadas</div>
        <?php if ($isProtected): ?>
          <div class="alert alert-warning mb-0">O perfil Administrador é protegido e sempre possui todas as permissões ativas.</div>
        <?php endif; ?>
        <div class="permission-actions">
          <button class="btn-filter btn-filter-ghost" type="button" data-permission-select-all<?= $isProtected ? ' disabled' : '' ?>>Marcar todas</button>
          <button class="btn-filter btn-filter-ghost" type="button" data-permission-clear-all<?= $isProtected ? ' disabled' : '' ?>>Desmarcar todas</button>
          <button class="btn-filter btn-filter-ghost" type="button" data-permission-readonly<?= $isProtected ? ' disabled' : '' ?>>Somente visualização</button>
          <button class="btn-filter btn-filter-ghost" type="button" data-permission-restore<?= $isProtected ? ' disabled' : '' ?>>Restaurar seleção inicial</button>
        </div>
      </div>

      <div class="accordion permissions-accordion" id="permissions-accordion">
        <?php $groupIndex = 0; ?>
        <?php foreach ($groups as $groupName => $permissions): ?>
          <?php if ($permissions === []) continue; ?>
          <?php $groupIndex++; $groupId = 'permission-group-' . $groupIndex; ?>
          <div class="accordion-item permission-group" data-permission-group>
            <h2 class="accordion-header">
              <button class="accordion-button<?= $groupIndex > 1 ? ' collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= h($groupId) ?>" aria-expanded="<?= $groupIndex === 1 ? 'true' : 'false' ?>" aria-controls="<?= h($groupId) ?>">
                <span><?= h((string) $groupName) ?></span>
                <small data-group-counter></small>
              </button>
            </h2>
            <div id="<?= h($groupId) ?>" class="accordion-collapse collapse<?= $groupIndex === 1 ? ' show' : '' ?>" data-bs-parent="#permissions-accordion">
              <div class="accordion-body">
                <div class="permission-group-actions">
                  <button class="btn-filter btn-filter-ghost" type="button" data-permission-group-select<?= $isProtected ? ' disabled' : '' ?>>Marcar grupo</button>
                  <button class="btn-filter btn-filter-ghost" type="button" data-permission-group-clear<?= $isProtected ? ' disabled' : '' ?>>Desmarcar grupo</button>
                </div>
                <div class="permission-grid">
                  <?php foreach ($permissions as $permission): ?>
                    <?php $permissionId = $permission->id(); if ($permissionId === null) continue; ?>
                    <?php $checked = $isProtected || isset($selectedIds[$permissionId]); ?>
                    <label class="permission-option">
                      <input
                        type="checkbox"
                        name="permission_ids[]"
                        value="<?= h((string) $permissionId) ?>"
                        data-permission-checkbox
                        data-permission-code="<?= h($permission->code()) ?>"
                        <?= $checked ? ' checked' : '' ?>
                        <?= $isProtected ? ' disabled' : '' ?>
                      >
                      <span>
                        <strong><?= h($permission->name()) ?></strong>
                        <small><?= h($permission->code()) ?></small>
                        <?php if ($permission->description() !== null): ?>
                          <em><?= h($permission->description()) ?></em>
                        <?php endif; ?>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="profile-sticky-actions">
        <a class="btn-modal-cancel" href="perfis-acesso.php" data-permission-cancel>Cancelar</a>
        <?php if (!$isProtected): ?>
          <button class="btn-modal-save" type="submit">Salvar permissões</button>
        <?php endif; ?>
      </div>
    </form>
  </section>
</div>

<div class="modal fade" id="profile-confirm-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content visual-modal">
      <div class="modal-header">
        <h2 class="modal-title fs-5" data-confirm-title>Confirmar ação</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body"><p class="mb-0" data-confirm-message></p></div>
      <div class="modal-footer">
        <button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn-modal-save" type="button" data-confirm-submit>Confirmar</button>
      </div>
    </div>
  </div>
</div>
