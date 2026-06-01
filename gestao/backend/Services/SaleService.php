<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Repositories\ClientAccountRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SaleRepository;
use App\Repositories\SettingRepository;
use InvalidArgumentException;

final class SaleService
{
    private SaleRepository $sales;
    private ProductRepository $products;
    private PaymentRepository $payments;
    private ClientAccountRepository $accounts;
    private SettingRepository $settings;

    public function __construct(
        ?SaleRepository $sales = null,
        ?ProductRepository $products = null,
        ?PaymentRepository $payments = null,
        ?ClientAccountRepository $accounts = null,
        ?SettingRepository $settings = null
    ) {
        $this->sales = $sales ?? new SaleRepository();
        $this->products = $products ?? new ProductRepository();
        $this->payments = $payments ?? new PaymentRepository();
        $this->accounts = $accounts ?? new ClientAccountRepository();
        $this->settings = $settings ?? new SettingRepository();
    }

    public function list(int $empresaId): array
    {
        return $this->sales->findAll($empresaId);
    }

    public function details(int $empresaId, int $id): ?array
    {
        return $this->sales->findById($empresaId, $id);
    }

    public function finalize(int $empresaId, int $usuarioId, array $payload): array
    {
        $items = $payload['items'] ?? [];

        if (!is_array($items) || !$items) {
            throw new InvalidArgumentException('Informe ao menos um produto para a venda.');
        }

        $paymentMethod = $this->mapPaymentMethod((string)($payload['payment'] ?? $payload['metodo'] ?? 'pix'));
        $discount = (float)($payload['discount'] ?? $payload['desconto'] ?? 0);
        $addition = (float)($payload['addition'] ?? $payload['acrescimo'] ?? 0);
        $clientId = (int)($payload['clientId'] ?? $payload['cliente_id'] ?? 0);
        $dueDate = trim((string)($payload['dueDate'] ?? $payload['vencimento'] ?? ''));

        if (!Validator::money($discount) || !Validator::money($addition)) {
            throw new InvalidArgumentException('Valores da venda inválidos.');
        }

        if ($paymentMethod === 'conta_cliente') {
            if ($clientId <= 0) {
                throw new InvalidArgumentException('Informe o cliente para venda na conta.');
            }

            if (!Validator::date($dueDate) || $dueDate === '') {
                throw new InvalidArgumentException('Informe o vencimento da conta.');
            }
        }

        $settings = $this->settings->getAll($empresaId);
        $blockExpired = ((string)($settings['bloquear_produto_vencido'] ?? '1')) === '1';
        $blockNegativeStock = ((string)($settings['bloquear_estoque_negativo'] ?? '1')) === '1';
        $saleItems = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $productId = (int)($item['productId'] ?? $item['produto_id'] ?? 0);
            $quantity = (float)($item['qty'] ?? $item['quantidade'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                throw new InvalidArgumentException('Item de venda inválido.');
            }

            $product = $this->products->findById($empresaId, $productId);

            if (!$product) {
                throw new InvalidArgumentException('Produto não encontrado.');
            }

            if ($blockExpired && $product['expiry'] !== '' && $product['expiry'] < date('Y-m-d')) {
                throw new InvalidArgumentException('Produto vencido bloqueado pelas configurações.');
            }

            if ($blockNegativeStock && $quantity > (float)$product['stock']) {
                throw new InvalidArgumentException('Estoque insuficiente para finalizar a venda.');
            }

            $lineTotal = $quantity * (float)$product['price'];
            $subtotal += $lineTotal;
            $saleItems[] = [
                'product' => $product,
                'quantity' => $quantity,
                'subtotal' => $lineTotal,
            ];
        }

        $total = max($subtotal - $discount + $addition, 0.0);
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
                $this->products->decreaseStock($empresaId, (int)$product['id'], (float)$item['quantity']);
            }

            $this->payments->create($saleId, [
                'metodo' => $paymentMethod,
                'valor' => $total,
                'status' => $paymentMethod === 'conta_cliente' ? 'pendente' : 'pago',
            ]);

            if ($paymentMethod === 'conta_cliente') {
                $this->accounts->createForSale($empresaId, $clientId, $saleId, $total, $dueDate);
            }

            $db->commit();

            return $this->sales->findById($empresaId, $saleId) ?? [];
        } catch (\Throwable $e) {
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

        if ($id <= 0 || $reason === '') {
            throw new InvalidArgumentException('Informe a venda e o motivo do cancelamento.');
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
            $this->payments->markReversedBySale($id);
            $this->accounts->cancelBySale($empresaId, $id);

            foreach ($items as $item) {
                $this->products->increaseStock($empresaId, (int)$item['produto_id'], (float)$item['quantidade']);
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
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
            'Crédito' => 'credito',
            'credito' => 'credito',
            'Débito' => 'debito',
            'debito' => 'debito',
            'Dinheiro' => 'dinheiro',
            'dinheiro' => 'dinheiro',
            'Conta do cliente' => 'conta_cliente',
            'conta_cliente' => 'conta_cliente',
            'Misto' => 'misto',
            'misto' => 'misto',
        ][$method] ?? 'pix';
    }
}
