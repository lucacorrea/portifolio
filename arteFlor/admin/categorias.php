<?php
$adminTitle = 'Categorias';
$activeAdmin = 'categorias';
require_once __DIR__ . '/../includes/categories.php';
$adminUser = require_admin();
$actionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $actionError = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        try {
            category_update_status((int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? ''));
            header('Location: ' . site_url('admin/categorias.php?updated=1'));
            exit;
        } catch (Throwable $exception) {
            error_log('[ArteFlor][category-status] ' . $exception->getMessage());
            $actionError = 'Não foi possível atualizar a categoria.';
        }
    }
}

$filters = [
    'search' => trim((string) ($_GET['search'] ?? '')),
    'status' => (string) ($_GET['status'] ?? ''),
    'destaque' => (string) ($_GET['destaque'] ?? ''),
    'ordem' => (string) ($_GET['ordem'] ?? 'manual'),
];
$categorias = category_list($filters);
$stats = category_stats();

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Catálogo</span>
    <h1>Categorias</h1>
    <p>Organize produtos por grupos comerciais, destaque e exibição na home ou catálogo.</p>
  </div>
  <div class="admin-hero-actions"><a class="btn btn-primary" href="<?= site_url('admin/categoria-form.php') ?>">Cadastrar categoria</a></div>
</section>

<?php if (isset($_GET['saved'])): ?>
  <div class="admin-alert-card admin-alert-success" role="status">
    <strong>Categoria salva</strong>
    A categoria foi gravada no banco e já pode ser usada no cadastro de produtos.
  </div>
<?php endif; ?>

<?php if (isset($_GET['updated'])): ?>
  <div class="admin-alert-card admin-alert-info" role="status">
    <strong>Status atualizado</strong>
    A visibilidade da categoria foi alterada com segurança.
  </div>
<?php endif; ?>

<?php if ($actionError !== ''): ?>
  <div class="admin-alert-card admin-alert-danger" role="alert">
    <strong>Erro</strong>
    <?= e($actionError) ?>
  </div>
<?php endif; ?>

<form class="admin-command-bar" method="get" action="<?= site_url('admin/categorias.php') ?>">
  <label class="admin-field">
    <span>Buscar</span>
    <input name="search" type="search" value="<?= e($filters['search']) ?>" placeholder="Nome, slug ou descrição">
  </label>
  <label class="admin-field">
    <span>Status</span>
    <select name="status">
      <option value="">Todos</option>
      <?php foreach (category_status_options() as $value => $label): ?>
        <option value="<?= e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="admin-field">
    <span>Destaque</span>
    <select name="destaque">
      <option value="">Todos</option>
      <option value="home" <?= $filters['destaque'] === 'home' ? 'selected' : '' ?>>Home</option>
      <option value="catalogo" <?= $filters['destaque'] === 'catalogo' ? 'selected' : '' ?>>Catálogo</option>
      <option value="priorizadas" <?= $filters['destaque'] === 'priorizadas' ? 'selected' : '' ?>>Priorizadas</option>
    </select>
  </label>
  <label class="admin-field">
    <span>Ordenação</span>
    <select name="ordem">
      <option value="manual" <?= $filters['ordem'] === 'manual' ? 'selected' : '' ?>>Ordem manual</option>
      <option value="alfabetica" <?= $filters['ordem'] === 'alfabetica' ? 'selected' : '' ?>>Alfabética</option>
      <option value="mais_usadas" <?= $filters['ordem'] === 'mais_usadas' ? 'selected' : '' ?>>Mais usadas</option>
    </select>
  </label>
  <button class="btn btn-soft" type="submit">Filtrar</button>
  <a class="btn btn-outline" href="<?= site_url('admin/categorias.php') ?>">Limpar</a>
</form>

<section class="admin-kpi-grid">
  <article class="admin-kpi-card"><span>Categorias</span><strong><?= $stats['total'] ?></strong><small><?= $stats['ativas'] ?> ativas no catálogo</small></article>
  <article class="admin-kpi-card"><span>Mais usada</span><strong><?= e($stats['mais_usada_nome']) ?></strong><small><?= $stats['mais_usada_total'] ?> produto(s)</small></article>
  <article class="admin-kpi-card"><span>Destaques</span><strong><?= $stats['destaques'] ?></strong><small>Home, catálogo ou prioridade</small></article>
  <article class="admin-kpi-card"><span>Inativas</span><strong><?= $stats['inativas'] ?></strong><small>Ocultas operacionalmente</small></article>
</section>

<div class="admin-data-table">
  <table>
    <thead><tr><th>Categoria</th><th>Descrição</th><th>Produtos</th><th>Status</th><th>Destaque</th><th>Ações</th></tr></thead>
    <tbody>
      <?php if (empty($categorias)): ?>
        <tr>
          <td colspan="6">
            <div class="admin-empty-row">
              <strong>Nenhuma categoria encontrada</strong>
              <span>Cadastre uma categoria ou ajuste os filtros.</span>
            </div>
          </td>
        </tr>
      <?php endif; ?>
      <?php foreach ($categorias as $categoria): ?>
        <?php
          $status = (string) $categoria['status'];
          $tags = [];
          if (!empty($categoria['exibir_home'])) $tags[] = 'Home';
          if (!empty($categoria['exibir_catalogo'])) $tags[] = 'Catálogo';
          if (!empty($categoria['priorizar_listagem'])) $tags[] = 'Prioridade';
          $highlight = !empty($tags) ? implode(' + ', $tags) : 'Normal';
        ?>
        <tr>
          <td>
            <div class="admin-avatar-line">
              <span class="admin-avatar color-avatar" style="--avatar-color: <?= e((string) $categoria['cor_apoio']) ?>"></span>
              <div class="admin-item-title"><strong><?= e($categoria['nome']) ?></strong><small><?= e($categoria['slug']) ?></small></div>
            </div>
          </td>
          <td><?= e((string) ($categoria['descricao'] ?? 'Sem descrição')) ?></td>
          <td><?= (int) $categoria['total_produtos'] ?></td>
          <td><span class="<?= $status === 'ativa' ? 'admin-badge-ok' : 'admin-badge-danger' ?>"><?= e(category_status_options()[$status] ?? $status) ?></span></td>
          <td><span class="<?= $highlight === 'Normal' ? 'admin-badge-info' : 'admin-badge-soft' ?>"><?= e($highlight) ?></span></td>
          <td>
            <div class="admin-table-actions">
              <a href="<?= site_url('admin/categoria-form.php?id=' . (int) $categoria['id']) ?>">Editar</a>
              <form method="post" action="<?= site_url('admin/categorias.php') ?>">
                <input type="hidden" name="csrf_token" value="<?= e(admin_csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int) $categoria['id'] ?>">
                <input type="hidden" name="status" value="<?= $status === 'ativa' ? 'inativa' : 'ativa' ?>">
                <button type="submit"><?= $status === 'ativa' ? 'Ocultar' : 'Ativar' ?></button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
