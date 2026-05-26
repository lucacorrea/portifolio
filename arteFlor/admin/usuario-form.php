<?php
$adminTitle = 'Cadastro de usuário';
$activeAdmin = 'usuario-form';
require_once __DIR__ . '/../includes/users.php';
$adminUser = require_admin();

$userId = filter_var($_GET['id'] ?? $_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$usuario = $userId > 0 ? admin_user_find($userId) : null;
$error = '';

if (!admin_user_can_manage($adminUser)) {
    http_response_code(403);
    $error = 'Apenas administradores podem gerenciar usuários.';
}

if ($userId > 0 && !$usuario && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(404);
    $error = 'Usuário não encontrado.';
}

if (admin_user_can_manage($adminUser) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        try {
            $savedId = admin_user_save_from_request($_POST, $adminUser);
            header('Location: ' . site_url('admin/usuarios.php?saved=1&id=' . $savedId));
            exit;
        } catch (Throwable $exception) {
            error_log('[ArteFlor][admin-user-save] ' . $exception->getMessage());
            $error = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'Não foi possível salvar o usuário. Verifique os dados e tente novamente.';
            $usuario = array_merge($usuario ?? [], $_POST);
        }
    }
}

$isEditing = !empty($usuario['id']);
$field = fn(string $key, mixed $default = ''): mixed => $_POST[$key] ?? $usuario[$key] ?? $default;
$activeChecked = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    ? (!empty($_POST['ativo']) ? 'checked' : '')
    : (((int) $field('ativo', 1) === 1) ? 'checked' : '');
$initials = strtoupper(substr((string) $field('nome', 'AF'), 0, 2));

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Gestão</span>
    <h1><?= $isEditing ? 'Editar usuário' : 'Cadastrar usuário' ?></h1>
    <p>Crie acessos administrativos com perfil e senha individual.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="btn btn-soft" href="<?= site_url('admin/usuarios.php') ?>">Voltar para usuários</a>
    <?php if (admin_user_can_manage($adminUser)): ?>
      <button class="btn btn-primary" type="submit" form="adminUserForm">Salvar usuário</button>
    <?php endif; ?>
  </div>
</section>

<?php if ($error !== ''): ?>
  <div class="admin-alert-card <?= admin_user_can_manage($adminUser) ? 'admin-alert-danger' : 'admin-alert-warning' ?>" role="alert">
    <strong><?= admin_user_can_manage($adminUser) ? 'Erro ao salvar' : 'Acesso restrito' ?></strong>
    <?= e($error) ?>
  </div>
<?php endif; ?>

<?php if (admin_user_can_manage($adminUser)): ?>
  <form id="adminUserForm" class="admin-form-shell" method="post" action="<?= site_url('admin/usuario-form.php' . ($isEditing ? '?id=' . (int) $usuario['id'] : '')) ?>">
    <input type="hidden" name="csrf_token" value="<?= e(admin_csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int) ($usuario['id'] ?? 0) ?>">

    <section class="admin-form-card">
      <div class="admin-form-section">
        <div class="admin-section-title"><strong>Dados de acesso</strong><p>O e-mail é usado no login e precisa ser único.</p></div>
        <div class="admin-form-grid">
          <label class="admin-field"><span>Nome</span><input name="nome" value="<?= e((string) $field('nome')) ?>" placeholder="Nome do usuário" required></label>
          <label class="admin-field"><span>E-mail</span><input name="email" type="email" value="<?= e((string) $field('email')) ?>" placeholder="usuario@arteflor.com" required></label>
          <label class="admin-field">
            <span>Perfil</span>
            <select name="perfil">
              <?php foreach (admin_user_profile_options() as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= (string) $field('perfil', 'operador') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="admin-field">
            <span>Status</span>
            <select name="ativo">
              <option value="1" <?= $activeChecked === 'checked' ? 'selected' : '' ?>>Ativo</option>
              <option value="0" <?= $activeChecked !== 'checked' ? 'selected' : '' ?>>Inativo</option>
            </select>
          </label>
        </div>
      </div>

      <div class="admin-form-section">
        <div class="admin-section-title"><strong>Senha</strong><p><?= $isEditing ? 'Preencha somente se quiser trocar a senha.' : 'Defina a senha inicial do usuário.' ?></p></div>
        <div class="admin-form-grid">
          <label class="admin-field"><span>Senha</span><input name="senha" type="password" minlength="8" autocomplete="new-password" <?= $isEditing ? '' : 'required' ?>></label>
          <label class="admin-field"><span>Confirmar senha</span><input name="confirmar_senha" type="password" minlength="8" autocomplete="new-password" <?= $isEditing ? '' : 'required' ?>></label>
        </div>
      </div>
    </section>

    <aside class="admin-form-card admin-side-card">
      <div class="client-profile-preview">
        <span><?= e($initials) ?></span>
        <h3><?= e((string) $field('nome', 'Novo usuário')) ?></h3>
        <p><?= e(admin_user_profile_options()[(string) $field('perfil', 'operador')] ?? 'Operador') ?> · <?= ((int) $field('ativo', 1) === 1) ? 'Ativo' : 'Inativo' ?></p>
      </div>
      <?php if ($isEditing): ?>
        <div class="admin-metric-list">
          <div class="admin-metric-row"><span>Criado em</span><strong><?= e(date('d/m/Y', strtotime((string) $usuario['criado_em']))) ?></strong></div>
          <div class="admin-metric-row"><span>Último acesso</span><strong><?= !empty($usuario['ultimo_acesso_em']) ? e(date('d/m', strtotime((string) $usuario['ultimo_acesso_em']))) : '-' ?></strong></div>
          <div class="admin-metric-row"><span>Atualizado</span><strong><?= !empty($usuario['atualizado_em']) ? e(date('d/m', strtotime((string) $usuario['atualizado_em']))) : '-' ?></strong></div>
        </div>
      <?php else: ?>
        <div class="admin-alert-card admin-alert-info"><strong>Novo acesso</strong>Use uma senha forte e perfil mínimo necessário para a função.</div>
      <?php endif; ?>
      <button class="btn btn-primary" type="submit">Salvar usuário</button>
      <a class="btn btn-soft" href="<?= site_url('admin/usuarios.php') ?>">Ver usuários</a>
    </aside>
  </form>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
