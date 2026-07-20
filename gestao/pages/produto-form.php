<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Repositories\SettingsRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Services\ProductService;
use App\Services\UploadService;

Auth::requireLogin();

$user = Auth::user();
$empresaId = (int)($user['empresa_id'] ?? 0);
$currentNivel = (string)($user['nivel'] ?? '');
$productService = new ProductService();
$uploadService = new UploadService();

function canProductFormAccess(string $action, string $nivel): bool
{
    $permissions = [
        'create' => ['admin', 'gerente', 'estoquista'],
        'edit' => ['admin', 'gerente', 'estoquista'],
    ];

    return in_array($nivel, $permissions[$action] ?? [], true);
}

function requireProductFormAccess(string $action, string $nivel): void
{
    if (!canProductFormAccess($action, $nivel)) {
        throw new RuntimeException('Você não tem permissão para executar esta ação.');
    }
}

function redirectProductList(string $type, string $message): void
{
    $_SESSION['product_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    header('Location: produtos.php');
    exit;
}

function productFormImageUrl(mixed $image): string
{
    $path = ltrim(str_replace('\\', '/', trim((string)$image)), '/');

    if ($path === '' || str_contains($path, '..')) {
        return '../assets/img/prod-placeholder.svg';
    }

    if (str_starts_with($path, 'uploads/produtos/')) {
        return '../' . $path;
    }

    if (str_starts_with($path, 'assets/img/')) {
        return '../' . $path;
    }

    if (basename($path) === $path) {
        return '../assets/img/' . rawurlencode($path);
    }

    return '../assets/img/prod-placeholder.svg';
}

function removePreviousProductImage(string $image, string $newImage): void
{
    if ($image === '' || $image === $newImage || !preg_match('#^uploads/produtos/[A-Za-z0-9._-]+$#', $image)) {
        return;
    }

    (new UploadService())->removeProductUpload(BASE_PATH . '/' . $image);
}

function productFormData(array $source, array $fallback = []): array
{
    $fields = [
        'id',
        'name',
        'sku',
        'barcode',
        'category',
        'description',
        'brand',
        'unit',
        'packageQuantity',
        'ncm',
        'cest',
        'manufacturer',
        'cost',
        'price',
        'stock',
        'minStock',
        'lot',
        'expiry',
        'image',
    ];
    $data = [];

    foreach ($fields as $field) {
        $data[$field] = $source[$field] ?? $fallback[$field] ?? '';
    }

    return $data;
}

$requestedId = 0;
if (isset($_GET['id'])) {
    $validatedId = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($validatedId === false) {
        redirectProductList('danger', 'Produto inválido.');
    }
    $requestedId = (int)$validatedId;
}

$product = null;
$formError = null;
$uploadedAbsolutePath = null;
$defaultMinStock = 0;

try {
    $settings = (new SettingsRepository())->getConfiguracoes($empresaId);
    $defaultMinStock = max(0, (float)($settings['default_min_stock'] ?? 0));
} catch (Throwable $e) {
    log_app_exception($e);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $requestedId > 0) {
    try {
        requireProductFormAccess('edit', $currentNivel);
        $product = $productService->find($empresaId, $requestedId);

        if (!$product) {
            redirectProductList('danger', 'Produto não encontrado.');
        }
    } catch (RuntimeException $e) {
        http_response_code(403);
        $formError = $e->getMessage();
    } catch (Throwable $e) {
        log_app_exception($e);
        $formError = 'Não foi possível carregar o produto.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        requireProductFormAccess('create', $currentNivel);
    } catch (RuntimeException $e) {
        http_response_code(403);
        $formError = $e->getMessage();
    }
}

$formData = productFormData($product ?? [], [
    'id' => $requestedId,
    'minStock' => $defaultMinStock,
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = productFormData($_POST, ['minStock' => $defaultMinStock]);

    try {
        if (!Csrf::validate((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Sessão expirada. Recarregue a página e tente novamente.');
        }

        $rawProductId = trim((string)($_POST['id'] ?? '0'));
        $postedId = filter_var($rawProductId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($rawProductId !== '' && $rawProductId !== '0' && $postedId === false) {
            throw new InvalidArgumentException('Produto inválido.');
        }
        $productId = $postedId === false ? 0 : (int)$postedId;
        requireProductFormAccess($productId > 0 ? 'edit' : 'create', $currentNivel);

        $existingProduct = null;
        if ($productId > 0) {
            $existingProduct = $productService->find($empresaId, $productId);
            if (!$existingProduct) {
                throw new InvalidArgumentException('Produto não encontrado.');
            }
        }

        $currentImage = (string)($existingProduct['image'] ?? '');
        $formData['image'] = $currentImage;
        [$image, $uploadedAbsolutePath] = $uploadService->storeProductImage(
            $empresaId,
            is_array($_FILES['imageFile'] ?? null) ? $_FILES['imageFile'] : null,
            $currentImage
        );

        $payload = [
            'id' => $productId,
            'name' => (string)($_POST['name'] ?? ''),
            'sku' => (string)($_POST['sku'] ?? ''),
            'barcode' => (string)($_POST['barcode'] ?? ''),
            'category' => (string)($_POST['category'] ?? ''),
            'description' => (string)($_POST['description'] ?? ''),
            'brand' => (string)($_POST['brand'] ?? ''),
            'unit' => (string)($_POST['unit'] ?? ''),
            'packageQuantity' => (string)($_POST['packageQuantity'] ?? ''),
            'ncm' => (string)($_POST['ncm'] ?? ''),
            'cest' => (string)($_POST['cest'] ?? ''),
            'manufacturer' => (string)($_POST['manufacturer'] ?? ''),
            'cost' => $_POST['cost'] ?? '',
            'price' => $_POST['price'] ?? '',
            'stock' => $_POST['stock'] ?? '',
            'minStock' => $_POST['minStock'] ?? '',
            'lot' => (string)($_POST['lot'] ?? ''),
            'expiry' => (string)($_POST['expiry'] ?? ''),
            'image' => $image,
        ];

        $productService->save($empresaId, $payload);

        if ($uploadedAbsolutePath !== null && $image !== $currentImage) {
            removePreviousProductImage($currentImage, $image);
        }

        redirectProductList('success', $productId > 0 ? 'Produto atualizado com sucesso.' : 'Produto cadastrado com sucesso.');
    } catch (InvalidArgumentException | RuntimeException $e) {
        $uploadService->removeProductUpload($uploadedAbsolutePath);
        $formError = $e->getMessage();
    } catch (Throwable $e) {
        $uploadService->removeProductUpload($uploadedAbsolutePath);
        log_app_exception($e);
        $formError = 'Não foi possível salvar o produto. Verifique os dados e tente novamente.';
    }
}

$isEditing = (int)($formData['id'] ?? 0) > 0;
$canRenderForm = canProductFormAccess($isEditing ? 'edit' : 'create', $currentNivel);
if (!$canRenderForm) {
    http_response_code(403);
}
$pageId = 'produto-form-server';
$pageTitle = $isEditing ? 'Editar produto' : 'Novo produto';
$activeMenu = 'produtos';
$previewUrl = productFormImageUrl($formData['image'] ?? '');
$barcodeScriptVersion = (string)(@filemtime(BASE_PATH . '/assets/js/produto-codigo-barras.js') ?: time());
require_once __DIR__ . '/layout/header.php';
?>

<style>
  .product-alert { margin-bottom: 14px; padding: 13px 15px; color: var(--red); background: rgba(230,83,103,.1); border: 1px solid rgba(230,83,103,.25); border-radius: 16px; font-size: 13px; font-weight: 750; }
  .product-form-help { margin: 8px 0 0; color: var(--muted); font-size: 11px; font-weight: 650; }
  .product-form-preview > div { display: flex; flex-wrap: wrap; gap: 6px; }
  .product-form-preview .file-btn { flex: 1 1 135px; }
  .product-form-section { display: grid; gap: 12px; padding-top: 16px; margin-top: 16px; border-top: 1px solid var(--line); }
  .product-form-section:first-of-type { padding-top: 0; margin-top: 0; border-top: 0; }
  .product-form-section-title { margin: 0; color: var(--blue); font-size: 13px; font-weight: 900; letter-spacing: 0; }
  .product-barcode-actions { display: grid; gap: 9px; align-items: center; }
  .product-barcode-actions .secondary-btn { min-height: 44px; }
  .product-lookup-message { display: grid; gap: 8px; margin-top: 10px; padding: 12px; border-radius: 14px; border: 1px solid var(--line); color: var(--ink); background: #fff; font-size: 12px; font-weight: 760; }
  .product-lookup-message[hidden] { display: none; }
  .product-lookup-message.success { color: var(--green); background: rgba(37,196,132,.1); border-color: rgba(37,196,132,.25); }
  .product-lookup-message.warning { color: #8A4A00; background: rgba(255,181,71,.14); border-color: rgba(255,181,71,.3); }
  .product-lookup-message.danger { color: var(--red); background: rgba(230,83,103,.1); border-color: rgba(230,83,103,.25); }
  .product-existing-card { display: grid; gap: 6px; padding: 12px; border-radius: 14px; color: var(--ink); background: #fff; border: 1px solid rgba(230,83,103,.25); }
  .product-existing-card strong { font-size: 13px; }
  .product-existing-card span { color: var(--muted); font-size: 11px; }
  .product-existing-card a { min-height: 38px; margin-top: 4px; }
  .product-autofilled { border-color: rgba(37,196,132,.55) !important; box-shadow: 0 0 0 3px rgba(37,196,132,.13); transition: box-shadow .2s ease, border-color .2s ease; }
  .product-scanner-backdrop { position: fixed; inset: 0; z-index: 100; display: none; place-items: center; padding: 18px; background: rgba(17,48,77,.48); backdrop-filter: blur(6px); }
  .product-scanner-backdrop.open { display: grid; }
  .product-scanner-modal { width: min(100%, 520px); padding: 16px; background: #fff; border-radius: 22px; box-shadow: 0 24px 70px rgba(17,48,77,.28); }
  .product-scanner-modal h2 { margin: 0; font-size: 19px; letter-spacing: 0; }
  .product-scanner-modal p { margin: 6px 0 12px; color: var(--muted); font-size: 12px; font-weight: 750; }
  .product-scanner-frame { position: relative; overflow: hidden; border-radius: 16px; background: #091B32; }
  .product-scanner-frame::after { content: ""; position: absolute; inset: 18%; border: 2px solid rgba(255,255,255,.78); border-radius: 14px; box-shadow: 0 0 0 999px rgba(0,0,0,.18); pointer-events: none; }
  .product-scanner-video { width: 100%; min-height: 260px; max-height: 58vh; display: block; object-fit: cover; }
  .product-scanner-status { min-height: 18px; margin-top: 10px; color: var(--muted); font-size: 12px; font-weight: 800; }
  .product-scanner-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 12px; }
  .product-scanner-actions .ghost-btn { width: auto; min-width: 130px; }
  @media (max-width: 620px) {
    .product-barcode-actions { grid-template-columns: 1fr; }
  }
  @media (min-width: 621px) {
    .product-barcode-actions { grid-template-columns: minmax(0, 1fr) auto; }
    .product-barcode-actions .secondary-btn { width: auto; padding: 0 14px; }
  }
  @media (min-width: 720px) { .form-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>

<header class="plain-header">
  <div class="page-title-row">
    <a class="back-btn" href="produtos.php" aria-label="Voltar">‹</a>
    <div>
      <p class="micro-label dark-text">Cadastro</p>
      <h1><?= $isEditing ? 'Editar produto' : 'Novo produto' ?></h1>
    </div>
    <span></span>
  </div>
</header>

<section class="content-pad">
  <?php if ($formError !== null): ?>
    <div class="product-alert" role="alert"><?= e($formError) ?></div>
  <?php endif; ?>

  <?php if ($canRenderForm && ($formError === null || $_SERVER['REQUEST_METHOD'] === 'POST')): ?>
    <form method="post" enctype="multipart/form-data" class="form-card">
      <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
      <input type="hidden" name="id" value="<?= (int)($formData['id'] ?? 0) ?>">

      <div class="product-form-preview">
        <img id="productPreview" src="<?= e($previewUrl) ?>" alt="Prévia do produto">
        <div>
          <label class="file-btn">
            Escolher imagem
            <input id="productImageInput" name="imageFile" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
          </label>
          <input id="productCameraInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" capture="environment" hidden>
          <button type="button" class="file-btn" data-select-product-image>Tirar foto</button>
        </div>
      </div>
      <p class="product-form-help">JPG, PNG, WEBP ou GIF. Fotos grandes são reduzidas automaticamente para até 2MB.</p>

      <div class="product-form-section section-gap-small">
        <h2 class="product-form-section-title">Identificação</h2>
        <div class="form-grid">
          <div class="field"><label for="productName">Nome do produto</label><input id="productName" name="name" maxlength="180" value="<?= e($formData['name']) ?>" required></div>
          <div class="field"><label for="productSku">SKU / Código interno</label><input id="productSku" name="sku" maxlength="80" value="<?= e($formData['sku']) ?>"></div>
          <div class="field product-barcode-field">
            <label for="productBarcode">Código de barras / GTIN</label>
            <div class="product-barcode-actions">
              <input id="productBarcode" name="barcode" maxlength="80" inputmode="numeric" autocomplete="off" value="<?= e($formData['barcode']) ?>">
              <button id="scanBarcodeButton" type="button" class="secondary-btn">Escanear</button>
            </div>
            <p class="product-form-help">Digite ou escaneie o código do produto.</p>
            <div id="barcodeLookupMessage" class="product-lookup-message" aria-live="polite" hidden></div>
          </div>
          <div class="field"><label for="productCategory">Categoria</label><input id="productCategory" name="category" maxlength="120" value="<?= e($formData['category']) ?>" required></div>
          <div class="field"><label for="productBrand">Marca</label><input id="productBrand" name="brand" maxlength="150" value="<?= e($formData['brand']) ?>"></div>
          <div class="field"><label for="productDescription">Descrição</label><textarea id="productDescription" name="description"><?= e($formData['description']) ?></textarea></div>
        </div>
      </div>

      <div class="product-form-section">
        <h2 class="product-form-section-title">Embalagem e fiscal</h2>
        <div class="form-grid">
          <div class="field"><label for="productUnit">Unidade</label><input id="productUnit" name="unit" maxlength="20" value="<?= e($formData['unit']) ?>"></div>
          <div class="field"><label for="productPackageQuantity">Quantidade da embalagem</label><input id="productPackageQuantity" name="packageQuantity" maxlength="50" value="<?= e($formData['packageQuantity']) ?>"></div>
          <div class="field"><label for="productNcm">NCM</label><input id="productNcm" name="ncm" maxlength="8" inputmode="numeric" value="<?= e($formData['ncm']) ?>"></div>
          <div class="field"><label for="productCest">CEST</label><input id="productCest" name="cest" maxlength="7" inputmode="numeric" value="<?= e($formData['cest']) ?>"></div>
          <div class="field"><label for="productManufacturer">Fabricante</label><input id="productManufacturer" name="manufacturer" maxlength="150" value="<?= e($formData['manufacturer']) ?>"></div>
        </div>
      </div>

      <div class="product-form-section">
        <h2 class="product-form-section-title">Estoque e preço</h2>
        <div class="form-grid">
          <div class="field"><label for="productCost">Preço de custo</label><input id="productCost" name="cost" type="number" min="0" step="0.01" value="<?= e($formData['cost']) ?>"></div>
          <div class="field"><label for="productPrice">Preço de venda</label><input id="productPrice" name="price" type="number" min="0" step="0.01" value="<?= e($formData['price']) ?>" required></div>
          <div class="field"><label for="productStock">Quantidade em estoque</label><input id="productStock" name="stock" type="number" min="0" step="0.001" value="<?= e($formData['stock']) ?>" required></div>
          <div class="field"><label for="productMinStock">Limite mínimo</label><input id="productMinStock" name="minStock" type="number" min="0" step="0.001" value="<?= e($formData['minStock']) ?>" required></div>
          <div class="field"><label for="productLot">Lote</label><input id="productLot" name="lot" maxlength="80" value="<?= e($formData['lot']) ?>"></div>
          <div class="field"><label for="productExpiry">Data de validade</label><input id="productExpiry" name="expiry" type="date" value="<?= e($formData['expiry']) ?>"></div>
        </div>
      </div>

      <button id="saveProductButton" class="primary-btn section-gap-small" type="submit">Salvar produto</button>
    </form>

    <div id="productScannerBackdrop" class="product-scanner-backdrop" aria-hidden="true">
      <section class="product-scanner-modal" role="dialog" aria-modal="true" aria-labelledby="productScannerTitle">
        <h2 id="productScannerTitle">Escanear código de barras</h2>
        <p>Aponte a câmera para o código do produto.</p>
        <div class="product-scanner-frame">
          <video id="productScannerVideo" class="product-scanner-video" autoplay muted playsinline></video>
        </div>
        <div id="productScannerStatus" class="product-scanner-status" aria-live="polite">Aguardando câmera.</div>
        <div class="product-scanner-actions">
          <button id="productScannerCancel" type="button" class="ghost-btn">Cancelar</button>
        </div>
      </section>
    </div>
  <?php endif; ?>
</section>

<script src="../assets/js/produto-codigo-barras.js?v=<?= e($barcodeScriptVersion) ?>"></script>
<?php require_once __DIR__ . '/layout/footer.php'; ?>
