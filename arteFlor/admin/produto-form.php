<?php
$adminTitle = 'Cadastro de produto';
$activeAdmin = 'produto-form';
$pageScripts = ['js/product-form.js'];
require_once __DIR__ . '/../includes/products.php';
$adminUser = require_admin();

$productId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$product = $productId > 0 ? product_find($productId) : null;
$images = $product ? product_images((int) $product['id']) : [];
$categories = product_categories();
$error = '';

if ($productId > 0 && !$product && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
    $error = 'Produto não encontrado.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        try {
            $savedId = product_save_from_request();
            header('Location: ' . site_url('admin/produtos.php?saved=1'));
            exit;
        } catch (Throwable $exception) {
            error_log('[ArteFlor][product-save] ' . $exception->getMessage());
            $error = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'Não foi possível salvar o produto. Verifique os dados e tente novamente.';
            $product = array_merge($product ?? [], $_POST);
            $images = $productId > 0 ? product_images($productId) : [];
        }
    }
}

$isEditing = !empty($product['id']);
$field = fn(string $key, mixed $default = ''): mixed => $_POST[$key] ?? $product[$key] ?? $default;
$categoryName = (string) ($_POST['categoria_nome'] ?? $product['categoria_nome'] ?? 'Buquês');
$statusValue = (string) $field('status', 'disponivel');
$previewImage = !empty($images[0]['url'] ?? '') ? product_public_image_url($images[0]['url']) : '';
$checked = function (string $key, bool $default = false) use ($product): string {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return isset($_POST[$key]) ? 'checked' : '';
    }

    if ($product && array_key_exists($key, $product)) {
        return !empty($product[$key]) ? 'checked' : '';
    }

    return $default ? 'checked' : '';
};

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Cadastro</span>
    <h1><?= $isEditing ? 'Editar produto' : 'Cadastrar produto' ?></h1>
    <p>Cadastro real no banco com upload múltiplo de imagens validadas.</p>
  </div>
  <div class="admin-hero-actions">
    <a class="btn btn-soft" href="<?= site_url('admin/produtos.php') ?>">Voltar para produtos</a>
    <button class="btn btn-primary" type="submit" form="productForm">Salvar produto</button>
  </div>
</section>

<?php if ($error !== ''): ?>
  <div class="admin-alert-card admin-alert-danger" role="alert">
    <strong>Erro ao salvar</strong>
    <?= e($error) ?>
  </div>
<?php endif; ?>

