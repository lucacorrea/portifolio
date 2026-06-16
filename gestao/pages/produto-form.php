<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Repositories\SettingsRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Services\ProductService;

Auth::requireLogin();

$user = Auth::user();
$empresaId = (int)($user['empresa_id'] ?? 0);
$currentNivel = (string)($user['nivel'] ?? '');
$productService = new ProductService();

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

function storeProductImage(int $empresaId, string $currentImage): array
{
    $file = $_FILES['imageFile'] ?? null;

    if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [$currentImage, null];
    }

    if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Não foi possível receber a imagem do produto.');
    }

    if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('A imagem deve ter no máximo 2MB.');
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new InvalidArgumentException('Arquivo de imagem inválido.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!is_string($mime) || !isset($extensions[$mime]) || @getimagesize($tmpName) === false) {
        throw new InvalidArgumentException('Formato de imagem inválido. Use JPG, PNG, WEBP ou GIF.');
    }

    $directory = BASE_PATH . '/uploads/produtos';
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível preparar o diretório de imagens.');
    }

    $filename = sprintf('empresa-%d-%s.%s', $empresaId, bin2hex(random_bytes(8)), $extensions[$mime]);
    $absolutePath = $directory . '/' . $filename;

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        throw new RuntimeException('Não foi possível salvar a imagem do produto.');
    }

    return ['uploads/produtos/' . $filename, $absolutePath];
}

function removeProductUpload(?string $absolutePath): void
{
    if ($absolutePath === null || !is_file($absolutePath)) {
        return;
    }

    $uploadDirectory = realpath(BASE_PATH . '/uploads/produtos');
    $fileDirectory = realpath(dirname($absolutePath));

    if ($uploadDirectory !== false && $fileDirectory === $uploadDirectory) {
        @unlink($absolutePath);
    }
}

function removePreviousProductImage(string $image, string $newImage): void
{
    if ($image === '' || $image === $newImage || !preg_match('#^uploads/produtos/[A-Za-z0-9._-]+$#', $image)) {
        return;
    }

    removeProductUpload(BASE_PATH . '/' . $image);
}

function productFormData(array $source, array $fallback = []): array
{
    $fields = ['id', 'name', 'sku', 'barcode', 'category', 'cost', 'price', 'stock', 'minStock', 'lot', 'expiry', 'image'];
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
        [$image, $uploadedAbsolutePath] = storeProductImage($empresaId, $currentImage);

        $payload = [
            'id' => $productId,
            'name' => (string)($_POST['name'] ?? ''),
            'sku' => (string)($_POST['sku'] ?? ''),
            'barcode' => (string)($_POST['barcode'] ?? ''),
            'category' => (string)($_POST['category'] ?? ''),
            'cost' => $_POST['cost'] ?? '',
            'price' => $_POST['price'] ?? '',
            'stock' => $_POST['stock'] ?? '',
            'minStock' => $_POST['minStock'] ?? '',
            'lot' => (string)($_POST['lot'] ?? ''),
            'expiry' => (string)($_POST['expiry'] ?? ''),
            'image' => $image,
        ];

        $productService->save($empresaId, $payload);

        if ($uploadedAbsolutePath !== null) {
            removePreviousProductImage($currentImage, $image);
        }

        redirectProductList('success', $productId > 0 ? 'Produto atualizado com sucesso.' : 'Produto cadastrado com sucesso.');
    } catch (InvalidArgumentException | RuntimeException $e) {
        removeProductUpload($uploadedAbsolutePath);
        $formError = $e->getMessage();
    } catch (Throwable $e) {
        removeProductUpload($uploadedAbsolutePath);
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
require_once __DIR__ . '/layout/header.php';
?>

<style>
  .product-alert { margin-bottom: 14px; padding: 13px 15px; color: var(--red); background: rgba(230,83,103,.1); border: 1px solid rgba(230,83,103,.25); border-radius: 16px; font-size: 13px; font-weight: 750; }
  .product-form-help { margin: 8px 0 0; color: var(--muted); font-size: 11px; font-weight: 650; }
  .product-form-preview > div { display: flex; flex-wrap: wrap; gap: 6px; }
  .product-form-preview .file-btn { flex: 1 1 135px; }
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
        <img id="productPreview" src="<?= e(productFormImageUrl($formData['image'] ?? '')) ?>" alt="Prévia do produto">
        <div>
          <label class="file-btn">
            Escolher imagem
            <input id="productImageInput" name="imageFile" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
          </label>
          <input id="productCameraInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif" capture="environment" hidden>
          <button type="button" class="file-btn" data-select-product-image>Tirar foto</button>
        </div>
      </div>
      <p class="product-form-help">JPG, PNG, WEBP ou GIF, com no máximo 2MB.</p>

      <div class="form-grid section-gap-small">
        <div class="field"><label for="productName">Nome do produto</label><input id="productName" name="name" maxlength="180" value="<?= e($formData['name']) ?>" required></div>
        <div class="field"><label for="productSku">SKU / Código interno</label><input id="productSku" name="sku" maxlength="80" value="<?= e($formData['sku']) ?>" required></div>
        <div class="field"><label for="productBarcode">Código de barras / QR</label><input id="productBarcode" name="barcode" maxlength="80" value="<?= e($formData['barcode']) ?>"></div>
        <div class="field"><label for="productCategory">Categoria</label><input id="productCategory" name="category" maxlength="120" value="<?= e($formData['category']) ?>" required></div>
        <div class="field"><label for="productCost">Preço de custo</label><input id="productCost" name="cost" type="number" min="0" step="0.01" value="<?= e($formData['cost']) ?>" required></div>
        <div class="field"><label for="productPrice">Preço de venda</label><input id="productPrice" name="price" type="number" min="0" step="0.01" value="<?= e($formData['price']) ?>" required></div>
        <div class="field"><label for="productStock">Quantidade em estoque</label><input id="productStock" name="stock" type="number" min="0" step="0.001" value="<?= e($formData['stock']) ?>" required></div>
        <div class="field"><label for="productMinStock">Limite mínimo</label><input id="productMinStock" name="minStock" type="number" min="0" step="0.001" value="<?= e($formData['minStock']) ?>" required></div>
        <div class="field"><label for="productLot">Lote</label><input id="productLot" name="lot" maxlength="80" value="<?= e($formData['lot']) ?>" required></div>
        <div class="field"><label for="productExpiry">Data de validade</label><input id="productExpiry" name="expiry" type="date" value="<?= e($formData['expiry']) ?>" required></div>
      </div>

      <button class="primary-btn section-gap-small" type="submit">Salvar produto</button>
    </form>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
