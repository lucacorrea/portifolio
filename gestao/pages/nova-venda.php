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

    header('Location: nova-venda.php' . ($query !== '' ? '?' . http_build_query(['product_search' => $query]) : ''));
    exit;
}

function redirectSaleSearch(string $query): void
{
    $query = trim($query);

    header('Location: nova-venda.php' . ($query !== '' ? '?' . http_build_query(['product_search' => $query]) : ''));
    exit;
}

function saleSearchLength(string $term): int
{
    return function_exists('mb_strlen') ? mb_strlen($term) : strlen($term);
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

function salePaymentIcon(string $method): string
{
    return [
        'pix' => 'PIX',
        'dinheiro' => 'R$',
        'credito' => 'CR',
        'debito' => 'DB',
        'conta_cliente' => 'CC',
        'misto' => 'MIX',
    ][$method] ?? 'OK';
}

function saleProductExpired(array $product): bool
{
    $expiry = trim((string)($product['expiry'] ?? ''));

    return $expiry !== '' && $expiry < date('Y-m-d');
}

function assertSaleProductCanEnterCart(array $product, float $quantity, array $cart, array $settings): void
{
    if ($quantity <= 0) {
        throw new InvalidArgumentException('Produto ou quantidade inválida.');
    }

    if (saleEnabled($settings, 'block_expired_products', true) && saleProductExpired($product)) {
        throw new InvalidArgumentException('Produto vencido não pode ser vendido.');
    }

    $productId = (int)($product['id'] ?? 0);
    $currentQuantity = (float)($cart[$productId] ?? 0);
    $requestedQuantity = round($currentQuantity + $quantity, 3);

    if (saleEnabled($settings, 'block_negative_stock', true) && $requestedQuantity > (float)($product['stock'] ?? 0)) {
        throw new InvalidArgumentException('Produto sem estoque.');
    }
}

function addProductToSaleCart(array $product, array $cart, array $settings, float $quantity = 1.0): array
{
    assertSaleProductCanEnterCart($product, $quantity, $cart, $settings);

    $productId = (int)$product['id'];
    $cart[$productId] = round(((float)($cart[$productId] ?? 0)) + $quantity, 3);

    return $cart;
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

$query = trim((string)($_GET['product_search'] ?? $_GET['q'] ?? ''));
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

        if ($action === 'search_product') {
            redirectSaleSearch((string)($_POST['product_search'] ?? ''));
        }

        if ($action === 'add_by_code' || $action === 'add_product_by_code') {
            $code = trim((string)($_POST['barcode'] ?? $_POST['product_search'] ?? ''));

            if ($code === '') {
                throw new InvalidArgumentException('Informe um código, SKU ou nome de produto.');
            }

            $product = $productRepository->findByCode($empresaId, $code);

            if (!$product) {
                $matches = saleSearchLength($code) >= 2 ? $productRepository->findAll($empresaId, $code) : [];
                if (!$matches) {
                    redirectSale('danger', 'Produto não encontrado.', $code);
                }

                redirectSale('warning', 'Nenhum código exato encontrado. Exibindo produtos encontrados.', $code);
            }

            $cart = addProductToSaleCart($product, $cart, $settings, 1.0);
            saveSaleCart($cart);
            redirectSale('success', 'Produto adicionado. Continue adicionando itens ou avance para Cliente.');
        }

        if ($action === 'add' || $action === 'add_product') {
            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = (float)str_replace(',', '.', (string)($_POST['quantity'] ?? '1'));

            if ($productId === false || $quantity <= 0) {
                throw new InvalidArgumentException('Produto ou quantidade inválida.');
            }

            $product = $productRepository->findById($empresaId, (int)$productId);
            if (!$product) {
                throw new InvalidArgumentException('Produto não encontrado.');
            }

            $cart = addProductToSaleCart($product, $cart, $settings, $quantity);
            saveSaleCart($cart);
            redirectSale('success', 'Produto adicionado. Continue adicionando itens ou avance para Cliente.', $query);
        }

        if ($action === 'update_cart' || $action === 'update_quantity') {
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
                    $product = $productRepository->findById($empresaId, $productId);
                    if (!$product) {
                        throw new InvalidArgumentException('Produto não encontrado.');
                    }

                    assertSaleProductCanEnterCart($product, $quantity, [], $settings);
                    $cart[$productId] = round($quantity, 3);
                }
            }

            saveSaleCart($cart);
            redirectSale('success', 'Carrinho atualizado.', $query);
        }

        if ($action === 'remove' || $action === 'remove_item') {
            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($productId !== false) {
                unset($cart[(int)$productId]);
                saveSaleCart($cart);
            }

            redirectSale('success', 'Produto removido do carrinho.', $query);
        }

        if ($action === 'clear' || $action === 'clear_cart') {
            unset($_SESSION['sale_cart']);
            redirectSale('success', 'Carrinho limpo.', $query);
        }

        if ($action === 'finish' || $action === 'finish_sale') {
            $sale = $saleService->finalize($empresaId, $currentUserId, [
                'items' => $cart,
                'cliente_id' => $_POST['cliente_id'] ?? 0,
                'payment' => $_POST['payment'] ?? 'pix',
                'creditInstallments' => $_POST['credit_installments'] ?? null,
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
    if (saleSearchLength($query) >= 2) {
        $products = $productRepository->findAll($empresaId, $query);
    }
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
$firstEnabledPayment = (string)(array_key_first($enabledPayments) ?? '');
$postedPayment = (string)($_POST['payment'] ?? '');
$selectedPaymentMethod = isset($enabledPayments[$postedPayment]) ? $postedPayment : $firstEnabledPayment;
$selectedPaymentLabel = (string)($enabledPayments[$selectedPaymentMethod] ?? 'Não habilitado');
$postedCreditInstallments = '';
$postedCreditInstallmentsRaw = trim((string)($_POST['credit_installments'] ?? ''));
if ($selectedPaymentMethod === 'credito' && preg_match('/^\d+$/', $postedCreditInstallmentsRaw)) {
    $installments = filter_var($postedCreditInstallmentsRaw, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
            'max_range' => 12,
        ],
    ]);
    $postedCreditInstallments = $installments === false ? '' : (string)$installments;
}

$flash = $_SESSION['sale_flash'] ?? null;
unset($_SESSION['sale_flash']);

$pageId = 'nova-venda-server';
$pageTitle = 'Nova Venda';
$activeMenu = 'nova-venda';
require_once __DIR__ . '/layout/header.php';
?>

<style>
  .sale-alert { margin-bottom: 14px; padding: 13px 15px; border: 1px solid var(--line); border-radius: 14px; font-size: 13px; font-weight: 750; }
  .sale-alert.success { color: var(--green); background: rgba(37,196,132,.1); border-color: rgba(37,196,132,.25); }
  .sale-alert.danger { color: var(--red); background: rgba(230,83,103,.1); border-color: rgba(230,83,103,.25); }
  .sale-alert.warning { color: #8A4A00; background: rgba(255,181,71,.14); border-color: rgba(255,181,71,.3); }
  .sale-layout { display: grid; gap: 14px; padding-bottom: 88px; }
  .sale-workspace { display: grid; gap: 14px; min-width: 0; }
  .sale-steps { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(132px, 1fr); gap: 8px; overflow-x: auto; padding: 2px 0 10px; scrollbar-width: none; }
  .sale-steps::-webkit-scrollbar { display: none; }
  .sale-step { min-height: 58px; display: flex; align-items: center; gap: 9px; padding: 9px 11px; color: var(--muted); background: #fff; border: 1px solid var(--line); border-radius: 14px; text-align: left; font-size: 11px; font-weight: 850; }
  .sale-step span { width: 28px; height: 28px; flex: 0 0 28px; display: grid; place-items: center; color: var(--blue); background: var(--blue-soft); border-radius: 10px; }
  .sale-step strong { display: block; color: var(--ink); font-size: 13px; }
  .sale-step small { display: block; margin-top: 2px; color: inherit; font-size: 10px; line-height: 1.2; }
  .sale-step.active { color: var(--blue); border-color: var(--blue-line); background: var(--blue-soft); }
  .sale-panel { padding: 15px; background: #fff; border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 8px 22px rgba(29,55,95,.055); }
  .sale-layout.sale-js .sale-panel { display: none; }
  .sale-layout.sale-js .sale-panel.active { display: block; }
  .sale-panel-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
  .sale-panel-head h2 { margin: 0; font-size: 18px; letter-spacing: 0; }
  .sale-panel-head p { margin: 4px 0 0; color: var(--muted); font-size: 12px; font-weight: 700; }
  .sale-search-form { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 9px; }
  .sale-search-form .search-box { min-width: 0; }
  .sale-search-form input { min-height: 56px; font-size: 15px; }
  .sale-search-form .secondary-btn { width: auto; min-height: 54px; padding: 0 18px; }
  .sale-camera-btn { min-height: 54px; padding: 0 18px; border: 1px solid var(--blue-line); border-radius: 14px; color: var(--blue); background: var(--blue-soft); font-weight: 900; }
  .sale-scan-submit { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
  .sale-search-help { margin: 8px 0 0; color: var(--muted); font-size: 12px; font-weight: 750; }
  .sale-camera-panel { position: fixed; inset: 0; z-index: 80; display: none; place-items: center; padding: 16px; background: rgba(18,32,54,.62); }
  .sale-camera-panel.open { display: grid; }
  .sale-camera-box { width: min(520px, 100%); padding: 14px; background: #fff; border-radius: 18px; box-shadow: 0 24px 60px rgba(18,32,54,.28); }
  .sale-camera-box h3 { margin: 0; font-size: 17px; letter-spacing: 0; }
  .sale-camera-box p { margin: 5px 0 12px; color: var(--muted); font-size: 12px; font-weight: 750; }
  .sale-camera-video { width: 100%; max-height: 58vh; object-fit: cover; background: #111827; border-radius: 14px; }
  .sale-camera-feedback { min-height: 18px; margin: 10px 0 0; color: var(--muted); font-size: 12px; font-weight: 800; }
  .sale-camera-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 12px; }
  .sale-clear-header-btn { min-height: 40px; width: auto; padding: 0 14px; }
  .sale-product-list { display: grid; gap: 10px; margin-top: 12px; }
  .sale-product-card { display: grid; gap: 11px; padding: 13px; background: #fff; border: 1px solid var(--line); border-radius: 14px; }
  .sale-product-card.warning { border-color: rgba(230,83,103,.25); background: rgba(230,83,103,.04); }
  .sale-product-top { display: flex; justify-content: space-between; gap: 12px; }
  .sale-product-top h3 { margin: 0; font-size: 15px; letter-spacing: 0; }
  .sale-product-top p { margin: 4px 0 0; color: var(--muted); font-size: 12px; font-weight: 700; }
  .sale-product-price { color: var(--blue); font-size: 16px; font-weight: 950; white-space: nowrap; }
  .sale-product-meta { display: flex; flex-wrap: wrap; gap: 6px; }
  .sale-product-meta span, .sale-mini-badge { padding: 5px 8px; color: var(--muted); background: #F6F9FD; border: 1px solid var(--line); border-radius: 999px; font-size: 10px; font-style: normal; font-weight: 800; }
  .sale-mini-badge.red { color: var(--red); background: rgba(230,83,103,.1); border-color: rgba(230,83,103,.2); }
  .sale-mini-badge.orange { color: #A45B00; background: rgba(255,181,71,.14); border-color: rgba(255,181,71,.28); }
  .sale-product-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: end; }
  .sale-product-row .field input { min-height: 44px; }
  .sale-cart-card { padding: 15px; background: #fff; border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 8px 22px rgba(29,55,95,.065); }
  .sale-cart-card h2 { margin: 0; font-size: 18px; letter-spacing: 0; }
  .sale-cart-compact { display: grid; gap: 12px; margin-top: 14px; }
  .sale-cart-summary-card { padding: 14px; background: #F8FBFF; border: 1px solid var(--blue-line); border-radius: 16px; }
  .sale-cart-summary-card h3 { margin: 0; color: var(--ink); font-size: 16px; letter-spacing: 0; }
  .sale-cart-summary-card p { margin: 6px 0 0; color: var(--muted); font-size: 12px; font-weight: 800; }
  .sale-cart-summary-card strong { color: var(--ink); }
  .sale-cart-summary-actions { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 9px; margin-top: 12px; align-items: center; }
  .sale-cart-summary-actions form { margin: 0; }
  .sale-cart-summary-actions .secondary-btn, .sale-cart-summary-actions .primary-btn { width: 100%; min-height: 42px; }
  .sale-cart-compact h3 { margin: 0; font-size: 16px; letter-spacing: 0; }
  .sale-cart-item { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 10px; padding: 12px 0; border-bottom: 1px solid var(--line); }
  .sale-cart-item:last-of-type { border-bottom: 0; }
  .sale-cart-item strong { display: block; font-size: 13px; }
  .sale-cart-item p { margin: 4px 0 0; color: var(--muted); font-size: 11px; font-weight: 700; }
  .sale-qty-tools { display: grid; grid-template-columns: 34px 78px 34px; gap: 5px; align-items: center; }
  .sale-qty-tools button { min-height: 36px; border: 1px solid var(--line); border-radius: 11px; color: var(--blue); background: var(--blue-soft); font-weight: 950; }
  .sale-qty-tools input { min-height: 36px; padding: 0 8px; text-align: center; border-radius: 11px; }
  .sale-cart-remove { min-height: 36px; margin-top: 7px; color: var(--red); background: rgba(230,83,103,.1); border: 0; border-radius: 11px; font-size: 12px; font-weight: 850; }
  .sale-payment-grid { display: grid; gap: 9px; }
  .sale-payment-card { min-height: 58px; display: flex; align-items: center; gap: 11px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 14px; background: #fff; font-size: 13px; font-weight: 850; cursor: pointer; }
  .sale-payment-card input { width: 18px; height: 18px; }
  .sale-payment-icon { width: 40px; height: 34px; display: grid; place-items: center; border-radius: 12px; color: var(--blue); background: var(--blue-soft); font-size: 11px; font-weight: 950; }
  .sale-payment-card:has(input:checked) { border-color: var(--blue-line); background: var(--blue-soft); }
  .sale-credit-installments-label { display: block; margin-top: 2px; color: var(--blue); font-size: 11px; font-weight: 900; }
  .sale-credit-installments-label[hidden] { display: none !important; }
  .sale-selected-client { min-height: 88px; padding: 12px; border: 1px dashed var(--blue-line); border-radius: 14px; background: #F8FBFF; }
  .sale-selected-client strong { display: block; font-size: 14px; }
  .sale-selected-client p { margin: 5px 0 0; color: var(--muted); font-size: 12px; font-weight: 700; }
  .sale-fiado-alert { margin-top: 10px; padding: 10px 12px; color: #8A4A00; background: rgba(255,181,71,.14); border: 1px solid rgba(255,181,71,.3); border-radius: 12px; font-size: 12px; font-weight: 800; }
  .sale-summary-lines { display: grid; gap: 8px; margin-top: 12px; }
  .sale-summary-lines div { display: flex; justify-content: space-between; gap: 12px; color: var(--muted); font-size: 13px; font-weight: 750; }
  .sale-summary-lines div[hidden], [data-payment-field][hidden] { display: none !important; }
  .sale-summary-lines strong { color: var(--ink); }
  .sale-summary-total { margin-top: 14px; padding: 13px; color: #fff; background: var(--blue); border-radius: 14px; }
  .sale-summary-total span { display: block; font-size: 11px; font-weight: 800; opacity: .86; }
  .sale-summary-total strong { display: block; margin-top: 3px; font-size: 24px; letter-spacing: 0; }
  .sale-review-list { display: grid; gap: 8px; }
  .sale-review-row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--line); font-size: 13px; }
  .sale-review-row[hidden] { display: none !important; }
  .sale-review-row:last-child { border-bottom: 0; }
  .sale-panel-actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 14px; }
  .sale-panel-actions.single { justify-content: stretch; }
  .sale-panel-actions button { min-height: 42px; padding: 0 14px; }
  .sale-payment-validation { margin: 10px 0 0; }
  .sale-step-warning { display: none; margin-top: 10px; }
  .sale-step-warning.visible { display: block; }
  .sale-submit-btn[disabled], .secondary-btn[disabled] { opacity: .55; cursor: not-allowed; }
  body.modal-open { overflow: hidden; }
  .sale-installments-backdrop { position: fixed; inset: 0; z-index: 120; display: none; place-items: center; padding: 16px; background: rgba(18,32,54,.64); backdrop-filter: blur(5px); }
  .sale-installments-backdrop.open { display: grid; }
  .sale-installments-modal { width: min(100%, 430px); padding: 18px; background: #fff; border-radius: 20px; box-shadow: 0 24px 64px rgba(18,32,54,.28); }
  .sale-installments-header { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
  .sale-installments-modal h2 { margin: 0; font-size: 20px; letter-spacing: 0; }
  .sale-installments-description { margin: 10px 0 14px; color: var(--muted); font-size: 12px; font-weight: 750; line-height: 1.4; }
  .sale-installments-modal input { min-height: 54px; font-size: 18px; text-align: center; }
  .sale-installments-preview { margin-top: 12px; padding: 12px; border: 1px solid var(--blue-line); border-radius: 14px; background: var(--blue-soft); }
  .sale-installments-preview span { display: block; color: var(--muted); font-size: 11px; font-weight: 800; }
  .sale-installments-preview strong { display: block; margin-top: 4px; color: var(--ink); font-size: 16px; }
  .sale-installments-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 16px; }
  .sale-installments-actions button { width: 100%; min-height: 46px; }
  @media (max-width: 819px) {
    .sale-layout { display: block; padding-bottom: 118px; }
    .sale-summary-sticky { display: none !important; }
    .sale-panel { display: none; }
    .sale-panel.active { display: block; }
    .sale-steps { position: sticky; top: 86px; z-index: 5; background: var(--bg); padding: 8px 0 10px; margin: 0 -2px; }
    .sale-panel-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .sale-panel-actions.single { grid-template-columns: 1fr; }
    .sale-panel-actions .primary-btn, .sale-panel-actions .secondary-btn { width: 100%; }
  }
  @media (max-width: 560px) {
    .sale-search-form, .sale-product-row { grid-template-columns: 1fr; }
    .sale-search-form .secondary-btn, .sale-search-form .sale-camera-btn, .sale-product-row .secondary-btn { width: 100%; }
    .sale-camera-panel { padding: 10px; align-items: start; }
    .sale-camera-box { margin-top: 18px; border-radius: 16px; }
    .sale-camera-video { max-height: 56vh; }
    .sale-panel { padding: 13px; border-radius: 14px; }
    .sale-product-top, .sale-cart-item { grid-template-columns: 1fr; }
    .sale-product-price { white-space: normal; }
    .sale-qty-tools { grid-template-columns: 42px minmax(82px,1fr) 42px; }
    .sale-payment-grid { grid-template-columns: 1fr; }
    .sale-panel-actions { justify-content: stretch; }
    .sale-panel-actions button { flex: 1; }
    .sale-cart-summary-actions { grid-template-columns: 1fr; }
  }
  @media (max-width: 420px) {
    .sale-installments-backdrop { align-items: end; padding: 10px; }
    .sale-installments-modal { border-radius: 20px 20px 14px 14px; }
    .sale-installments-actions { grid-template-columns: 1fr; }
  }
  @media (min-width: 820px) {
    .sale-layout { grid-template-columns: minmax(0, 1fr) minmax(340px, 390px); align-items: start; padding-bottom: 0; }
    .sale-summary-sticky { display: block; position: sticky; top: 90px; }
    .sale-summary-sticky .sale-submit-btn { display: none !important; }
    .form-grid.sale-two { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .field.full { grid-column: 1 / -1; }
    .sale-payment-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
</style>

<header class="plain-header">
  <div class="page-title-row">
    <div>
      <p class="micro-label dark-text">Frente de caixa</p>
      <h1>Nova venda</h1>
    </div>
    <form method="post" data-sale-remember-step="products" onsubmit="return confirm('Limpar o carrinho?');">
      <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
      <input type="hidden" name="action" value="clear">
      <button class="secondary-btn sale-clear-header-btn" type="submit" aria-label="Limpar carrinho">Limpar</button>
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

  <div class="sale-layout" data-sale-layout>
    <main class="sale-workspace">
      <nav class="sale-steps" aria-label="Etapas da venda">
        <button class="sale-step active" type="button" data-sale-step="products">
          <span>1</span>
          <div>
            <strong>Produto</strong>
            <small>Adicionar itens</small>
          </div>
        </button>
        <button class="sale-step" type="button" data-sale-step="client">
          <span>2</span>
          <div>
            <strong>Cliente</strong>
            <small>Identificação</small>
          </div>
        </button>
        <button class="sale-step" type="button" data-sale-step="payment">
          <span>3</span>
          <div>
            <strong>Pagamento</strong>
            <small>Forma e valores</small>
          </div>
        </button>
        <button class="sale-step" type="button" data-sale-step="review">
          <span>4</span>
          <div>
            <strong>Revisão</strong>
            <small>Finalizar</small>
          </div>
        </button>
      </nav>
      <div id="saleStepMessage" class="sale-alert warning sale-step-warning" role="alert"></div>

      <section class="sale-panel active" data-sale-panel="products">
        <div class="sale-panel-head">
          <div>
            <h2>Produtos</h2>
            <p>Busque, confira estoque e adicione itens ao carrinho.</p>
          </div>
        </div>
        <form class="sale-search-form" method="post" action="nova-venda.php" autocomplete="off" data-sale-remember-step="products">
          <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
          <input id="barcodeInput" type="hidden" name="barcode" value="">
          <label class="search-box">
            <span data-icon="search"></span>
            <input id="productSearchInput" type="search" name="product_search" value="<?= e($query) ?>" placeholder="Digite nome, SKU ou código de barras" autofocus inputmode="search">
          </label>
          <button id="saleScanSubmit" class="sale-scan-submit" type="submit" name="action" value="add_product_by_code" tabindex="-1">Adicionar por código</button>
          <button class="secondary-btn" type="submit" name="action" value="search_product">Buscar</button>
          <button id="saleCameraButton" class="sale-camera-btn" type="button">Ler código de barras</button>
        </form>
        <p class="sale-search-help">Digite para buscar um produto ou use a câmera para ler o código.</p>
        <div id="saleCameraMessage" class="sale-alert danger sale-step-warning" role="alert">Não foi possível acessar a câmera. Digite ou use o leitor físico.</div>

        <div class="sale-product-list" id="saleProducts">
          <?php if ($query === ''): ?>
            <article class="summary-card">Digite o nome, SKU ou código de barras para localizar o produto.</article>
          <?php elseif (saleSearchLength($query) < 2): ?>
            <article class="summary-card">Digite pelo menos 2 caracteres para buscar.</article>
          <?php elseif (!$products): ?>
            <article class="summary-card">Nenhum produto encontrado.</article>
          <?php endif; ?>

          <?php foreach ($products as $product): ?>
            <?php
            $stock = (float)($product['stock'] ?? 0);
            $minStock = (float)($product['minStock'] ?? 0);
            $expiry = trim((string)($product['expiry'] ?? ''));
            $isExpired = $expiry !== '' && $expiry < date('Y-m-d');
            $isLowStock = $stock > 0 && $stock <= $minStock;
            $isNoStock = $stock <= 0;
            $blocked = ($isExpired && saleEnabled($settings, 'block_expired_products', true))
                || ($isNoStock && saleEnabled($settings, 'block_negative_stock', true));
            ?>
            <article class="sale-product-card <?= $isExpired || $isNoStock ? 'warning' : '' ?>">
              <div class="sale-product-top">
                <div>
                  <h3><?= e((string)$product['name']) ?></h3>
                  <p><?= e((string)$product['category']) ?> · <?= e((string)($product['sku'] ?: 'Sem SKU')) ?></p>
                </div>
                <strong class="sale-product-price"><?= e(saleMoney($product['price'])) ?></strong>
              </div>
              <div class="sale-product-meta">
                <span>Código <?= e((string)($product['barcode'] ?: 'sem código')) ?></span>
                <span>Estoque <?= e(saleNumber($stock)) ?></span>
                <span>Validade <?= e($expiry !== '' ? date('d/m/Y', strtotime($expiry)) : 'sem data') ?></span>
                <?php if ($isExpired): ?><em class="sale-mini-badge red">Vencido</em><?php endif; ?>
                <?php if ($isNoStock): ?><em class="sale-mini-badge red">Sem estoque</em><?php endif; ?>
                <?php if ($isLowStock): ?><em class="sale-mini-badge orange">Estoque baixo</em><?php endif; ?>
              </div>
              <form class="sale-product-row section-gap-small" method="post" data-sale-remember-step="products">
                <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                <div class="field">
                  <label for="qty<?= (int)$product['id'] ?>">Quantidade</label>
                  <input id="qty<?= (int)$product['id'] ?>" name="quantity" type="number" min="0.001" step="0.001" value="1">
                </div>
                <button class="secondary-btn" type="submit" <?= $blocked ? 'disabled' : '' ?>>Adicionar</button>
              </form>
            </article>
          <?php endforeach; ?>
        </div>
        <div id="saleStepWarning" class="sale-alert warning sale-step-warning" role="alert">Adicione pelo menos um produto para continuar.</div>
        <?php if ($cartProducts): ?>
          <section class="sale-cart-compact" aria-label="Itens adicionados">
            <div class="sale-cart-summary-card">
              <h3>Resumo da venda</h3>
              <p><?= count($cartProducts) ?> item(ns) adicionados</p>
              <p>Subtotal: <strong><?= e(saleMoney($cartSubtotal)) ?></strong></p>
              <div class="sale-cart-summary-actions">
                <button class="primary-btn" type="button" data-sale-step="client">Continuar para Cliente</button>
                <form method="post" data-sale-remember-step="products" onsubmit="return confirm('Limpar o carrinho?');">
                  <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                  <input type="hidden" name="action" value="clear">
                  <button class="secondary-btn" type="submit">Limpar carrinho</button>
                </form>
              </div>
            </div>

            <div class="sale-cart-card">
              <div class="sale-panel-head">
                <div>
                  <h3>Itens adicionados</h3>
                  <p>Altere quantidades ou remova itens antes de avançar.</p>
                </div>
              </div>
              <form method="post" data-sale-remember-step="products">
                <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="action" value="update_cart">
                <?php foreach ($cartProducts as $item): ?>
                  <?php $product = $item['product']; ?>
                  <div class="sale-cart-item">
                    <div>
                      <strong><?= e((string)$product['name']) ?></strong>
                      <p><?= e(saleMoney($product['price'])) ?> cada · subtotal <?= e(saleMoney($item['lineTotal'])) ?></p>
                    </div>
                    <div>
                      <div class="sale-qty-tools">
                        <button type="button" data-qty-step="-1" aria-label="Diminuir quantidade">-</button>
                        <input name="quantities[<?= (int)$product['id'] ?>]" type="number" min="0" step="0.001" value="<?= e((string)$item['quantity']) ?>">
                        <button type="button" data-qty-step="1" aria-label="Aumentar quantidade">+</button>
                      </div>
                      <button class="sale-cart-remove" name="remove_id" value="<?= (int)$product['id'] ?>" type="submit">Remover</button>
                    </div>
                  </div>
                <?php endforeach; ?>
                <button class="secondary-btn section-gap-small" type="submit">Atualizar itens</button>
              </form>
            </div>
          </section>
        <?php endif; ?>
        <div class="sale-panel-actions single">
          <button class="primary-btn" type="button" data-sale-step="client" data-require-cart="1" <?= !$cartProducts ? 'disabled' : '' ?>>Continuar para Cliente</button>
        </div>
      </section>

      <div id="saleCameraPanel" class="sale-camera-panel" aria-hidden="true">
        <div class="sale-camera-box" role="dialog" aria-modal="true" aria-labelledby="saleCameraTitle">
          <h3 id="saleCameraTitle">Ler código de barras</h3>
          <p>Aponte a câmera para o código. A leitura fecha automaticamente quando detectar.</p>
          <video id="saleCameraVideo" class="sale-camera-video" playsinline muted></video>
          <div id="saleCameraFeedback" class="sale-camera-feedback">Aguardando câmera...</div>
          <div class="sale-camera-actions">
            <button id="saleCameraCancel" class="secondary-btn" type="button">Cancelar leitura</button>
          </div>
        </div>
      </div>

      <form id="saleFinishForm" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="finish">
        <input type="hidden" id="saleCreditInstallments" name="credit_installments" value="<?= e($postedCreditInstallments) ?>">

        <section class="sale-panel" data-sale-panel="client">
          <div class="sale-panel-head">
            <div>
              <h2>Cliente</h2>
              <p>Venda balcão é opcional; conta do cliente exige seleção.</p>
            </div>
          </div>
          <div class="form-grid sale-two">
            <div class="field full">
              <label for="saleClient">Cliente</label>
              <select id="saleClient" name="cliente_id">
                <option value="0" data-name="Venda sem cliente" data-phone="" data-cpf="" data-status="">Venda balcão</option>
                <?php foreach ($clients as $client): ?>
                  <option value="<?= (int)$client['id'] ?>"
                    data-name="<?= e((string)$client['name']) ?>"
                    data-phone="<?= e((string)$client['phone']) ?>"
                    data-cpf="<?= e((string)$client['cpf']) ?>"
                    data-status="<?= e((string)$client['status']) ?>">
                    <?= e((string)$client['name']) ?><?= $client['phone'] ? ' - ' . e((string)$client['phone']) : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="sale-selected-client full" id="saleSelectedClient">
              <strong>Venda sem cliente</strong>
              <p>Cliente opcional para vendas comuns.</p>
            </div>
            <div class="sale-fiado-alert full">Para venda na conta do cliente, selecione um cliente.</div>
          </div>
          <div class="sale-panel-actions">
            <button class="secondary-btn" type="button" data-sale-step="products">Voltar</button>
            <button class="primary-btn" type="button" data-sale-step="payment">Continuar</button>
          </div>
        </section>

        <section class="sale-panel" data-sale-panel="payment">
          <div class="sale-panel-head">
            <div>
              <h2>Pagamento</h2>
              <p>Escolha uma forma habilitada e informe desconto ou dinheiro recebido.</p>
            </div>
          </div>
          <div class="form-grid sale-two">
            <div class="field full">
              <label>Forma de pagamento</label>
              <div class="sale-payment-grid">
                <?php foreach ($enabledPayments as $method => $label): ?>
                  <label class="sale-payment-card">
                    <input type="radio" name="payment" value="<?= e($method) ?>" data-payment-label="<?= e($label) ?>" <?= $method === $selectedPaymentMethod ? 'checked' : '' ?>>
                    <span class="sale-payment-icon"><?= e(salePaymentIcon($method)) ?></span>
                    <span>
                      <?= e($label) ?>
                      <?php if ($method === 'credito'): ?>
                        <span id="saleCreditInstallmentsLabel" class="sale-credit-installments-label" hidden></span>
                      <?php endif; ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="field">
              <label for="saleDiscount">Desconto</label>
              <input id="saleDiscount" name="discount" type="number" min="0" step="0.01" value="0" <?= saleEnabled($settings, 'allow_discount', true) ? '' : 'readonly' ?>>
            </div>

            <div class="field" data-payment-field="received">
              <label for="saleReceived">Valor recebido em dinheiro</label>
              <input id="saleReceived" name="received" type="number" min="0" step="0.01" placeholder="<?= e(saleMoney($cartSubtotal)) ?>">
            </div>

            <div class="summary-card full">
              <div class="sale-summary-lines">
                <div><span>Subtotal</span><strong id="salePaymentSubtotal"><?= e(saleMoney($cartSubtotal)) ?></strong></div>
                <div><span>Desconto</span><strong id="salePaymentDiscount">R$ 0,00</strong></div>
                <div><span>Total final</span><strong id="salePaymentTotal"><?= e(saleMoney($cartSubtotal)) ?></strong></div>
                <div><span>Desconto equivalente</span><strong id="saleDiscountPercent">0,00%</strong></div>
                <div><span>Limite permitido</span><strong><?= e(number_format((float)($settings['discount_limit_percent'] ?? 0), 2, ',', '.')) ?>%</strong></div>
                <div data-payment-field="received"><span>Troco estimado</span><strong id="saleChangeValue">R$ 0,00</strong></div>
              </div>
            </div>

            <div class="field" data-payment-field="due-date">
              <label for="saleDueDate">Vencimento da conta</label>
              <input id="saleDueDate" name="due_date" type="date" value="<?= e((new DateTimeImmutable('today'))->modify('+' . max(0, (int)($settings['debt_due_days'] ?? 30)) . ' days')->format('Y-m-d')) ?>">
            </div>

            <?php if (isset($enabledPayments['misto'])): ?>
              <div class="field full" data-payment-field="mixed">
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
                <div class="summary-card section-gap-small">
                  <div class="sale-summary-lines">
                    <div><span>Total da venda</span><strong id="saleMixedSaleTotal"><?= e(saleMoney($cartSubtotal)) ?></strong></div>
                    <div><span>Total informado</span><strong id="saleMixedTotal">R$ 0,00</strong></div>
                    <div><span>Diferença</span><strong id="saleMixedDifference"><?= e(saleMoney($cartSubtotal)) ?></strong></div>
                  </div>
                </div>
              </div>
            <?php endif; ?>

            <div class="field full">
              <label for="saleObservation">Observação</label>
              <textarea id="saleObservation" name="observacao"></textarea>
            </div>
          </div>
          <div id="salePaymentValidation" class="sale-alert warning sale-step-warning sale-payment-validation" role="alert"></div>
          <div class="sale-panel-actions">
            <button class="secondary-btn" type="button" data-sale-step="client">Voltar</button>
            <button id="saleReviewButton" class="primary-btn" type="button" data-sale-step="review">Revisar venda</button>
          </div>
        </section>

        <section class="sale-panel" data-sale-panel="review">
          <div class="sale-panel-head">
            <div>
              <h2>Revisão</h2>
              <p>Confira itens, cliente, pagamento e total antes de finalizar.</p>
            </div>
          </div>
          <div class="sale-review-list">
            <?php if (!$cartProducts): ?>
              <article class="summary-card">Adicione produtos para revisar a venda.</article>
            <?php endif; ?>
            <?php foreach ($cartProducts as $item): ?>
              <?php $product = $item['product']; ?>
              <div class="sale-review-row">
                <span><?= e((string)$product['name']) ?> · <?= e(saleNumber($item['quantity'])) ?> x <?= e(saleMoney($product['price'])) ?></span>
                <strong><?= e(saleMoney($item['lineTotal'])) ?></strong>
              </div>
            <?php endforeach; ?>
            <div class="sale-review-row"><span>Cliente</span><strong id="saleReviewClient">Venda balcão</strong></div>
            <div class="sale-review-row"><span>Pagamento</span><strong id="saleReviewPayment"><?= e($selectedPaymentLabel) ?></strong></div>
            <div id="saleReviewInstallmentsRow" class="sale-review-row" hidden><span>Parcelamento</span><strong id="saleReviewInstallments"></strong></div>
            <div class="sale-review-row"><span>Desconto</span><strong id="saleReviewDiscount">R$ 0,00</strong></div>
            <div class="sale-review-row" data-review-field="change"><span>Troco</span><strong id="saleReviewChange">R$ 0,00</strong></div>
            <div class="sale-review-row" data-review-field="due-date"><span>Vencimento</span><strong id="saleReviewDueDate">Não informado</strong></div>
            <div class="sale-review-row" data-review-field="mixed"><span>Composição</span><strong id="saleReviewMixed">Não informado</strong></div>
            <div class="sale-review-row" data-review-field="observation"><span>Observação</span><strong id="saleReviewObservation">Sem observação</strong></div>
            <div class="sale-review-row"><span>Total final</span><strong id="saleReviewTotal"><?= e(saleMoney($cartSubtotal)) ?></strong></div>
          </div>
          <div class="sale-panel-actions">
            <button class="secondary-btn" type="button" data-sale-step="payment">Voltar para editar</button>
            <button class="primary-btn sale-submit-btn" type="submit" form="saleFinishForm" <?= !$cartProducts || !$enabledPayments ? 'disabled' : '' ?>>Finalizar venda</button>
          </div>
        </section>
      </form>
    </main>

    <aside class="sale-summary-sticky">
      <section class="sale-cart-card">
        <div class="sale-panel-head">
          <div>
            <h2>Resumo</h2>
            <p><?= count($cartProducts) ?> item(ns) adicionados</p>
          </div>
        </div>
        <?php if (!$cartProducts): ?>
          <article class="summary-card">Nenhum item adicionado.</article>
        <?php endif; ?>

        <div class="sale-summary-lines">
          <div><span>Subtotal</span><strong><?= e(saleMoney($cartSubtotal)) ?></strong></div>
          <div><span>Desconto</span><strong id="saleSummaryDiscount">R$ 0,00</strong></div>
          <div><span>Pagamento</span><strong id="saleSummaryPayment"><?= e($selectedPaymentLabel) ?></strong></div>
        </div>
        <div class="sale-summary-total">
          <span>Total da venda</span>
          <strong id="saleSummaryTotal"><?= e(saleMoney($cartSubtotal)) ?></strong>
        </div>
      </section>
    </aside>
  </div>
</section>

<div id="creditInstallmentsModal" class="sale-installments-backdrop" aria-hidden="true">
  <section id="creditInstallmentsDialog" class="sale-installments-modal" role="dialog" aria-modal="true" aria-labelledby="creditInstallmentsTitle">
    <div class="sale-installments-header">
      <div>
        <p class="micro-label dark-text">Cartão de crédito</p>
        <h2 id="creditInstallmentsTitle">Informe as parcelas</h2>
      </div>
    </div>
    <p class="sale-installments-description">Digite 1 para crédito à vista ou de 2 a 12 para parcelado.</p>
    <div class="field">
      <label for="creditInstallmentsInput">Número de parcelas</label>
      <input id="creditInstallmentsInput" type="number" min="1" max="12" step="1" inputmode="numeric" autocomplete="off" placeholder="Ex.: 3">
    </div>
    <div id="creditInstallmentsError" class="sale-alert danger" role="alert" hidden></div>
    <div class="sale-installments-preview">
      <span>Forma selecionada</span>
      <strong id="creditInstallmentsPreview">Informe a quantidade</strong>
    </div>
    <div class="sale-installments-actions">
      <button id="creditInstallmentsCancel" type="button" class="secondary-btn">Cancelar</button>
      <button id="creditInstallmentsConfirm" type="button" class="primary-btn">Confirmar parcelas</button>
    </div>
  </section>
</div>

<script>
(() => {
  const layout = document.querySelector('[data-sale-layout]');
  if (!layout) return;

  layout.classList.add('sale-js');
  const subtotal = Number(<?= json_encode(round($cartSubtotal, 2)) ?>);
  const cartItems = Number(<?= json_encode(count($cartProducts)) ?>);
  const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
  const productSearch = document.getElementById('productSearchInput');
  const barcodeInput = document.getElementById('barcodeInput');
  const scanSubmit = document.getElementById('saleScanSubmit');
  const searchForm = productSearch?.closest('form');
  const searchButton = searchForm?.querySelector('button[name="action"][value="search_product"]');
  const finishForm = document.getElementById('saleFinishForm');
  const cameraButton = document.getElementById('saleCameraButton');
  const cameraPanel = document.getElementById('saleCameraPanel');
  const cameraCancel = document.getElementById('saleCameraCancel');
  const cameraVideo = document.getElementById('saleCameraVideo');
  const cameraFeedback = document.getElementById('saleCameraFeedback');
  const cameraMessage = document.getElementById('saleCameraMessage');
  const stepMessage = document.getElementById('saleStepMessage');
  const paymentValidation = document.getElementById('salePaymentValidation');
  const reviewButton = document.getElementById('saleReviewButton');
  const creditInstallmentsHidden = document.getElementById('saleCreditInstallments');
  const creditInstallmentsModal = document.getElementById('creditInstallmentsModal');
  const creditInstallmentsInput = document.getElementById('creditInstallmentsInput');
  const creditInstallmentsConfirm = document.getElementById('creditInstallmentsConfirm');
  const creditInstallmentsCancel = document.getElementById('creditInstallmentsCancel');
  const creditInstallmentsError = document.getElementById('creditInstallmentsError');
  const creditInstallmentsPreview = document.getElementById('creditInstallmentsPreview');
  const creditInstallmentsLabel = document.getElementById('saleCreditInstallmentsLabel');
  const saleReviewInstallmentsRow = document.getElementById('saleReviewInstallmentsRow');
  const saleReviewInstallments = document.getElementById('saleReviewInstallments');
  const allowedSteps = ['products', 'client', 'payment', 'review'];
  let currentStep = 'products';
  let previousPaymentMethod = '';
  let confirmedCreditInstallments = 0;
  let installmentsModalOpen = false;
  let cameraStream = null;
  let cameraLoop = 0;
  let zxingControls = null;

  function numericValue(selector) {
    const element = document.querySelector(selector);
    return Math.max(0, Number(String(element?.value || '0').replace(',', '.')) || 0);
  }

  function paymentMethod() {
    return document.querySelector('input[name="payment"]:checked')?.value || '';
  }

  function setPaymentMethod(method) {
    document.querySelectorAll('input[name="payment"]').forEach((input) => {
      input.checked = input.value === method;
    });
  }

  function parseInstallmentsValue(value) {
    const raw = String(value || '').trim();
    if (!/^\d+$/.test(raw)) return 0;
    const installments = Number.parseInt(raw, 10);

    return Number.isInteger(installments) && installments >= 1 && installments <= 12 ? installments : 0;
  }

  function creditInstallmentsText(installments, short = false) {
    if (installments === 1) return short ? 'à vista' : 'Crédito à vista';

    return short ? `${installments}x` : `Crédito em ${installments}x`;
  }

  function reviewInstallmentsText(installments) {
    if (installments === 1) return 'À vista';

    return `${installments} parcelas`;
  }

  function resetCreditInstallments() {
    confirmedCreditInstallments = 0;
    if (creditInstallmentsHidden) creditInstallmentsHidden.value = '';
    if (creditInstallmentsInput) creditInstallmentsInput.value = '';
    updateCreditInstallmentsDisplay();
  }

  function selectedPaymentLabel() {
    const selected = document.querySelector('input[name="payment"]:checked');
    const label = selected?.dataset.paymentLabel || 'Não informado';

    if (selected?.value === 'credito' && confirmedCreditInstallments > 0) {
      return `${label} · ${creditInstallmentsText(confirmedCreditInstallments, true)}`;
    }

    return label;
  }

  function updateCreditInstallmentsDisplay() {
    const show = paymentMethod() === 'credito' && confirmedCreditInstallments > 0;

    if (creditInstallmentsLabel) {
      creditInstallmentsLabel.hidden = !show;
      creditInstallmentsLabel.textContent = show ? creditInstallmentsText(confirmedCreditInstallments) : '';
    }

    if (saleReviewInstallmentsRow) {
      saleReviewInstallmentsRow.hidden = !show;
    }

    if (saleReviewInstallments) {
      saleReviewInstallments.textContent = show ? reviewInstallmentsText(confirmedCreditInstallments) : '';
    }
  }

  function setInstallmentsError(message) {
    if (!creditInstallmentsError) return;
    creditInstallmentsError.hidden = message === '';
    creditInstallmentsError.textContent = message;
  }

  function updateInstallmentsPreview() {
    const installments = parseInstallmentsValue(creditInstallmentsInput?.value || '');
    if (!creditInstallmentsPreview) return;

    creditInstallmentsPreview.textContent = installments > 0
      ? creditInstallmentsText(installments)
      : 'Informe a quantidade';
  }

  function openCreditInstallmentsModal() {
    if (!creditInstallmentsModal || !creditInstallmentsInput) return;
    installmentsModalOpen = true;
    setInstallmentsError('');
    creditInstallmentsInput.value = confirmedCreditInstallments > 0 ? String(confirmedCreditInstallments) : '';
    updateInstallmentsPreview();
    creditInstallmentsModal.classList.add('open');
    creditInstallmentsModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    setTimeout(() => {
      creditInstallmentsInput.focus();
      creditInstallmentsInput.select();
    }, 0);
  }

  function closeCreditInstallmentsModal(restorePreviousPayment = false) {
    if (!creditInstallmentsModal) return;
    installmentsModalOpen = false;
    creditInstallmentsModal.classList.remove('open');
    creditInstallmentsModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    setInstallmentsError('');

    if (restorePreviousPayment) {
      resetCreditInstallments();
      setPaymentMethod(previousPaymentMethod || '');
    }

    document.querySelector('input[name="payment"][value="credito"]')?.closest('.sale-payment-card')?.focus?.({ preventScroll: true });
    updatePaymentVisibility();
    updateTotals();
  }

  function confirmCreditInstallments() {
    const installments = parseInstallmentsValue(creditInstallmentsInput?.value || '');

    if (installments < 1) {
      setInstallmentsError('Informe uma quantidade de parcelas entre 1 e 12.');
      creditInstallmentsInput?.focus();
      return false;
    }

    confirmedCreditInstallments = installments;
    if (creditInstallmentsHidden) creditInstallmentsHidden.value = String(installments);
    previousPaymentMethod = 'credito';
    closeCreditInstallmentsModal(false);
    updateCreditInstallmentsDisplay();
    updateTotals();

    return true;
  }

  function mixedInputs() {
    return Array.from(document.querySelectorAll('input[name^="mixed["]'));
  }

  function mixedTotalValue() {
    return mixedInputs().reduce((sum, input) => sum + Math.max(0, Number(String(input.value || '0').replace(',', '.')) || 0), 0);
  }

  function getSaleState() {
    const discount = numericValue('#saleDiscount');
    const total = Math.max(0, subtotal - discount);
    const received = numericValue('#saleReceived');

    return {
      cartItems,
      subtotal,
      discount,
      total,
      received,
      change: Math.max(0, received - total),
      payment: paymentMethod(),
      creditInstallments: parseInstallmentsValue(creditInstallmentsHidden?.value || ''),
      clientId: Number(document.getElementById('saleClient')?.value || 0),
      mixedTotal: mixedTotalValue(),
      dueDate: document.getElementById('saleDueDate')?.value || '',
    };
  }

  function showStepMessage(message, type = 'warning') {
    if (!stepMessage) return;
    stepMessage.textContent = message;
    stepMessage.className = `sale-alert ${type} sale-step-warning visible`;
  }

  function hideStepMessage() {
    if (!stepMessage) return;
    stepMessage.textContent = '';
    stepMessage.classList.remove('visible');
  }

  function showPaymentValidation(message) {
    if (!paymentValidation) return;
    paymentValidation.textContent = message;
    paymentValidation.classList.add('visible');
  }

  function hidePaymentValidation() {
    if (!paymentValidation) return;
    paymentValidation.textContent = '';
    paymentValidation.classList.remove('visible');
  }

  function showPaymentField(name) {
    document.querySelectorAll(`[data-payment-field="${name}"]`).forEach((field) => {
      field.hidden = false;
    });
  }

  function updatePaymentVisibility() {
    const payment = paymentMethod();

    document.querySelectorAll('[data-payment-field]').forEach((field) => {
      field.hidden = true;
    });

    if (payment === 'dinheiro') {
      showPaymentField('received');
    }

    if (payment === 'conta_cliente') {
      showPaymentField('due-date');
    }

    if (payment === 'misto') {
      showPaymentField('mixed');
    }
  }

  function validateMixedPayment() {
    const state = getSaleState();
    if (state.payment !== 'misto') return true;

    return Math.abs(state.mixedTotal - state.total) <= 0.01;
  }

  function validateAccountCustomer() {
    const state = getSaleState();
    if (state.payment !== 'conta_cliente') return true;

    return state.clientId > 0;
  }

  function paymentBlockReason() {
    const state = getSaleState();

    if (state.cartItems < 1) {
      return 'Adicione pelo menos um produto para continuar.';
    }

    if (!state.payment) {
      return 'Selecione uma forma de pagamento.';
    }

    if (state.discount > state.subtotal) {
      return 'O desconto não pode ser maior que o subtotal.';
    }

    if (!validateAccountCustomer()) {
      return 'Para vender fiado, selecione um cliente.';
    }

    if (state.payment === 'credito' && state.creditInstallments < 1) {
      return 'Informe e confirme a quantidade de parcelas do cartão de crédito.';
    }

    if (!validateMixedPayment()) {
      return 'A soma do pagamento misto precisa bater com o total final.';
    }

    return '';
  }

  function validatePaymentStep() {
    const reason = paymentBlockReason();
    const valid = reason === '';

    if (reviewButton) {
      reviewButton.disabled = !valid;
    }

    document.querySelectorAll('.sale-submit-btn').forEach((button) => {
      button.disabled = !valid;
    });

    if (!valid && currentStep === 'payment') {
      showPaymentValidation(reason);
    } else {
      hidePaymentValidation();
    }

    return valid;
  }

  function canEnterStep(step) {
    const state = getSaleState();

    if (step === 'products') return true;
    if (step === 'client' || step === 'payment') return state.cartItems > 0;
    if (step === 'review') return validatePaymentStep();

    return false;
  }

  function blockedStepMessage(step) {
    if (cartItems < 1 && step !== 'products') {
      return 'Adicione pelo menos um produto para continuar.';
    }

    if (step === 'review') {
      return paymentBlockReason() || 'Revise os dados de pagamento antes de finalizar.';
    }

    return 'Conclua a etapa anterior antes de continuar.';
  }

  function showStep(step) {
    const target = allowedSteps.includes(step) ? step : 'products';
    currentStep = target;
    layout.querySelectorAll('.sale-step[data-sale-step]').forEach((button) => {
      button.classList.toggle('active', button.dataset.saleStep === target);
    });
    layout.querySelectorAll('[data-sale-panel]').forEach((panel) => {
      panel.classList.toggle('active', panel.dataset.salePanel === target);
    });
    updatePaymentVisibility();
    updateTotals();
    validatePaymentStep();
    if (window.matchMedia('(max-width: 819px)').matches) {
      layout.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function setStep(step) {
    const target = allowedSteps.includes(step) ? step : 'products';

    if (!canEnterStep(target)) {
      showStepMessage(blockedStepMessage(target));
      if (target === 'review' && cartItems > 0) {
        showStep('payment');
        sessionStorage.setItem('saleCurrentStep', 'payment');
        if (paymentMethod() === 'credito' && parseInstallmentsValue(creditInstallmentsHidden?.value || '') < 1) {
          openCreditInstallmentsModal();
        }
      }
      return;
    }

    hideStepMessage();
    sessionStorage.setItem('saleCurrentStep', target);
    showStep(target);
  }

  function formatDate(value) {
    if (!value) return 'Não informado';
    const [year, month, day] = value.split('-');
    if (!year || !month || !day) return value;

    return `${day}/${month}/${year}`;
  }

  function updateClient() {
    const select = document.getElementById('saleClient');
    const card = document.getElementById('saleSelectedClient');
    const review = document.getElementById('saleReviewClient');
    if (!select || !card) return;
    const option = select.options[select.selectedIndex];
    const name = option?.dataset.name || 'Venda sem cliente';
    const phone = option?.dataset.phone || 'Sem telefone';
    const cpf = option?.dataset.cpf || 'Sem CPF/CNPJ';
    const status = option?.dataset.status || 'Cliente não selecionado';
    const title = document.createElement('strong');
    const contact = document.createElement('p');
    const balance = document.createElement('p');
    title.textContent = name;
    contact.textContent = `${phone} - ${cpf}`;
    balance.textContent = `Status financeiro: ${status}`;
    card.replaceChildren(title, contact, balance);
    if (review) review.textContent = name;
  }

  function updateReviewDetails(state) {
    const reviewChange = document.querySelector('[data-review-field="change"]');
    const reviewDueDate = document.querySelector('[data-review-field="due-date"]');
    const reviewMixed = document.querySelector('[data-review-field="mixed"]');
    const reviewObservation = document.querySelector('[data-review-field="observation"]');
    const observation = String(document.getElementById('saleObservation')?.value || '').trim();

    if (reviewChange) {
      reviewChange.hidden = state.payment !== 'dinheiro';
      document.getElementById('saleReviewChange')?.replaceChildren(document.createTextNode(money.format(state.change)));
    }

    if (reviewDueDate) {
      reviewDueDate.hidden = state.payment !== 'conta_cliente';
      document.getElementById('saleReviewDueDate')?.replaceChildren(document.createTextNode(formatDate(state.dueDate)));
    }

    if (reviewMixed) {
      reviewMixed.hidden = state.payment !== 'misto';
      const composition = mixedInputs()
        .map((input) => {
          const label = input.closest('.field')?.querySelector('label')?.textContent?.trim() || input.name;
          const value = Math.max(0, Number(String(input.value || '0').replace(',', '.')) || 0);
          return value > 0 ? `${label}: ${money.format(value)}` : '';
        })
        .filter(Boolean)
        .join(' · ') || 'Não informado';
      document.getElementById('saleReviewMixed')?.replaceChildren(document.createTextNode(composition));
    }

    if (reviewObservation) {
      reviewObservation.hidden = observation === '';
      document.getElementById('saleReviewObservation')?.replaceChildren(document.createTextNode(observation || 'Sem observação'));
    }
  }

  function updateTotals() {
    const state = getSaleState();
    const discountPercent = state.subtotal > 0 ? (state.discount / state.subtotal) * 100 : 0;
    const mixedDifference = state.total - state.mixedTotal;

    document.getElementById('salePaymentSubtotal')?.replaceChildren(document.createTextNode(money.format(state.subtotal)));
    document.getElementById('salePaymentDiscount')?.replaceChildren(document.createTextNode(money.format(state.discount)));
    document.getElementById('salePaymentTotal')?.replaceChildren(document.createTextNode(money.format(state.total)));
    document.getElementById('saleSummaryDiscount')?.replaceChildren(document.createTextNode(money.format(state.discount)));
    document.getElementById('saleReviewDiscount')?.replaceChildren(document.createTextNode(money.format(state.discount)));
    document.getElementById('saleSummaryTotal')?.replaceChildren(document.createTextNode(money.format(state.total)));
    document.getElementById('saleReviewTotal')?.replaceChildren(document.createTextNode(money.format(state.total)));
    document.getElementById('saleDiscountPercent')?.replaceChildren(document.createTextNode(`${discountPercent.toFixed(2).replace('.', ',')}%`));
    document.getElementById('saleChangeValue')?.replaceChildren(document.createTextNode(money.format(state.change)));
    document.getElementById('saleMixedSaleTotal')?.replaceChildren(document.createTextNode(money.format(state.total)));
    document.getElementById('saleMixedTotal')?.replaceChildren(document.createTextNode(money.format(state.mixedTotal)));
    document.getElementById('saleMixedDifference')?.replaceChildren(document.createTextNode(money.format(Math.abs(mixedDifference))));
    document.getElementById('saleSummaryPayment')?.replaceChildren(document.createTextNode(selectedPaymentLabel()));
    document.getElementById('saleReviewPayment')?.replaceChildren(document.createTextNode(selectedPaymentLabel()));
    updateCreditInstallmentsDisplay();
    updateReviewDetails(state);
    validatePaymentStep();
  }

  function submitBarcode(code) {
    const value = String(code || '').trim();
    if (!value || !searchForm || !productSearch || !scanSubmit) return;
    productSearch.value = value;
    if (barcodeInput) barcodeInput.value = value;
    sessionStorage.setItem('saleCurrentStep', 'products');
    searchForm.requestSubmit(scanSubmit);
  }

  function bindSubmitStepMemory() {
    document.querySelectorAll('form[data-sale-remember-step]').forEach((form) => {
      form.addEventListener('submit', () => {
        sessionStorage.setItem('saleCurrentStep', form.dataset.saleRememberStep || 'products');
      });
    });
  }

  function showCameraMessage(message) {
    if (cameraMessage) {
      cameraMessage.textContent = message;
      cameraMessage.classList.add('visible');
    }
  }

  function stopCamera() {
    if (cameraLoop) {
      cancelAnimationFrame(cameraLoop);
      cameraLoop = 0;
    }
    if (zxingControls?.stop) {
      zxingControls.stop();
      zxingControls = null;
    }
    if (cameraStream) {
      cameraStream.getTracks().forEach((track) => track.stop());
      cameraStream = null;
    }
    if (cameraVideo) cameraVideo.srcObject = null;
    cameraPanel?.classList.remove('open');
    cameraPanel?.setAttribute('aria-hidden', 'true');
    productSearch?.focus({ preventScroll: true });
  }

  async function loadZxing() {
    if (window.ZXingBrowser) return window.ZXingBrowser;
    await new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://unpkg.com/@zxing/browser@latest/umd/index.min.js';
      script.async = true;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
    return window.ZXingBrowser;
  }

  async function startNativeBarcodeReader() {
    const detector = new BarcodeDetector({
      formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e', 'qr_code'],
    });
    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' } },
      audio: false,
    });
    cameraVideo.srcObject = cameraStream;
    await cameraVideo.play();

    const scanFrame = async () => {
      if (!cameraStream) return;
      try {
        const codes = await detector.detect(cameraVideo);
        if (codes.length > 0) {
          const value = codes[0].rawValue || '';
          stopCamera();
          submitBarcode(value);
          return;
        }
      } catch (error) {
        stopCamera();
        showCameraMessage('Não foi possível ler pela câmera. Digite ou use o leitor físico.');
        return;
      }
      cameraLoop = requestAnimationFrame(scanFrame);
    };

    cameraLoop = requestAnimationFrame(scanFrame);
  }

  async function startZxingBarcodeReader() {
    const zxing = await loadZxing();
    const reader = new zxing.BrowserMultiFormatReader();
    zxingControls = await reader.decodeFromVideoDevice(null, cameraVideo, (result) => {
      if (!result) return;
      const value = typeof result.getText === 'function' ? result.getText() : String(result.text || '');
      stopCamera();
      submitBarcode(value);
    });
  }

  async function openCamera() {
    if (!cameraPanel || !cameraVideo || !navigator.mediaDevices?.getUserMedia) {
      showCameraMessage('Não foi possível acessar a câmera. Digite ou use o leitor físico.');
      return;
    }

    cameraMessage?.classList.remove('visible');
    cameraPanel.classList.add('open');
    cameraPanel.setAttribute('aria-hidden', 'false');
    if (cameraFeedback) cameraFeedback.textContent = 'Aponte a câmera para o código.';

    try {
      if ('BarcodeDetector' in window) {
        await startNativeBarcodeReader();
      } else {
        await startZxingBarcodeReader();
      }
    } catch (error) {
      stopCamera();
      showCameraMessage('Não foi possível acessar a câmera. Digite ou use o leitor físico.');
    }
  }

  document.addEventListener('click', (event) => {
    const stepButton = event.target.closest('[data-sale-step]');
    if (stepButton) {
      setStep(stepButton.dataset.saleStep);
    }

    const qtyButton = event.target.closest('[data-qty-step]');
    if (qtyButton) {
      const input = qtyButton.parentElement?.querySelector('input[type="number"]');
      if (!input) return;
      const step = Number(qtyButton.dataset.qtyStep || 0);
      const current = Number(input.value || 0);
      input.value = Math.max(0, current + step).toFixed(3).replace(/\.?0+$/, '');
    }
  });

  document.addEventListener('input', (event) => {
    if (event.target.matches('#saleDiscount, #saleReceived, #saleObservation, #saleDueDate, input[name^="mixed["]')) {
      updateTotals();
    }
    if (event.target.matches('#productSearchInput')) {
      if (barcodeInput) barcodeInput.value = '';
    }
  });
  document.addEventListener('change', (event) => {
    if (event.target.matches('#saleClient')) {
      updateClient();
      updateTotals();
    }
    if (event.target.matches('input[name="payment"]')) {
      const selected = event.target.value;
      if (selected === 'credito') {
        openCreditInstallmentsModal();
      } else {
        previousPaymentMethod = selected;
        resetCreditInstallments();
      }
      updatePaymentVisibility();
      updateTotals();
    }
  });
  productSearch?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      if (barcodeInput) barcodeInput.value = '';
      sessionStorage.setItem('saleCurrentStep', 'products');
      searchForm?.requestSubmit(searchButton);
    }
  });
  finishForm?.addEventListener('submit', (event) => {
    if (currentStep !== 'review' || !canEnterStep('review')) {
      event.preventDefault();
      showStepMessage(blockedStepMessage('review'));
      showStep(canEnterStep('payment') ? 'payment' : 'products');
      if (paymentMethod() === 'credito' && parseInstallmentsValue(creditInstallmentsHidden?.value || '') < 1) {
        openCreditInstallmentsModal();
      }
      return;
    }
    sessionStorage.removeItem('saleCurrentStep');
  });
  creditInstallmentsConfirm?.addEventListener('click', confirmCreditInstallments);
  creditInstallmentsCancel?.addEventListener('click', () => closeCreditInstallmentsModal(true));
  creditInstallmentsInput?.addEventListener('input', () => {
    setInstallmentsError('');
    updateInstallmentsPreview();
  });
  creditInstallmentsInput?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      confirmCreditInstallments();
    }
    if (event.key === 'Escape') {
      event.preventDefault();
      closeCreditInstallmentsModal(true);
    }
  });
  creditInstallmentsModal?.addEventListener('click', (event) => {
    if (event.target === creditInstallmentsModal) closeCreditInstallmentsModal(true);
  });
  cameraButton?.addEventListener('click', openCamera);
  cameraCancel?.addEventListener('click', stopCamera);
  cameraPanel?.addEventListener('click', (event) => {
    if (event.target === cameraPanel) stopCamera();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && installmentsModalOpen) {
      event.preventDefault();
      closeCreditInstallmentsModal(true);
      return;
    }
    if (event.key === 'Escape' && cameraPanel?.classList.contains('open')) {
      stopCamera();
    }
  });

  bindSubmitStepMemory();
  confirmedCreditInstallments = paymentMethod() === 'credito'
    ? parseInstallmentsValue(creditInstallmentsHidden?.value || '')
    : 0;
  if (paymentMethod() !== 'credito') {
    resetCreditInstallments();
  }
  previousPaymentMethod = paymentMethod() === 'credito' ? '' : paymentMethod();
  updateCreditInstallmentsDisplay();
  updateClient();
  updatePaymentVisibility();
  updateTotals();
  const savedStep = sessionStorage.getItem('saleCurrentStep') || 'products';
  if (savedStep === 'review' && !canEnterStep('review')) {
    showStep(canEnterStep('payment') ? 'payment' : 'products');
    sessionStorage.setItem('saleCurrentStep', canEnterStep('payment') ? 'payment' : 'products');
  } else if (canEnterStep(savedStep)) {
    showStep(savedStep);
  } else {
    showStep('products');
    sessionStorage.setItem('saleCurrentStep', 'products');
  }
  productSearch?.focus({ preventScroll: true });
  productSearch?.select();
})();
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
