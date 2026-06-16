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

function canProductAccess(string $action, string $nivel): bool
{
    $permissions = [
        'view' => ['admin', 'gerente', 'operador', 'estoquista', 'leitor'],
        'create' => ['admin', 'gerente', 'estoquista'],
        'edit' => ['admin', 'gerente', 'estoquista'],
        'delete' => ['admin', 'gerente'],
        'stock' => ['admin', 'gerente', 'estoquista'],
    ];

    return in_array($nivel, $permissions[$action] ?? [], true);
}

function requireProductAccess(string $action, string $nivel): void
{
    if (!canProductAccess($action, $nivel)) {
        throw new RuntimeException('Você não tem permissão para executar esta ação.');
    }
}

function redirectProducts(string $type, string $message, string $query = '', string $filter = 'todos'): void
{
    $_SESSION['product_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    $params = [];
    if ($query !== '') {
        $params['q'] = $query;
    }
    if ($filter !== 'todos') {
        $params['filtro'] = $filter;
    }

    header('Location: produtos.php' . ($params ? '?' . http_build_query($params) : ''));
    exit;
}

function productImageUrl(mixed $image): string
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

function productStatus(array $product, DateTimeImmutable $today, int $alertDays): array
{
    $expiry = trim((string)($product['expiry'] ?? ''));
    $stock = (float)($product['stock'] ?? 0);
    $minStock = (float)($product['minStock'] ?? 0);

    if ($expiry !== '') {
        $expiryDate = DateTimeImmutable::createFromFormat('!Y-m-d', $expiry);

        if ($expiryDate instanceof DateTimeImmutable) {
            if ($expiryDate < $today) {
                return ['key' => 'vencidos', 'label' => 'Vencido', 'class' => 'red'];
            }

            if ($expiryDate <= $today->modify('+' . $alertDays . ' days')) {
                return ['key' => 'perto_validade', 'label' => 'Perto da validade', 'class' => 'orange'];
            }
        }
    }

    if ($stock <= $minStock) {
        return ['key' => 'estoque_baixo', 'label' => 'Estoque baixo', 'class' => 'orange'];
    }

    return ['key' => 'normal', 'label' => 'Normal', 'class' => 'green'];
}

function productMatchesFilter(array $product, string $filter, DateTimeImmutable $today, int $alertDays): bool
{
    if ($filter === 'todos') {
        return true;
    }

    if ($filter === 'estoque_baixo') {
        return (float)($product['stock'] ?? 0) <= (float)($product['minStock'] ?? 0);
    }

    $expiry = trim((string)($product['expiry'] ?? ''));
    $expiryDate = $expiry !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $expiry) : false;
    if (!$expiryDate instanceof DateTimeImmutable) {
        return false;
    }

    if ($filter === 'vencidos') {
        return $expiryDate < $today;
    }

    return $filter === 'perto_validade'
        && $expiryDate >= $today
        && $expiryDate <= $today->modify('+' . $alertDays . ' days');
}

function formatProductNumber(mixed $value, int $decimals = 3): string
{
    $formatted = number_format((float)$value, $decimals, ',', '.');

    return rtrim(rtrim($formatted, '0'), ',');
}

try {
    requireProductAccess('view', $currentNivel);
} catch (RuntimeException $e) {
    http_response_code(403);
    exit('Acesso negado.');
}

$allowedFilters = ['todos', 'estoque_baixo', 'perto_validade', 'vencidos'];
$query = trim((string)($_GET['q'] ?? ''));
$filter = (string)($_GET['filtro'] ?? 'todos');
$filter = in_array($filter, $allowedFilters, true) ? $filter : 'todos';
$productService = new ProductService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::validate((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Sessão expirada. Recarregue a página e tente novamente.');
        }

        requireProductAccess('delete', $currentNivel);

        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($id === false) {
            throw new InvalidArgumentException('Produto inválido.');
        }

        if (!$productService->find($empresaId, (int)$id)) {
            throw new InvalidArgumentException('Produto não encontrado.');
        }

        $productService->inactivate($empresaId, (int)$id);
        redirectProducts('success', 'Produto inativado com sucesso.', $query, $filter);
    } catch (InvalidArgumentException | RuntimeException $e) {
        redirectProducts('danger', $e->getMessage(), $query, $filter);
    } catch (Throwable $e) {
        log_app_exception($e);
        redirectProducts('danger', 'Não foi possível inativar o produto.', $query, $filter);
    }
}

$expirationAlertDays = 7;
try {
    $settings = (new SettingsRepository())->getConfiguracoes($empresaId);
    $expirationAlertDays = max(0, min(365, (int)($settings['expiration_alert_days'] ?? 7)));
} catch (Throwable $e) {
    log_app_exception($e);
}

$products = [];
$loadError = null;
try {
    $products = $productService->list($empresaId, $query);
} catch (Throwable $e) {
    log_app_exception($e);

    if ($currentNivel === 'admin') {
        $loadError = 'Erro técnico ao carregar produtos: ' . $e->getMessage();
    } else {
        $loadError = 'Não foi possível carregar os produtos agora.';
    }
}

$today = new DateTimeImmutable('today');
$products = array_values(array_filter(
    $products,
    static function (array $product) use ($filter, $today, $expirationAlertDays): bool {
        return productMatchesFilter($product, $filter, $today, $expirationAlertDays);
    }
));

$flash = $_SESSION['product_flash'] ?? null;
unset($_SESSION['product_flash']);

$pageId = 'produtos-server';
$pageTitle = 'Produtos';
$activeMenu = 'produtos';
require_once __DIR__ . '/layout/header.php';
?>

