<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Repositories\ClientRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SettingsRepository;
use App\Security\Auth;
use App\Security\Csrf;
use App\Services\SaleService;

Auth::requireLogin();

$user = Auth::user();
$empresaId = (int)($user['empresa_id'] ?? 0);
$currentUserId = (int)($user['id'] ?? 0);
$currentNivel = (string)($user['nivel'] ?? '');

function canSaleAccess(string $action, string $nivel): bool
{
    $permissions = [
        'create' => ['admin', 'gerente', 'operador'],
        'discount' => ['admin', 'gerente', 'operador'],
        'account' => ['admin', 'gerente', 'operador'],
        'cancel' => ['admin', 'gerente'],
        'view' => ['admin', 'gerente', 'operador', 'leitor'],
    ];

    return in_array($nivel, $permissions[$action] ?? [], true);
}

function requireSaleAccess(string $action, string $nivel): void
{
    if (!canSaleAccess($action, $nivel)) {
        throw new RuntimeException('Você não tem permissão para executar esta ação.');
    }
}

function redirectSale(string $type, string $message, string $query = ''): void
{
    $_SESSION['sale_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    header('Location: nova-venda.php' . ($query !== '' ? '?' . http_build_query(['q' => $query]) : ''));
    exit;
}

function saleCart(): array
{
    return is_array($_SESSION['sale_cart'] ?? null) ? $_SESSION['sale_cart'] : [];
}

function saveSaleCart(array $cart): void
{
    $_SESSION['sale_cart'] = array_filter($cart, static fn ($qty): bool => (float)$qty > 0);
}

function saleMoney(mixed $value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function saleNumber(mixed $value, int $decimals = 3): string
{
    $formatted = number_format((float)$value, $decimals, ',', '.');

    return rtrim(rtrim($formatted, '0'), ',');
}

function saleEnabled(array $settings, string $key, bool $default = true): bool
{
    if (!array_key_exists($key, $settings)) {
        return $default;
    }

    return ((string)$settings[$key]) === '1';
}

function saleDefaultSettings(array $settings): array
{
    return array_merge([
        'block_expired_products' => 1,
        'block_negative_stock' => 1,
        'payment_pix' => 1,
        'payment_cash' => 1,
        'payment_credit' => 1,
        'payment_debit' => 1,
        'payment_account' => 1,
        'payment_mixed' => 1,
        'allow_discount' => 1,
        'discount_limit_percent' => 0,
        'require_customer_for_account' => 1,
        'debt_due_days' => 30,
    ], $settings);
}

try {
    requireSaleAccess('create', $currentNivel);
} catch (RuntimeException $e) {
    http_response_code(403);
    exit('Acesso negado.');
}

$productRepository = new ProductRepository();
$clientRepository = new ClientRepository();
$settingsRepository = new SettingsRepository();
$saleService = new SaleService();

$query = trim((string)($_GET['q'] ?? ''));
$formError = null;

try {
    $settings = saleDefaultSettings($settingsRepository->getConfiguracoes($empresaId));
} catch (Throwable $e) {
    log_app_exception($e);
    $settings = saleDefaultSettings([]);
    $formError = 'Não foi possível carregar as configurações da venda. Usando regras padrão.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    try {
        if (!Csrf::validate((string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Sessão expirada. Recarregue a página e tente novamente.');
        }

        requireSaleAccess('create', $currentNivel);

        $cart = saleCart();

        if ($action === 'add') {
            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = (float)str_replace(',', '.', (string)($_POST['quantity'] ?? '1'));

            if ($productId === false || $quantity <= 0) {
                throw new InvalidArgumentException('Produto ou quantidade inválida.');
            }

            if (!$productRepository->findById($empresaId, (int)$productId)) {
                throw new InvalidArgumentException('Produto não encontrado.');
            }

            $cart[(int)$productId] = round(($cart[(int)$productId] ?? 0) + $quantity, 3);
            saveSaleCart($cart);
            redirectSale('success', 'Produto adicionado ao carrinho.', $query);
        }

        if ($action === 'update_cart') {
            $quantities = is_array($_POST['quantities'] ?? null) ? $_POST['quantities'] : [];
            $cart = [];
            $removeId = filter_var($_POST['remove_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            foreach ($quantities as $productId => $quantity) {
                $productId = (int)$productId;
                if ($removeId !== false && $productId === (int)$removeId) {
                    continue;
                }

                $quantity = (float)str_replace(',', '.', (string)$quantity);

                if ($productId > 0 && $quantity > 0) {
                    $cart[$productId] = round($quantity, 3);
                }
            }

            saveSaleCart($cart);
            redirectSale('success', 'Carrinho atualizado.', $query);
        }

        if ($action === 'remove') {
            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($productId !== false) {
                unset($cart[(int)$productId]);
                saveSaleCart($cart);
            }

            redirectSale('success', 'Produto removido do carrinho.', $query);
        }

        if ($action === 'clear') {
            unset($_SESSION['sale_cart']);
            redirectSale('success', 'Carrinho limpo.', $query);
        }

        if ($action === 'finish') {
            $sale = $saleService->finalize($empresaId, $currentUserId, [
                'items' => $cart,
                'cliente_id' => $_POST['cliente_id'] ?? 0,
                'payment' => $_POST['payment'] ?? 'pix',
                'discount' => $_POST['discount'] ?? 0,
                'received' => $_POST['received'] ?? null,
                'dueDate' => $_POST['due_date'] ?? '',
                'mixed' => is_array($_POST['mixed'] ?? null) ? $_POST['mixed'] : [],
                'observacao' => $_POST['observacao'] ?? '',
            ]);

            unset($_SESSION['sale_cart']);
            header('Location: venda-detalhes.php?id=' . (int)($sale['id'] ?? 0));
            exit;
        }

        throw new InvalidArgumentException('Ação inválida.');
    } catch (InvalidArgumentException | RuntimeException $e) {
        $formError = $e->getMessage();
    } catch (Throwable $e) {
        log_app_exception($e);
        $formError = 'Não foi possível finalizar a venda. Verifique os dados e tente novamente.';
    }
}

$products = [];
$clients = [];
try {
    $products = $productRepository->findAll($empresaId, $query);
    $clients = $clientRepository->findAll($empresaId);
} catch (Throwable $e) {
    log_app_exception($e);
    $formError = $formError ?? 'Não foi possível carregar produtos e clientes agora.';
}

$cart = saleCart();
$cartProducts = [];
$cartSubtotal = 0.0;

foreach ($cart as $productId => $quantity) {
    try {
        $product = $productRepository->findById($empresaId, (int)$productId);
    } catch (Throwable $e) {
        log_app_exception($e);
        $product = null;
    }

    if (!$product) {
        unset($cart[$productId]);
        continue;
    }

    $lineTotal = round((float)$quantity * (float)$product['price'], 2);
    $cartSubtotal += $lineTotal;
    $cartProducts[] = [
        'product' => $product,
        'quantity' => (float)$quantity,
        'lineTotal' => $lineTotal,
    ];
}

saveSaleCart($cart);

$paymentLabels = [
    'pix' => 'PIX',
    'dinheiro' => 'Dinheiro',
    'credito' => 'Cartão de crédito',
    'debito' => 'Cartão de débito',
    'conta_cliente' => 'Conta do cliente',
    'misto' => 'Pagamento misto',
];
$paymentKeys = [
    'pix' => 'payment_pix',
    'dinheiro' => 'payment_cash',
    'credito' => 'payment_credit',
    'debito' => 'payment_debit',
    'conta_cliente' => 'payment_account',
    'misto' => 'payment_mixed',
];
$enabledPayments = array_filter(
    $paymentLabels,
    static fn (string $label, string $method): bool => saleEnabled($settings, $paymentKeys[$method], true),
    ARRAY_FILTER_USE_BOTH
);

$flash = $_SESSION['sale_flash'] ?? null;
unset($_SESSION['sale_flash']);

$pageId = 'nova-venda-server';
$pageTitle = 'Nova Venda';
$activeMenu = 'vendas';
require_once __DIR__ . '/layout/header.php';
?>

<style>
  .sale-alert { margin-bottom: 14px; padding: 13px 15px; border: 1px solid var(--line); border-radius: 16px; font-size: 13px; font-weight: 750; }
  .sale-alert.success { color: var(--green); background: rgba(37,196,132,.1); border-color: rgba(37,196,132,.25); }
  .sale-alert.danger { color: var(--red); background: rgba(230,83,103,.1); border-color: rgba(230,83,103,.25); }
  .sale-section { margin-bottom: 16px; }
  .sale-search-form { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 9px; }
  .sale-search-form .search-box { min-width: 0; }
  .sale-search-form .secondary-btn { width: auto; min-height: 54px; padding: 0 16px; }
  #saleProducts .product-card { grid-template-columns: 1fr; }
  .sale-product-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: end; }
  .sale-product-row .field input { min-height: 42px; }
  .sale-cart-row { display: grid; grid-template-columns: minmax(0, 1fr) 96px auto; gap: 8px; align-items: center; }
  .sale-cart-row + .sale-cart-row { margin-top: 10px; }
  .sale-cart-row .field input { min-height: 42px; }
  .sale-payments { display: grid; gap: 9px; }
  .sale-payments label { display: flex; gap: 9px; align-items: center; min-height: 42px; padding: 0 12px; border: 1px solid var(--line); border-radius: 14px; font-size: 13px; font-weight: 800; }
  .sale-summary { display: grid; gap: 8px; }
  .sale-summary div { display: flex; justify-content: space-between; gap: 12px; color: var(--muted); font-size: 13px; font-weight: 750; }
  .sale-summary strong { color: var(--ink); }
  .sale-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 9px; }
  .sale-actions form { margin: 0; }
  .sale-actions button { width: 100%; }
  @media (max-width: 520px) {
    .sale-search-form,
    .sale-product-row,
    .sale-cart-row,
    .sale-actions { grid-template-columns: 1fr; }
  }
  @media (min-width: 820px) {
    .sale-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(360px, .9fr); gap: 16px; align-items: start; }
    .form-grid.sale-two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .field.full { grid-column: 1 / -1; }
  }
</style>

<header class="plain-header">
  <div class="page-title-row">
    <div>
      <p class="micro-label dark-text">Frente de caixa</p>
      <h1>Nova venda</h1>
    </div>
    <form method="post" onsubmit="return confirm('Limpar o carrinho?');">
      <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="clear">
      <button class="round-btn" type="submit" aria-label="Limpar carrinho">+</button>
    </form>
  </div>
</header>

<section class="content-pad">
  <?php if (is_array($flash)): ?>
    <div class="sale-alert <?= e((string)($flash['type'] ?? 'danger')) ?>" role="status"><?= e((string)($flash['message'] ?? '')) ?></div>
  <?php endif; ?>

  <?php if ($formError !== null): ?>
    <div class="sale-alert danger" role="alert"><?= e($formError) ?></div>
  <?php endif; ?>

  <div class="sale-grid">
    <div>
      <section class="sale-section">
        <form class="sale-search-form" method="get" action="nova-venda.php">
          <label class="search-box">
            <span data-icon="search"></span>
            <input type="search" name="q" value="<?= e($query) ?>" placeholder="Buscar produto por nome, SKU, lote ou código">
          </label>
          <button class="secondary-btn" type="submit">Buscar</button>
        </form>
      </section>

      <section class="sale-section" id="saleProducts">
        <?php if (!$products): ?>
          <article class="summary-card">Nenhum produto encontrado.</article>
        <?php endif; ?>

        <?php foreach ($products as $product): ?>
          <article class="product-card">
            <div class="product-info">
              <h3><?= e((string)$product['name']) ?></h3>
              <p><?= e((string)$product['category']) ?> · <?= e((string)($product['sku'] ?: 'Sem SKU')) ?></p>
              <div class="product-meta">
                <span>Qtd. <?= e(saleNumber($product['stock'])) ?></span>
                <span>Val. <?= e($product['expiry'] ? date('d/m/Y', strtotime((string)$product['expiry'])) : 'Sem validade') ?></span>
                <span><?= e(saleMoney($product['price'])) ?></span>
              </div>
              <form class="sale-product-row section-gap-small" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <div class="field">
                  <label for="qty<?= (int)$product['id'] ?>">Quantidade</label>
                  <input id="qty<?= (int)$product['id'] ?>" name="quantity" type="number" min="0.001" step="0.001" value="1">
                </div>
                <button class="secondary-btn" type="submit">Adicionar</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
    </div>

    <div>
      <section class="summary-card sale-section">
        <h3>Carrinho</h3>
        <?php if (!$cartProducts): ?>
          <p>Nenhum produto no carrinho.</p>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="update_cart">
            <?php foreach ($cartProducts as $item): ?>
              <?php $product = $item['product']; ?>
              <div class="sale-cart-row">
                <div>
                  <strong><?= e((string)$product['name']) ?></strong>
                  <p><?= e(saleMoney($product['price'])) ?> cada · <?= e(saleMoney($item['lineTotal'])) ?></p>
                </div>
                <div class="field">
                  <label for="cartQty<?= (int)$product['id'] ?>">Qtd.</label>
                  <input id="cartQty<?= (int)$product['id'] ?>" name="quantities[<?= (int)$product['id'] ?>]" type="number" min="0" step="0.001" value="<?= e((string)$item['quantity']) ?>">
                </div>
                <button class="secondary-btn" name="remove_id" value="<?= (int)$product['id'] ?>" type="submit">Remover</button>
              </div>
            <?php endforeach; ?>
            <button class="secondary-btn section-gap-small" type="submit">Atualizar carrinho</button>
          </form>
        <?php endif; ?>
      </section>

      <form method="post" class="form-card">
        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="finish">

        <div class="form-grid sale-two">
          <div class="field">
            <label for="saleClient">Cliente</label>
            <select id="saleClient" name="cliente_id">
              <option value="0">Venda balcão</option>
              <?php foreach ($clients as $client): ?>
                <option value="<?= (int)$client['id'] ?>"><?= e((string)$client['name']) ?><?= $client['phone'] ? ' - ' . e((string)$client['phone']) : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label for="saleDueDate">Vencimento da conta</label>
            <input id="saleDueDate" name="due_date" type="date" value="<?= e((new DateTimeImmutable('today'))->modify('+' . max(0, (int)($settings['debt_due_days'] ?? 30)) . ' days')->format('Y-m-d')) ?>">
          </div>

          <div class="field">
            <label for="saleDiscount">Desconto</label>
            <input id="saleDiscount" name="discount" type="number" min="0" step="0.01" value="0" <?= saleEnabled($settings, 'allow_discount', true) ? '' : 'readonly' ?>>
          </div>

          <div class="field">
            <label for="saleReceived">Valor recebido em dinheiro</label>
            <input id="saleReceived" name="received" type="number" min="0" step="0.01" placeholder="<?= e(saleMoney($cartSubtotal)) ?>">
          </div>

          <div class="field full">
            <label>Forma de pagamento</label>
            <div class="sale-payments">
              <?php foreach ($enabledPayments as $method => $label): ?>
                <label>
                  <input type="radio" name="payment" value="<?= e($method) ?>" <?= $method === array_key_first($enabledPayments) ? 'checked' : '' ?>>
                  <span><?= e($label) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <?php if (isset($enabledPayments['misto'])): ?>
            <div class="field full">
              <label>Composição do pagamento misto</label>
              <div class="form-grid sale-two">
                <?php foreach (['pix' => 'PIX', 'dinheiro' => 'Dinheiro', 'credito' => 'Crédito', 'debito' => 'Débito'] as $method => $label): ?>
                  <?php if (saleEnabled($settings, $paymentKeys[$method], true)): ?>
                    <div class="field">
                      <label for="mixed<?= e($method) ?>"><?= e($label) ?></label>
                      <input id="mixed<?= e($method) ?>" name="mixed[<?= e($method) ?>]" type="number" min="0" step="0.01" value="0">
                    </div>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="field full">
            <label for="saleObservation">Observação</label>
            <textarea id="saleObservation" name="observacao"></textarea>
          </div>
        </div>

        <div class="summary-card section-gap-small">
          <div class="sale-summary">
            <div><span>Subtotal</span><strong><?= e(saleMoney($cartSubtotal)) ?></strong></div>
            <div><span>Itens</span><strong><?= count($cartProducts) ?></strong></div>
            <div><span>Limite de desconto</span><strong><?= e(number_format((float)($settings['discount_limit_percent'] ?? 0), 2, ',', '.')) ?>%</strong></div>
          </div>
        </div>

        <button class="primary-btn section-gap-small" type="submit" <?= !$cartProducts || !$enabledPayments ? 'disabled' : '' ?>>Finalizar venda</button>
      </form>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
