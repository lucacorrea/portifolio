<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

$service = $application->profileManagement();
$filters = [
  'search' => trim((string) ($_GET['search'] ?? '')),
  'status' => (string) ($_GET['status'] ?? ''),
  'type' => (string) ($_GET['type'] ?? ''),
];
$profiles = $service->listProfiles($filters);
$summary = $service->profileSummary();

$canCreate = $authorization->can('perfil.criar');
$canEdit = $authorization->can('perfil.editar');
$canDuplicate = $authorization->can('perfil.duplicar');
$canStatus = $authorization->can('perfil.desativar');
$canDelete = $authorization->can('perfil.excluir');
$canConfigure = $authorization->can('perfil.configurar_permissoes');

function profile_status_label(string $status): string
{
  return $status === 'ativo' ? 'Ativo' : 'Inativo';
}

function profile_date(?string $value): string
{
  if ($value === null || trim($value) === '') {
    return '-';
  }

  try {
    return (new DateTimeImmutable($value))->format('d/m/Y H:i');
  } catch (Throwable $exception) {
    return '-';
  }
}
?>
<div class="page-body profiles-page">
  <?php metric_grid([
    ['Total de perfis', (string) $summary['total'], 'bi-shield-lock', '#2563EB', 'cadastrados'],
    ['Perfis ativos', (string) $summary['active'], 'bi-check-circle', '#16A34A', 'com acesso permitido'],
    ['Perfis inativos', (string) $summary['inactive'], 'bi-pause-circle', '#D97706', 'sem novos acessos'],
    ['Usuários vinculados', (string) $summary['users'], 'bi-people', '#7C3AED', 'em todos os perfis'],
  ]); ?>

  <form class="filter-bar" method="get" action="perfis-acesso.php">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input class="search-input" type="search" name="search" value="<?= h($filters['search']) ?>" placeholder="Buscar por nome do perfil">
    </div>
    <select class="filter-select" name="status" aria-label="Status">
      <option value="">Todos os status</option>
      <option value="ativo"<?= $filters['status'] === 'ativo' ? ' selected' : '' ?>>Ativos</option>
      <option value="inativo"<?= $filters['status'] === 'inativo' ? ' selected' : '' ?>>Inativos</option>
    </select>
    <select class="filter-select" name="type" aria-label="Tipo">
      <option value="">Todos os tipos</option>
      <option value="protegido"<?= $filters['type'] === 'protegido' ? ' selected' : '' ?>>Protegidos</option>
      <option value="personalizado"<?= $filters['type'] === 'personalizado' ? ' selected' : '' ?>>Personalizados</option>
    </select>
    <button class="btn-filter btn-filter-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
    <a class="btn-filter btn-filter-ghost" href="perfis-acesso.php"><i class="bi bi-x-lg"></i> Limpar filtros</a>
  </form>

  <section class="panel">
    <div class="panel-header">
      <div class="panel-title"><i class="bi bi-shield-lock"></i>Perfis cadastrados</div>
      <?php if ($canCreate): ?>
        <a class="btn-filter btn-filter-primary" href="perfil-formulario.php"><i class="bi bi-plus-lg"></i> Novo perfil</a>
      <?php endif; ?>
    </div>

    <?php if ($profiles === []): ?>
      <?php empty_state('Nenhum perfil encontrado', 'Ajuste os filtros ou crie um novo perfil de acesso.'); ?>
    <?php else: ?>
      <div class="table-panel-wrap">
        <table class="os-table profiles-table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>Descrição</th>
              <th>Tipo</th>
              <th>Status</th>
              <th>Usuários</th>
              <th>Permissões</th>
              <th>Atualizado em</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($profiles as $profile): ?>
              <tr>
                <td><strong><?= h($profile->name()) ?></strong></td>
                <td class="profile-description"><?= h($profile->description() ?? '-') ?></td>
                <td><?= ui_badge($profile->isProtected() ? 'Protegido' : 'Personalizado') ?></td>
                <td><?= ui_badge(profile_status_label($profile->status())) ?></td>
                <td><?= h((string) $profile->totalUsers()) ?></td>
                <td><?= h((string) $profile->totalPermissions()) ?></td>
                <td><?= h(profile_date($profile->updatedAt())) ?></td>
                <td>
                  <div class="dropdown">
                    <button class="btn-action" type="button" data-bs-toggle="dropdown" aria-label="Ações do perfil <?= h($profile->name()) ?>">
                      <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li><a class="dropdown-item" href="perfil-formulario.php?id=<?= h((string) $profile->id()) ?>&view=1"><i class="bi bi-eye"></i> Visualizar</a></li>
                      <?php if ($canEdit): ?>
                        <li><a class="dropdown-item<?= $profile->isProtected() ? ' disabled' : '' ?>" href="perfil-formulario.php?id=<?= h((string) $profile->id()) ?>"><i class="bi bi-pencil"></i> Editar</a></li>
                      <?php endif; ?>
                      <?php if ($canConfigure): ?>
                        <li><a class="dropdown-item" href="perfil-permissoes.php?id=<?= h((string) $profile->id()) ?>"><i class="bi bi-ui-checks-grid"></i> Configurar permissões</a></li>
                      <?php endif; ?>
                      <?php if ($canDuplicate): ?>
                        <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#duplicate-profile-<?= h((string) $profile->id()) ?>"><i class="bi bi-copy"></i> Duplicar</button></li>
                      <?php endif; ?>
                      <?php if ($canStatus && !$profile->isProtected()): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                          <form method="post" action="actions/perfil-status.php" data-confirm-title="<?= $profile->status() === 'ativo' ? 'Desativar perfil' : 'Ativar perfil' ?>" data-confirm-message="<?= h(($profile->status() === 'ativo' ? 'Desativar' : 'Ativar') . ' o perfil "' . $profile->name() . '"?') ?>">
                            <?= $csrf->field() ?>
                            <input type="hidden" name="id" value="<?= h((string) $profile->id()) ?>">
                            <input type="hidden" name="status" value="<?= $profile->status() === 'ativo' ? 'inativo' : 'ativo' ?>">
                            <button class="dropdown-item" type="submit"><i class="bi bi-power"></i> <?= $profile->status() === 'ativo' ? 'Desativar' : 'Ativar' ?></button>
                          </form>
                        </li>
                      <?php endif; ?>
                      <?php if ($canDelete && !$profile->isProtected()): ?>
                        <li>
                          <form method="post" action="actions/perfil-excluir.php" data-confirm-title="Excluir perfil" data-confirm-message="<?= h('Excluir o perfil "' . $profile->name() . '"? Esta ação só será permitida se não houver usuários vinculados.') ?>">
                            <?= $csrf->field() ?>
                            <input type="hidden" name="id" value="<?= h((string) $profile->id()) ?>">
                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash"></i> Excluir</button>
                          </form>
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

<?php foreach ($profiles as $profile): ?>
  <?php if (!$canDuplicate) continue; ?>
  <div class="modal fade" id="duplicate-profile-<?= h((string) $profile->id()) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content visual-modal" method="post" action="actions/perfil-duplicar.php">
        <div class="modal-header">
          <h2 class="modal-title fs-5">Duplicar perfil</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <?= $csrf->field() ?>
          <input type="hidden" name="id" value="<?= h((string) $profile->id()) ?>">
          <p class="section-note mb-3">As permissões de <?= h($profile->name()) ?> serão copiadas para um perfil personalizado ativo.</p>
          <div class="form-group">
            <label class="form-label" for="duplicate-name-<?= h((string) $profile->id()) ?>">Nome do novo perfil</label>
            <input class="form-control-os" id="duplicate-name-<?= h((string) $profile->id()) ?>" name="name" maxlength="100" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="duplicate-description-<?= h((string) $profile->id()) ?>">Descrição</label>
            <textarea class="form-control-os" id="duplicate-description-<?= h((string) $profile->id()) ?>" name="description" maxlength="255" rows="3"><?= h($profile->description() ?? '') ?></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn-modal-cancel" type="button" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn-modal-save" type="submit">Duplicar perfil</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>

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