<form id="productForm" class="admin-form-shell" method="post" action="<?= site_url('admin/produto-form.php' . ($isEditing ? '?id=' . (int) $product['id'] : '')) ?>" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= e(admin_csrf_token()) ?>">
  <input type="hidden" name="id" value="<?= (int) ($product['id'] ?? 0) ?>">

  <section class="admin-form-card">
    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Dados principais</strong><p>Informações exibidas no catálogo e no detalhe do produto.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field">
          <span>Nome</span>
          <input name="nome" value="<?= e((string) $field('nome')) ?>" placeholder="Buquê de Rosas Vermelhas" data-product-preview-name-source required>
        </label>
        <label class="admin-field">
          <span>Categoria</span>
          <select name="categoria_nome" required>
            <?php foreach ($categories as $category): ?>
              <option value="<?= e($category['nome']) ?>" <?= $categoryName === $category['nome'] ? 'selected' : '' ?>>
                <?= e($category['nome']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="admin-field">
          <span>SKU</span>
          <input name="sku" value="<?= e((string) $field('sku')) ?>" placeholder="Gerado automaticamente se vazio">
        </label>
        <label class="admin-field">
          <span>Status</span>
          <select name="status" required>
            <?php foreach (product_status_options() as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $statusValue === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="admin-field full">
          <span>Descrição curta</span>
          <input name="descricao_curta" value="<?= e((string) $field('descricao_curta')) ?>" placeholder="Resumo que aparece no card" data-product-preview-description-source>
        </label>
        <label class="admin-field full">
          <span>Descrição completa</span>
          <textarea name="descricao_completa" placeholder="Composição, ocasião indicada, cuidados e observações"><?= e((string) $field('descricao_completa')) ?></textarea>
        </label>
      </div>
    </div>

    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Preço e estoque</strong><p>Campos comerciais para venda online e PDV.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field">
          <span>Preço</span>
          <input name="preco" type="number" min="0" step="0.01" value="<?= e((string) $field('preco', '')) ?>" placeholder="149.90" data-product-preview-price-source required>
        </label>
        <label class="admin-field">
          <span>Preço promocional</span>
          <input name="preco_promocional" type="number" min="0" step="0.01" value="<?= e((string) $field('preco_promocional', '')) ?>" placeholder="129.90" data-product-preview-promo-source>
        </label>
        <label class="admin-field">
          <span>Estoque</span>
          <input name="estoque" type="number" min="0" value="<?= e((string) $field('estoque', 0)) ?>">
        </label>
        <label class="admin-field">
          <span>Estoque mínimo</span>
          <input name="estoque_minimo" type="number" min="0" value="<?= e((string) $field('estoque_minimo', 0)) ?>">
        </label>
      </div>
    </div>

    <div class="admin-form-section">
      <div class="admin-section-title"><strong>Fotos, tags e SEO</strong><p>Envie imagens reais do produto. SVG e arquivos executáveis não são aceitos por segurança.</p></div>
      <div class="admin-form-grid">
        <label class="admin-field full product-upload-field">
          <span>Imagens do produto</span>
          <input name="imagens[]" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif" multiple data-product-images-input>
          <small>Você pode enviar até 8 imagens por vez, com até 5 MB cada. Formatos: JPG, PNG, WEBP, GIF e AVIF.</small>
        </label>
        <label class="admin-field">
          <span>Slug/SEO</span>
          <input name="slug" value="<?= e((string) $field('slug')) ?>" placeholder="Gerado automaticamente se vazio">
        </label>
      </div>

      <div class="product-live-preview" data-product-image-preview hidden></div>

      <?php if (!empty($images)): ?>
        <div class="product-image-admin-grid">
          <?php foreach ($images as $image): ?>
            <figure>
              <img src="<?= e(product_public_image_url($image['url'])) ?>" alt="<?= e($image['texto_alternativo'] ?? 'Imagem do produto') ?>">
              <figcaption><?= !empty($image['principal']) ? 'Principal' : 'Imagem' ?></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <aside class="admin-form-card admin-side-card">
    <div class="admin-preview-product">
      <div class="admin-preview-media" data-product-main-preview>
        <?php if ($previewImage !== ''): ?>
          <img src="<?= e($previewImage) ?>" alt="Preview do produto">
        <?php else: ?>
          <div class="admin-upload-placeholder">A&F</div>
        <?php endif; ?>
      </div>
      <div>
        <span class="badge">Preview</span>
        <h3 data-product-preview-name><?= e((string) $field('nome', 'Novo produto')) ?></h3>
        <p data-product-preview-description><?= e((string) $field('descricao_curta', 'Produto pronto para catálogo.')) ?></p>
        <strong data-product-preview-price><?= money_br((float) $field('preco_promocional', 0) > 0 ? (float) $field('preco_promocional', 0) : (float) $field('preco', 0)) ?></strong>
      </div>
    </div>
    <div class="admin-check-list">
      <label><input name="exibir_catalogo" type="checkbox" value="1" <?= $checked('exibir_catalogo', true) ?>> Exibir no catálogo</label>
      <label><input name="permitir_venda_online" type="checkbox" value="1" <?= $checked('permitir_venda_online', true) ?>> Permitir venda online</label>
      <label><input name="disponivel_pdv" type="checkbox" value="1" <?= $checked('disponivel_pdv', true) ?>> Disponível no PDV</label>
      <label><input name="destaque" type="checkbox" value="1" <?= $checked('destaque') ?>> Produto em destaque</label>
      <label><input name="sob_encomenda" type="checkbox" value="1" <?= $checked('sob_encomenda') ?>> Produto sob encomenda</label>
    </div>
    <button class="btn btn-primary" type="submit">Salvar produto</button>
    <a class="btn btn-soft" href="<?= site_url('admin/produtos.php') ?>">Ver listagem</a>
  </aside>
</form>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
