<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Repositories\ClientAccountRepository;
use App\Repositories\ClientRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SaleRepository;
use App\Repositories\SettingsRepository;
use InvalidArgumentException;
use Throwable;

final class SaleService
{
    private SaleRepository $sales;
    private ProductRepository $products;
    private ClientRepository $clients;
    private PaymentRepository $payments;
    private ClientAccountRepository $accounts;
    private SettingsRepository $settings;

    public function __construct(
        ?SaleRepository $sales = null,
        ?ProductRepository $products = null,
        ?ClientRepository $clients = null,
        ?PaymentRepository $payments = null,
        ?ClientAccountRepository $accounts = null,
        ?SettingsRepository $settings = null
    ) {
        $this->sales = $sales ?? new SaleRepository();
        $this->products = $products ?? new ProductRepository();
        $this->clients = $clients ?? new ClientRepository();
        $this->payments = $payments ?? new PaymentRepository();
        $this->accounts = $accounts ?? new ClientAccountRepository();
        $this->settings = $settings ?? new SettingsRepository();
    }

    public function list(int $empresaId): array
    {
        return $this->sales->findAll($empresaId);
    }

    public function details(int $empresaId, int $id): ?array
    {
        return $this->sales->findById($empresaId, $id);
    }

    public function create(int $empresaId, int $usuarioId, array $payload): int
    {
        $sale = $this->finalize($empresaId, $usuarioId, $payload);

        return (int)($sale['id'] ?? 0);
    }

