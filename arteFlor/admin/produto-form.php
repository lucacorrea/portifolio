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
$adminMessage = product_admin_message_from_query();
$csrfToken = admin_csrf_token();
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
            header('Location: ' . site_url('admin/produtos.php?success=produto_salvo'));
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
$tagsText = (string) ($_POST['tags'] ?? ($isEditing ? product_tags_text((int) $product['id']) : ''));
$previewImage = !empty($images[0]['url'] ?? '') ? product_public_image_url($images[0]['url']) : '';
$previewStockValue = $field('estoque', 0);
$previewMinStockValue = $field('estoque_minimo', 0);
$previewPriceValue = $field('preco', 0);
$previewPromoValue = $field('preco_promocional', 0);
$previewStockProduct = [
    'estoque' => is_scalar($previewStockValue) ? (int) $previewStockValue : 0,
    'estoque_minimo' => is_scalar($previewMinStockValue) ? (int) $previewMinStockValue : 0,
];
$previewStockStatus = product_inventory_status($previewStockProduct);
$previewStockPercent = product_inventory_percent($previewStockProduct);
$previewRegularPrice = product_normalize_money(is_scalar($previewPriceValue) ? $previewPriceValue : 0);
$previewPromoPrice = product_normalize_money(is_scalar($previewPromoValue) ? $previewPromoValue : 0);
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

<?php if ($adminMessage): ?>
  <div class="admin-alert-card <?= e($adminMessage['class']) ?>" role="status">
    <strong><?= e($adminMessage['title']) ?></strong>
    <?= e($adminMessage['body']) ?>
  </div>
<?php endif; ?>

