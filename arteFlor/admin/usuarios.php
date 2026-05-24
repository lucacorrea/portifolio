<?php
$adminTitle = 'Usuários';
$activeAdmin = 'usuarios';
require_once __DIR__ . '/../includes/users.php';
$adminUser = require_admin();
$actionError = '';

if (!admin_user_can_manage($adminUser)) {
    http_response_code(403);
}

if (admin_user_can_manage($adminUser) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $actionError = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        try {
            $userId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
            $status = (string) ($_POST['status'] ?? '');
            if (!in_array($status, ['ativo', 'inativo'], true)) {
                throw new InvalidArgumentException('Status de usuário inválido.');
            }
            $active = $status === 'ativo' ? 1 : 0;
            admin_user_update_status($userId, $active, $adminUser);
            header('Location: ' . site_url('admin/usuarios.php?updated=1'));
            exit;
        } catch (Throwable $exception) {
            error_log('[ArteFlor][admin-user-status] ' . $exception->getMessage());
            $actionError = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'Não foi possível atualizar o usuário.';
        }
    }
}

$filters = [
    'search' => admin_user_clean_text($_GET['search'] ?? '', 120),
    'perfil' => admin_user_clean_text($_GET['perfil'] ?? '', 40),
    'status' => admin_user_clean_text($_GET['status'] ?? '', 20),
];
$usuarios = admin_user_can_manage($adminUser) ? admin_user_list($filters) : [];
$stats = admin_user_can_manage($adminUser) ? admin_user_stats() : ['total' => 0, 'ativos' => 0, 'admins' => 0, 'recentes' => 0];

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Gestão</span>
    <h1>Usuários</h1>
    <p>Controle os acessos administrativos por perfil, status e último acesso.</p>
  </div>
  <?php if (admin_user_can_manage($adminUser)): ?>
    <div class="admin-hero-actions"><a class="btn btn-primary" href="<?= site_url('admin/usuario-form.php') ?>">Cadastrar usuário</a></div>
  <?php endif; ?>
</section>

<?php if (!admin_user_can_manage($adminUser)): ?>
  <div class="admin-alert-card admin-alert-danger" role="alert">
    <strong>Acesso restrito</strong>
    Apenas administradores podem gerenciar usuários do painel.
  </div>
<?php else: ?>
  <?php if (isset($_GET['saved'])): ?>
    <div class="admin-alert-card admin-alert-success" role="status">
      <strong>Usuário salvo</strong>
      O acesso administrativo foi gravado com segurança.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['updated'])): ?>
    <div class="admin-alert-card admin-alert-info" role="status">
      <strong>Status atualizado</strong>
      A situação do usuário foi alterada.
    </div>
  <?php endif; ?>

  <?php if ($actionError !== ''): ?>
    <div class="admin-alert-card admin-alert-danger" role="alert">
      <strong>Atenção</strong>
      <?= e($actionError) ?>
    </div>
  <?php endif; ?>

  <form class="admin-command-bar" method="get" action="<?= site_url('admin/usuarios.php') ?>">
    <label class="admin-field">
      <span>Buscar</span>
      <input name="search" type="search" value="<?= e($filters['search']) ?>" placeholder="Nome ou e-mail">
    </label>
    <label class="admin-field">
      <span>Perfil</span>
      <select name="perfil">
        <option value="">Todos</option>
        <?php foreach (admin_user_profile_options() as $value => $label): ?>
          <option value="<?= e($value) ?>" <?= $filters['perfil'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="admin-field">
      <span>Status</span>
      <select name="status">
        <option value="">Todos</option>
        <option value="ativo" <?= $filters['status'] === 'ativo' ? 'selected' : '' ?>>Ativos</option>
        <option value="inativo" <?= $filters['status'] === 'inativo' ? 'selected' : '' ?>>Inativos</option>
      </select>
    </label>
    <button class="btn btn-soft" type="submit">Filtrar</button>
    <a class="btn btn-outline" href="<?= site_url('admin/usuarios.php') ?>">Limpar</a>
  </form>

  <section class="admin-kpi-grid">
    <article class="admin-kpi-card"><span>Usuários</span><strong><?= (int) $stats['total'] ?></strong><small>Total cadastrado</small></article>
    <article class="admin-kpi-card"><span>Ativos</span><strong><?= (int) $stats['ativos'] ?></strong><small>Podem acessar o painel</small></article>
    <article class="admin-kpi-card"><span>Admins</span><strong><?= (int) $stats['admins'] ?></strong><small>Administradores ativos</small></article>
    <article class="admin-kpi-card"><span>Recentes</span><strong><?= (int) $stats['recentes'] ?></strong><small>Acesso nos últimos 30 dias</small></article>
  </section>

  <div class="admin-data-table">
    <table>
      <thead><tr><th>Usuário</th><th>Perfil</th><th>Status</th><th>Último acesso</th><th>Atividade</th><th>Criado em</th><th>Ações</th></tr></thead>
      <tbody>
        <?php if (empty($usuarios)): ?>
          <tr>
            <td colspan="7">
              <div class="admin-empty-row">
                <strong>Nenhum usuário encontrado</strong>
                <span>Cadastre um usuário ou ajuste os filtros.</span>
              </div>
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($usuarios as $usuario): ?>
          <?php
            $profile = (string) $usuario['perfil'];
            $active = (int) $usuario['ativo'] === 1;
          ?>
          <tr>
            <td>
              <strong><?= e((string) $usuario['nome']) ?></strong>
              <small><?= e((string) $usuario['email']) ?></small>
            </td>
            <td><span class="<?= e(admin_user_badge_class($profile)) ?>"><?= e(admin_user_profile_options()[$profile] ?? $profile) ?></span></td>
            <td><span class="<?= $active ? 'admin-badge-ok' : 'admin-badge-danger' ?>"><?= $active ? 'Ativo' : 'Inativo' ?></span></td>
            <td><?= !empty($usuario['ultimo_acesso_em']) ? e(date('d/m/Y H:i', strtotime((string) $usuario['ultimo_acesso_em']))) : 'Nunca acessou' ?></td>
            <td>
              <?= (int) $usuario['acoes_pedidos'] ?> pedido(s)
              <small><?= (int) $usuario['movimentacoes_estoque'] ?> mov. estoque</small>
            </td>
            <td><?= e(date('d/m/Y', strtotime((string) $usuario['criado_em']))) ?></td>
            <td>
              <div class="admin-table-actions">
                <a href="<?= site_url('admin/usuario-form.php?id=' . (int) $usuario['id']) ?>">Editar</a>
                <?php if ((int) $usuario['id'] !== (int) ($adminUser['id'] ?? 0)): ?>
                  <form method="post" action="<?= site_url('admin/usuarios.php') ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(admin_csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                    <input type="hidden" name="status" value="<?= $active ? 'inativo' : 'ativo' ?>">
                    <button type="submit"><?= $active ? 'Inativar' : 'Ativar' ?></button>
                  </form>
                <?php else: ?>
                  <span class="admin-action-muted">Usuário atual</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