    public function finalize(int $empresaId, int $usuarioId, array $payload): array
    {
        $settings = $this->settingsWithDefaults($empresaId);
        $items = $this->normalizeItems($payload['items'] ?? []);

        if (!$items) {
            throw new InvalidArgumentException('Informe ao menos um produto para a venda.');
        }

        $paymentMethod = $this->mapPaymentMethod((string)($payload['payment'] ?? $payload['metodo'] ?? 'pix'));
        $this->assertPaymentEnabled($paymentMethod, $settings);

        $discount = $this->moneyValue($payload['discount'] ?? $payload['desconto'] ?? 0, 'Desconto');
        $addition = $this->moneyValue($payload['addition'] ?? $payload['acrescimo'] ?? 0, 'Acréscimo');
        $received = $this->optionalMoneyValue($payload['received'] ?? $payload['valor_recebido'] ?? null, 'Valor recebido');
        $clientId = (int)($payload['clientId'] ?? $payload['cliente_id'] ?? 0);
        $dueDate = trim((string)($payload['dueDate'] ?? $payload['vencimento'] ?? ''));
        $blockExpired = $this->enabled($settings, 'block_expired_products', true);
        $blockNegativeStock = $this->enabled($settings, 'block_negative_stock', true);
        $allowDiscount = $this->enabled($settings, 'allow_discount', true);
        $discountLimitPercent = max(0.0, min(100.0, (float)($settings['discount_limit_percent'] ?? 0)));

        if (!$allowDiscount && $discount > 0) {
            throw new InvalidArgumentException('Desconto não permitido pelas configurações.');
        }

        $saleItems = [];
        $subtotal = 0.0;

        foreach ($items as $productId => $quantity) {
            $product = $this->products->findById($empresaId, (int)$productId);

            if (!$product) {
                throw new InvalidArgumentException('Produto não encontrado.');
            }

            if ($blockExpired && $this->isExpired((string)($product['expiry'] ?? ''))) {
                throw new InvalidArgumentException('Produto vencido bloqueado pelas configurações: ' . $product['name']);
            }

            if ($blockNegativeStock && $quantity > (float)$product['stock']) {
                throw new InvalidArgumentException('Estoque insuficiente para o produto: ' . $product['name']);
            }

            $lineTotal = round($quantity * (float)$product['price'], 2);
            $subtotal += $lineTotal;
            $saleItems[] = [
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => $lineTotal,
            ];
        }

        $subtotal = round($subtotal, 2);
        $maxDiscount = round($subtotal * ($discountLimitPercent / 100), 2);
        if ($allowDiscount && $discount > $maxDiscount) {
            throw new InvalidArgumentException('Desconto acima do limite configurado.');
        }

        if ($discount > $subtotal + $addition) {
            throw new InvalidArgumentException('O desconto não pode ser maior que o total da venda.');
        }

        $total = round(max($subtotal - $discount + $addition, 0.0), 2);

        if ($paymentMethod === 'conta_cliente') {
            $this->assertClientExists($empresaId, $clientId);

            if ($dueDate === '') {
                $dueDate = $this->defaultDueDate($settings);
            }

            if (!Validator::date($dueDate)) {
                throw new InvalidArgumentException('Vencimento da conta inválido.');
            }
        } elseif ($clientId > 0) {
            $this->assertClientExists($empresaId, $clientId);
        } elseif ($this->enabled($settings, 'require_customer_for_account', true) && $paymentMethod === 'conta_cliente') {
            throw new InvalidArgumentException('Informe o cliente para venda na conta.');
        }

        $mixedPayments = $this->normalizeMixedPayments($payload['mixed'] ?? [], $settings);
        if ($paymentMethod === 'misto') {
            $mixedTotal = round(array_sum($mixedPayments), 2);
            if (!$mixedPayments || abs($mixedTotal - $total) > 0.01) {
                throw new InvalidArgumentException('A composição do pagamento misto precisa fechar o total da venda.');
            }
            $received = $mixedTotal;
        }

        $change = null;
        if ($paymentMethod === 'dinheiro') {
            if ($received === null || $received < $total) {
                throw new InvalidArgumentException('Informe um valor recebido suficiente para pagamento em dinheiro.');
            }
            $change = round($received - $total, 2);
        }

        $db = $this->sales->connection();
        $db->beginTransaction();

        try {
            $saleId = $this->sales->create($empresaId, $usuarioId, [
                'cliente_id' => $clientId > 0 ? $clientId : null,
                'numero_venda' => $this->generateSaleNumber(),
                'status' => $paymentMethod === 'conta_cliente' ? 'em_aberto' : 'finalizada',
                'subtotal' => $subtotal,
                'desconto' => $discount,
                'acrescimo' => $addition,
                'total' => $total,
            ]);

            foreach ($saleItems as $item) {
                $product = $item['product'];
                $this->sales->addItem($saleId, [
                    'produto_id' => $product['id'],
                    'produto_nome' => $product['name'],
                    'lote' => $product['lot'],
                    'validade' => $product['expiry'],
                    'quantidade' => $item['quantity'],
                    'preco_unitario' => $product['price'],
                    'subtotal' => $item['subtotal'],
                ]);

                $this->decreaseStock($empresaId, (int)$product['id'], (float)$item['quantity'], $blockNegativeStock);
            }

            $this->payments->create($saleId, [
                'metodo' => $paymentMethod,
                'valor' => $total,
                'valor_recebido' => $received,
                'troco' => $change,
                'status' => $paymentMethod === 'conta_cliente' ? 'pendente' : 'pago',
            ]);

            if ($paymentMethod === 'conta_cliente') {
                $this->createClientAccountForSale($empresaId, $clientId, $saleId, $total, $dueDate);
            }

            $db->commit();

            return $this->sales->findById($empresaId, $saleId) ?? ['id' => $saleId];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public function cancel(int $empresaId, int $usuarioId, array $payload): void
    {
        $id = (int)($payload['id'] ?? 0);
        $reason = trim((string)($payload['reason'] ?? $payload['motivo'] ?? ''));
        $settings = $this->settingsWithDefaults($empresaId);

        if ($id <= 0) {
            throw new InvalidArgumentException('Informe a venda para cancelamento.');
        }

        if ($this->enabled($settings, 'require_cancellation_reason', true) && $reason === '') {
            throw new InvalidArgumentException('Informe o motivo do cancelamento.');
        }

        $sale = $this->sales->findById($empresaId, $id);

        if (!$sale) {
            throw new InvalidArgumentException('Venda não encontrada.');
        }

        if (($sale['status'] ?? '') === 'Cancelada') {
            throw new InvalidArgumentException('Venda já cancelada.');
        }

        $db = $this->sales->connection();
        $db->beginTransaction();

        try {
            $items = $this->sales->stockItemsForSale($empresaId, $id);
            $this->sales->cancel($empresaId, $id, $usuarioId, $reason);
            $this->markPaymentsReversedBySale($id);
            $this->cancelAccountsBySale($empresaId, $id);

            foreach ($items as $item) {
                $this->products->increaseStock($empresaId, (int)$item['produto_id'], (float)$item['quantidade']);
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    private function normalizeItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $key => $item) {
            if (is_array($item)) {
                $productId = (int)($item['productId'] ?? $item['produto_id'] ?? $item['id'] ?? $key);
                $quantity = (float)($item['qty'] ?? $item['quantity'] ?? $item['quantidade'] ?? 0);
            } else {
                $productId = (int)$key;
                $quantity = (float)$item;
            }

            if ($productId <= 0 || $quantity <= 0) {
                throw new InvalidArgumentException('Item de venda inválido.');
            }

            $normalized[$productId] = ($normalized[$productId] ?? 0.0) + $quantity;
        }

        return $normalized;
    }

    private function createClientAccountForSale(int $empresaId, int $clientId, int $saleId, float $total, string $dueDate): void
    {
        $stmt = $this->accounts->connection()->prepare(
            'INSERT INTO cliente_contas (empresa_id, cliente_id, venda_id, valor_original, valor_pago, saldo_aberto, vencimento, status)
             VALUES (:empresa_id, :cliente_id, :venda_id, :valor_original, 0, :saldo_aberto, :vencimento, \'em_aberto\')'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clientId,
            ':venda_id' => $saleId,
            ':valor_original' => $total,
            ':saldo_aberto' => $total,
            ':vencimento' => $dueDate,
        ]);
    }

    private function markPaymentsReversedBySale(int $saleId): void
    {
        $stmt = $this->payments->connection()->prepare(
            'UPDATE pagamentos
             SET status = \'estornado\'
             WHERE venda_id = :venda_id'
        );
        $stmt->execute([':venda_id' => $saleId]);
    }

    private function cancelAccountsBySale(int $empresaId, int $saleId): void
    {
        $stmt = $this->accounts->connection()->prepare(
            'UPDATE cliente_contas
             SET status = \'cancelado\',
                 saldo_aberto = 0
             WHERE empresa_id = :empresa_id AND venda_id = :venda_id'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':venda_id' => $saleId,
        ]);
    }

    private function normalizeMixedPayments(mixed $mixed, array $settings): array
    {
        if (!is_array($mixed)) {
            return [];
        }

        $payments = [];

        foreach ($mixed as $method => $amount) {
            $method = $this->mapPaymentMethod((string)$method);
            if ($method === 'misto' || $method === 'conta_cliente') {
                continue;
            }

            $value = $this->optionalMoneyValue($amount, 'Valor do pagamento misto') ?? 0.0;
            if ($value <= 0) {
                continue;
            }

            $this->assertPaymentEnabled($method, $settings);
            $payments[$method] = round(($payments[$method] ?? 0.0) + $value, 2);
        }

        return $payments;
    }

    private function settingsWithDefaults(int $empresaId): array
    {
        $settings = $this->settings->getConfiguracoes($empresaId);

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
            'require_cancellation_reason' => 1,
            'debt_due_days' => 30,
        ], $settings);
    }