<form id="productForm" class="admin-form-shell" method="post" action="<?= site_url('admin/produto-form.php' . ($isEditing ? '?id=' . (int) $product['id'] : '')) ?>" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
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
          <select name="categoria_nome" data-product-preview-category-source required>
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
          <select name="status" data-product-preview-status-source required>
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
          <input name="estoque" type="number" min="0" value="<?= e((string) $field('estoque', 0)) ?>" data-product-preview-stock-source>
        </label>
        <label class="admin-field">
          <span>Estoque mínimo</span>
          <input name="estoque_minimo" type="number" min="0" value="<?= e((string) $field('estoque_minimo', 0)) ?>" data-product-preview-min-stock-source>
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
        <label class="admin-field full">
          <span>Tags</span>
          <input name="tags" value="<?= e($tagsText) ?>" placeholder="Romântico, Mais vendido, Presente especial">
          <small>Separe por vírgulas. As tags aparecem no admin e no catálogo público.</small>
        </label>
      </div>

      <div class="product-live-preview" data-product-image-preview hidden></div>

      <?php if (!empty($images)): ?>
        <div class="admin-section-title compact product-images-note">
          <strong>Imagens cadastradas</strong>
          <p>A imagem principal aparece primeiro no catálogo e na página do produto.</p>
        </div>
        <div class="product-image-admin-grid">
          <?php foreach ($images as $image): ?>
            <?php $imageId = (int) $image['id']; ?>
            <figure class="<?= !empty($image['principal']) ? 'is-primary' : '' ?>">
              <img src="<?= e(product_public_image_url($image['url'])) ?>" alt="<?= e($image['texto_alternativo'] ?? 'Imagem do produto') ?>">
              <figcaption><?= !empty($image['principal']) ? 'Principal' : 'Imagem' ?></figcaption>
              <div class="product-image-actions">
                <?php if (empty($image['principal'])): ?>
                  <button type="submit" form="productImagePrimary<?= $imageId ?>">Definir principal</button>
                <?php endif; ?>
                <button class="admin-action-danger" type="submit" form="productImageRemove<?= $imageId ?>">Remover</button>
              </div>
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
        <div class="product-preview-topline">
          <span class="badge">Preview</span>
          <span class="admin-badge-soft" data-product-preview-category><?= e($categoryName) ?></span>
        </div>
        <h3 data-product-preview-name><?= e((string) $field('nome', 'Novo produto')) ?></h3>
        <p data-product-preview-description><?= e((string) $field('descricao_curta', 'Produto pronto para catálogo.')) ?></p>
        <div class="product-preview-price">
          <strong data-product-preview-price><?= money_br($previewPromoPrice > 0 ? $previewPromoPrice : $previewRegularPrice) ?></strong>
          <span data-product-preview-original-price <?= $previewPromoPrice > 0 ? '' : 'hidden' ?>><?= $previewPromoPrice > 0 ? 'Original: ' . money_br($previewRegularPrice) : '' ?></span>
        </div>
        <span class="admin-badge-soft product-preview-status" data-product-preview-status><?= e(status_label($statusValue)) ?></span>
        <div class="product-preview-stock inventory-stock-cell">
          <div class="inventory-stock-meta">
            <strong data-product-preview-stock-value>Estoque: <?= (int) $previewStockProduct['estoque'] ?> un.</strong>
            <small data-product-preview-min-stock-value>Mínimo: <?= (int) $previewStockProduct['estoque_minimo'] ?> un.</small>
          </div>
          <div class="inventory-stock-bar" aria-hidden="true">
            <span class="inventory-stock-fill <?= e($previewStockStatus) ?>" data-product-preview-stock-fill style="width: <?= $previewStockPercent ?>%"></span>
          </div>
          <span class="<?= e(product_inventory_badge_class($previewStockStatus)) ?>" data-product-preview-stock-label><?= e(product_inventory_label($previewStockStatus)) ?></span>
        </div>
        <div class="product-preview-badges">
          <span data-product-preview-flag="exibir_catalogo">Exibir no catálogo</span>
          <span data-product-preview-flag="permitir_venda_online">Venda online</span>
          <span data-product-preview-flag="disponivel_pdv">Disponível no PDV</span>
          <span data-product-preview-flag="destaque">Destaque</span>
          <span data-product-preview-flag="sob_encomenda">Sob encomenda</span>
        </div>
      </div>
    </div>
    <div class="admin-check-list">
      <label><input name="exibir_catalogo" type="checkbox" value="1" <?= $checked('exibir_catalogo', true) ?> data-product-preview-flag-source="exibir_catalogo"> Exibir no catálogo</label>
      <label><input name="permitir_venda_online" type="checkbox" value="1" <?= $checked('permitir_venda_online', true) ?> data-product-preview-flag-source="permitir_venda_online"> Permitir venda online</label>
      <label><input name="disponivel_pdv" type="checkbox" value="1" <?= $checked('disponivel_pdv', true) ?> data-product-preview-flag-source="disponivel_pdv"> Disponível no PDV</label>
      <label><input name="destaque" type="checkbox" value="1" <?= $checked('destaque') ?> data-product-preview-flag-source="destaque"> Produto em destaque</label>
      <label><input name="sob_encomenda" type="checkbox" value="1" <?= $checked('sob_encomenda') ?> data-product-preview-flag-source="sob_encomenda"> Produto sob encomenda</label>
    </div>
    <button class="btn btn-primary" type="submit">Salvar produto</button>
    <a class="btn btn-soft" href="<?= site_url('admin/produtos.php') ?>">Ver listagem</a>
  </aside>
</form>
<?php if ($isEditing && !empty($images)): ?>
  <?php foreach ($images as $image): ?>
    <?php $imageId = (int) $image['id']; ?>
    <form
      id="productImagePrimary<?= $imageId ?>"
      method="post"
      action="<?= site_url('admin/actions/produto-imagem-principal.php') ?>"
      data-confirm="Definir esta imagem como principal?"
      hidden
    >
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
      <input type="hidden" name="image_id" value="<?= $imageId ?>">
    </form>
    <form
      id="productImageRemove<?= $imageId ?>"
      method="post"
      action="<?= site_url('admin/actions/produto-imagem-remover.php') ?>"
      data-confirm="Remover esta imagem do produto?"
      hidden
    >
      <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
      <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
      <input type="hidden" name="image_id" value="<?= $imageId ?>">
    </form>
  <?php endforeach; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