<style>
  .product-alert { margin-bottom: 14px; padding: 13px 15px; border: 1px solid var(--line); border-radius: 16px; font-size: 13px; font-weight: 750; }
  .product-alert.success { color: var(--green); background: rgba(37,196,132,.1); border-color: rgba(37,196,132,.25); }
  .product-alert.danger { color: var(--red); background: rgba(230,83,103,.1); border-color: rgba(230,83,103,.25); }
  .product-search-form { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 9px; }
  .product-search-form .search-box { min-width: 0; }
  .product-search-form .secondary-btn { width: auto; min-height: 54px; padding: 0 16px; }
  .filter-pills a { height: 31px; flex: 0 0 auto; display: inline-flex; align-items: center; padding: 0 14px; border-radius: 999px; color: var(--muted); background: #fff; border: 1px solid var(--line); font-size: 12px; font-weight: 800; }
  .filter-pills a.active { color: var(--blue); background: var(--blue-soft); border-color: var(--blue-line); }
  .product-card .card-actions form { margin: 0; }
  .product-card .card-actions button { min-height: 34px; }
  .product-card .card-actions { align-items: stretch; }
  .product-card .card-actions > * { flex: 1; }
  .product-card .card-actions a { display: grid; place-items: center; }
  @media (max-width: 430px) { .product-search-form { grid-template-columns: 1fr; } .product-search-form .secondary-btn { width: 100%; } }
</style>

<header class="plain-header">
  <div class="page-title-row">
    <div>
      <p class="micro-label dark-text">Produtos e estoque</p>
      <h1>Produtos</h1>
    </div>
    <?php if (canProductAccess('create', $currentNivel)): ?>
      <a class="round-btn" href="produto-form.php" aria-label="Cadastrar produto">+</a>
    <?php endif; ?>
  </div>
</header>

<section class="content-pad">
  <?php if (is_array($flash)): ?>
    <div class="product-alert <?= e((string)($flash['type'] ?? 'danger')) ?>" role="status">
      <?= e((string)($flash['message'] ?? '')) ?>
    </div>
  <?php endif; ?>

  <?php if ($loadError !== null): ?>
    <div class="product-alert danger" role="alert"><?= e($loadError) ?></div>
  <?php endif; ?>

  <form class="product-search-form" method="get" action="produtos.php">
    <label class="search-box">
      <span data-icon="search"></span>
      <input type="search" name="q" value="<?= e($query) ?>" placeholder="Buscar por nome, SKU, lote ou categoria">
    </label>
    <input type="hidden" name="filtro" value="<?= e($filter) ?>">
    <button class="secondary-btn" type="submit">Buscar</button>
  </form>

  <?php
  $filterLabels = [
      'todos' => 'Todos',
      'estoque_baixo' => 'Estoque baixo',
      'perto_validade' => 'Perto da validade',
      'vencidos' => 'Vencidos',
  ];
  ?>
  <nav class="filter-pills" aria-label="Filtros de produtos">
    <?php foreach ($filterLabels as $filterKey => $filterLabel): ?>
      <?php $filterQuery = array_filter(['q' => $query, 'filtro' => $filterKey], static fn ($value): bool => $value !== ''); ?>
      <a class="<?= $filter === $filterKey ? 'active' : '' ?>" href="produtos.php?<?= e(http_build_query($filterQuery)) ?>">
        <?= e($filterLabel) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div id="productsList">
    <?php if (!$products && $loadError === null): ?>
      <article class="summary-card">Nenhum produto encontrado.</article>
    <?php endif; ?>

    <?php foreach ($products as $product): ?>
      <?php
      $status = productStatus($product, $today, $expirationAlertDays);
      $expiry = trim((string)($product['expiry'] ?? ''));
      $expiryLabel = $expiry !== '' ? date('d/m/Y', strtotime($expiry)) : 'Sem validade';
      ?>
      <article class="product-card">
        <img src="<?= e(productImageUrl($product['image'] ?? '')) ?>" alt="Imagem de <?= e((string)$product['name']) ?>">
        <div class="product-info">
          <h3><?= e((string)$product['name']) ?></h3>
          <p><?= e((string)$product['category']) ?> · <?= e((string)($product['sku'] ?: 'Sem SKU')) ?></p>
          <div class="product-meta">
            <span>Lote <?= e((string)($product['lot'] ?: 'não informado')) ?></span>
            <span>Val. <?= e($expiryLabel) ?></span>
            <span>Qtd. <?= e(formatProductNumber($product['stock'])) ?></span>
            <span>Mín. <?= e(formatProductNumber($product['minStock'])) ?></span>
            <span>R$ <?= e(number_format((float)$product['price'], 2, ',', '.')) ?></span>
          </div>
          <div class="badge-row">
            <span class="badge <?= e($status['class']) ?>"><?= e($status['label']) ?></span>
          </div>
          <?php if (canProductAccess('edit', $currentNivel) || canProductAccess('delete', $currentNivel)): ?>
            <div class="card-actions">
              <?php if (canProductAccess('edit', $currentNivel)): ?>
                <a href="produto-form.php?id=<?= (int)$product['id'] ?>">Editar</a>
              <?php endif; ?>
              <?php if (canProductAccess('delete', $currentNivel)): ?>
                <form method="post" action="produtos.php?<?= e(http_build_query(array_filter(['q' => $query, 'filtro' => $filter]))) ?>" onsubmit="return confirm('Inativar este produto?');">
                  <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                  <button class="danger-mini" type="submit">Inativar</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