    private function assertPaymentEnabled(string $method, array $settings): void
    {
        $key = [
            'pix' => 'payment_pix',
            'dinheiro' => 'payment_cash',
            'credito' => 'payment_credit',
            'debito' => 'payment_debit',
            'conta_cliente' => 'payment_account',
            'misto' => 'payment_mixed',
        ][$method] ?? null;

        if ($key === null || !$this->enabled($settings, $key, true)) {
            throw new InvalidArgumentException('Forma de pagamento não habilitada.');
        }
    }

    private function assertClientExists(int $empresaId, int $clientId): void
    {
        if ($clientId <= 0 || !$this->clients->findById($empresaId, $clientId)) {
            throw new InvalidArgumentException('Cliente não encontrado.');
        }
    }

    private function decreaseStock(int $empresaId, int $productId, float $quantity, bool $blockNegativeStock): void
    {
        $sql = 'UPDATE produtos
                SET quantidade = quantidade - :quantidade
                WHERE empresa_id = :empresa_id
                  AND id = :id';

        if ($blockNegativeStock) {
            $sql .= ' AND quantidade >= :quantidade';
        }

        $stmt = $this->products->connection()->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $productId,
            ':quantidade' => $quantity,
        ]);

        if ($blockNegativeStock && $stmt->rowCount() < 1) {
            throw new InvalidArgumentException('Estoque insuficiente para finalizar a venda.');
        }
    }

    private function moneyValue(mixed $value, string $label): float
    {
        $value = str_replace(',', '.', trim((string)$value));

        if ($value === '') {
            return 0.0;
        }

        if (!Validator::money($value)) {
            throw new InvalidArgumentException($label . ' inválido.');
        }

        return round((float)$value, 2);
    }

    private function optionalMoneyValue(mixed $value, string $label): ?float
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }

        return $this->moneyValue($value, $label);
    }

    private function enabled(array $settings, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $settings)) {
            return $default;
        }

        return ((string)$settings[$key]) === '1';
    }

    private function isExpired(string $date): bool
    {
        return $date !== '' && $date < date('Y-m-d');
    }

    private function defaultDueDate(array $settings): string
    {
        $days = max(0, min(365, (int)($settings['debt_due_days'] ?? 30)));

        return (new \DateTimeImmutable('today'))->modify('+' . $days . ' days')->format('Y-m-d');
    }

    private function generateSaleNumber(): string
    {
        return date('YmdHis') . random_int(100, 999);
    }

    private function mapPaymentMethod(string $method): string
    {
        return [
            'PIX' => 'pix',
            'pix' => 'pix',
            'Dinheiro' => 'dinheiro',
            'dinheiro' => 'dinheiro',
            'Crédito' => 'credito',
            'credito' => 'credito',
            'Cartão de crédito' => 'credito',
            'Débito' => 'debito',
            'debito' => 'debito',
            'Cartão de débito' => 'debito',
            'Conta do cliente' => 'conta_cliente',
            'conta_cliente' => 'conta_cliente',
            'Misto' => 'misto',
            'misto' => 'misto',
        ][$method] ?? 'pix';
    }
}
