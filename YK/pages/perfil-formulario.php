<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$service = $application->profileManagement();
$profileId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
  'options' => ['min_range' => 1],
]);
$viewOnly = ((string) ($_GET['view'] ?? '')) === '1';
$loadError = null;
$details = null;
try {
  $details = is_int($profileId) ? $service->getProfile($profileId) : null;
} catch (Throwable $exception) {
  $loadError = 'Perfil não encontrado ou indisponível.';
}

if ($loadError !== null): ?>
  <div class="page-body profiles-page">
    <?php empty_state('Perfil indisponível', $loadError); ?>
  </div>
  <?php return; ?>
<?php endif;

$profile = $details?->profile();
$isProtected = $profile?->isProtected() ?? false;
$readOnly = $viewOnly || $isProtected;
$activeProfiles = array_filter(
  $service->listProfiles(['status' => 'ativo']),
  static fn ($item): bool => !$profileId || $item->id() !== $profileId
);
?>
<div class="page-body profiles-page">
  <section class="panel profile-form-panel">
    <div class="panel-header">
      <div class="panel-title">
        <i class="bi bi-shield-lock"></i>
        <?= h($profile === null ? 'Novo perfil' : ($viewOnly ? 'Visualizar perfil' : 'Editar perfil')) ?>
      </div>
      <div class="panel-actions">
        <?php if ($profile !== null && $authorization->can('perfil.configurar_permissoes')): ?>
          <a class="btn-filter btn-filter-ghost" href="perfil-permissoes.php?id=<?= h((string) $profile->id()) ?>"><i class="bi bi-ui-checks-grid"></i> Permissões</a>
        <?php endif; ?>
        <a class="btn-filter btn-filter-ghost" href="perfis-acesso.php"><i class="bi bi-arrow-left"></i> Voltar</a>
      </div>
    </div>

    <form method="post" action="actions/perfil-salvar.php" class="profile-form">
      <div class="p-3">
        <?php if ($isProtected): ?>
          <div class="alert alert-warning">O perfil Administrador é protegido. Nome, status e permissões estruturais não podem ser alterados por esta tela.</div>
        <?php endif; ?>

        <?= $csrf->field() ?>
        <?php if ($profile !== null): ?>
          <input type="hidden" name="id" value="<?= h((string) $profile->id()) ?>">
        <?php endif; ?>

        <div class="form-section">
          <h3 class="form-section-title">Dados do perfil</h3>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="profile-name">Nome do perfil</label>
              <input class="form-control-os" id="profile-name" name="name" maxlength="100" required value="<?= h($profile?->name() ?? '') ?>"<?= $readOnly ? ' readonly' : '' ?>>
            </div>
            <div class="form-group">
              <label class="form-label" for="profile-status">Status</label>
              <select class="form-control-os" id="profile-status" name="status"<?= $readOnly ? ' disabled' : '' ?>>
                <?php $status = $profile?->status() ?? 'ativo'; ?>
                <option value="ativo"<?= $status === 'ativo' ? ' selected' : '' ?>>Ativo</option>
                <option value="inativo"<?= $status === 'inativo' ? ' selected' : '' ?>>Inativo</option>
              </select>
              <?php if ($readOnly): ?>
                <input type="hidden" name="status" value="<?= h($status) ?>">
              <?php endif; ?>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="profile-description">Descrição</label>
            <textarea class="form-control-os" id="profile-description" name="description" maxlength="255" rows="3"<?= $viewOnly ? ' readonly' : '' ?>><?= h($profile?->description() ?? '') ?></textarea>
          </div>
        </div>

        <?php if ($profile === null): ?>
          <div class="form-section">
            <h3 class="form-section-title">Permissões iniciais</h3>
            <p class="section-note mb-3">Copie somente as permissões de outro perfil. O nome não será copiado.</p>
            <div class="form-group">
              <label class="form-label" for="copy-profile">Copiar permissões de outro perfil</label>
              <select class="form-control-os" id="copy-profile" name="copy_profile_id">
                <option value="">Perfil vazio</option>
                <?php foreach ($activeProfiles as $copyProfile): ?>
                  <option value="<?= h((string) $copyProfile->id()) ?>"><?= h($copyProfile->name()) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        <?php elseif ($details !== null): ?>
          <div class="form-section">
            <h3 class="form-section-title">Resumo</h3>
            <div class="profile-summary-grid">
              <div><span>Tipo</span><strong><?= h($profile->isProtected() ? 'Protegido' : 'Personalizado') ?></strong></div>
              <div><span>Usuários vinculados</span><strong><?= h((string) $details->totalUsers()) ?></strong></div>
              <div><span>Permissões vinculadas</span><strong><?= h((string) $details->totalPermissions()) ?></strong></div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <div class="profile-sticky-actions">
        <a class="btn-modal-cancel" href="perfis-acesso.php">Cancelar</a>
        <?php if (!$viewOnly && !$isProtected): ?>
          <button class="btn-modal-save" type="submit"><?= $profile === null ? 'Criar perfil' : 'Salvar alterações' ?></button>
        <?php endif; ?>
      </div>
    </form>
  </section>
</div>
