<?php
$adminTitle = 'Cadastro de categoria';
$activeAdmin = 'categoria-form';
require_once __DIR__ . '/../includes/categories.php';
$adminUser = require_admin();

$categoryId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$category = $categoryId > 0 ? category_find($categoryId) : null;
$error = '';

if ($categoryId > 0 && !$category && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
    $error = 'Categoria não encontrada.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        try {
            category_save_from_request();
            header('Location: ' . site_url('admin/categorias.php?saved=1'));
            exit;
        } catch (Throwable $exception) {
            error_log('[ArteFlor][category-save] ' . $exception->getMessage());
            $error = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'Não foi possível salvar a categoria. Verifique os dados e tente novamente.';
            $category = array_merge($category ?? [], $_POST);
        }
    }
}

$isEditing = !empty($category['id']);
$field = fn(string $key, mixed $default = ''): mixed => $_POST[$key] ?? $category[$key] ?? $default;
$checked = function (string $key, bool $default = false) use ($category): string {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return isset($_POST[$key]) ? 'checked' : '';
    }

    if ($category && array_key_exists($key, $category)) {
        return !empty($category[$key]) ? 'checked' : '';
    }

    return $default ? 'checked' : '';
};
$previewColor = (string) $field('cor_apoio', '#4F8F6B');

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Cadastro</span>
    <h1><?= $isEditing ? 'Editar categoria' : 'Cadastrar categoria' ?></h1>
    <p>Defina nome, slug, ordem, cor de apoio e onde a categoria aparece no catálogo.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="btn btn-soft" href="<?= site_url('admin/categorias.php') ?>">Voltar para categorias</a>
    <button class="btn btn-primary" type="submit" form="categoryForm">Salvar categoria</button>
  </div>
</section>

<?php if ($error !== ''): ?>
  <div class="admin-alert-card admin-alert-danger" role="alert">
    <strong>Erro ao salvar</strong>
    <?= e($error) ?>
  </div>
<?php endif; ?>

<form id="categoryForm" class="admin-form-shell" method="post" action="<?= site_url('admin/categoria-form.php' . ($isEditing ? '?id=' . (int) $category['id'] : '')) ?>">
  <input type="hidden" name="csrf_token" value="<?= e(admin_csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int) ($category['id'] ?? 0) ?>">

  <section class="admin-form-card">
    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Dados principais</strong><p>Campos reais para organizar o catálogo no banco.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field">
          <span>Nome</span>
          <input name="nome" value="<?= e((string) $field('nome')) ?>" placeholder="Ex: Buquês" required>
        </label>
        <label class="admin-field">
          <span>Slug</span>
          <input name="slug" value="<?= e((string) $field('slug')) ?>" placeholder="Gerado automaticamente se vazio">
        </label>
        <label class="admin-field">
          <span>Ícone textual</span>
          <input name="icone_textual" value="<?= e((string) $field('icone_textual')) ?>" placeholder="Buquê">
        </label>
        <label class="admin-field">
          <span>Status</span>
          <select name="status">
            <?php foreach (category_status_options() as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= (string) $field('status', 'ativa') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="admin-field full">
          <span>Descrição</span>
          <input name="descricao" value="<?= e((string) $field('descricao')) ?>" placeholder="Resumo para aparecer no catálogo">
        </label>
      </div>
    </div>

    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Configuração visual</strong><p>Controle de ordem e destaque.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field">
          <span>Ordem</span>
          <input name="ordem" type="number" min="0" value="<?= e((string) $field('ordem', 0)) ?>">
        </label>
        <label class="admin-field">
          <span>Cor de apoio</span>
          <input name="cor_apoio" type="color" value="<?= e(preg_match('/^#[0-9A-Fa-f]{6}$/', $previewColor) ? $previewColor : '#4F8F6B') ?>">
        </label>
      </div>
    </div>
  </section>

  <aside class="admin-form-card admin-side-card">
    <div class="category-preview" style="--category-color: <?= e(preg_match('/^#[0-9A-Fa-f]{6}$/', $previewColor) ? $previewColor : '#4F8F6B') ?>">
      <span></span>
      <strong><?= e((string) $field('nome', 'Nova categoria')) ?></strong>
      <p><?= e((string) $field('descricao', 'Categoria pronta para organizar produtos.')) ?></p>
    </div>
    <div class="admin-check-list">
      <label><input name="exibir_home" type="checkbox" value="1" <?= $checked('exibir_home', true) ?>> Exibir na home</label>
      <label><input name="exibir_catalogo" type="checkbox" value="1" <?= $checked('exibir_catalogo', true) ?>> Exibir no catálogo</label>
      <label><input name="priorizar_listagem" type="checkbox" value="1" <?= $checked('priorizar_listagem') ?>> Priorizar na listagem</label>
    </div>
    <button class="btn btn-primary" type="submit">Salvar categoria</button>
    <a class="btn btn-soft" href="<?= site_url('admin/categorias.php') ?>">Ver categorias</a>
  </aside>
</form>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
