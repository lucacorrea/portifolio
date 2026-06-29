<?php

declare(strict_types=1);

namespace App\Inventory\Service;

use InvalidArgumentException;
use PDO;

final class InventoryManagementService
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function consumeForOrder(int $orderId, int $productId, string $quantity, int $userId, ?int $authorizationId = null): void
    {
        $qty = $this->quantity($quantity);
        $statement = $this->connection->prepare('SELECT id, nome, estoque FROM produtos WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $productId]);
        $product = $statement->fetch();
        if ($product === false) throw new InvalidArgumentException('Produto da finalização não encontrado.');

        $current = (float) $product['estoque'];
        $next = $current - $qty;
        if ($next < 0.0 && $authorizationId === null) {
            throw new InvalidArgumentException('Estoque insuficiente para ' . $product['nome'] . '. Disponível: ' . number_format($current, 3, ',', '.') . ', utilizado: ' . number_format($qty, 3, ',', '.') . '.');
        }

        $this->connection->prepare('UPDATE produtos SET estoque = :stock WHERE id = :id')->execute([
            'id' => $productId,
            'stock' => number_format($next, 3, '.', ''),
        ]);
        $this->connection->prepare(
            'INSERT INTO estoque_movimentacoes
                (produto_id, ordem_servico_id, tipo, quantidade, saldo_anterior, saldo_posterior, autorizacao_id, usuario_id, observacao)
             VALUES
                (:product_id, :order_id, "saida_os", :quantity, :previous, :next, :authorization_id, :user_id, :notes)'
        )->execute([
            'product_id' => $productId,
            'order_id' => $orderId,
            'quantity' => number_format($qty, 3, '.', ''),
            'previous' => number_format($current, 3, '.', ''),
            'next' => number_format($next, 3, '.', ''),
            'authorization_id' => $authorizationId,
            'user_id' => $userId,
            'notes' => 'Baixa por finalização de OS.',
        ]);
    }

    public function createNegativeStockAuthorization(int $orderId, int $productId, string $requested, string $available, int $requestedBy, int $authorizedBy, string $reason): int
    {
        $req = $this->quantity($requested);
        $avail = $this->quantity($available, true);
        $excess = max(0.0, $req - $avail);
        if ($excess <= 0.0) {
            throw new InvalidArgumentException('Autorização de estoque negativo sem excedente.');
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 255 || str_contains($reason, "\0")) {
            throw new InvalidArgumentException('Informe justificativa válida para autorização.');
        }

        $statement = $this->connection->prepare(
            'INSERT INTO estoque_autorizacoes
                (ordem_servico_id, produto_id, quantidade_solicitada, saldo_disponivel, quantidade_excedente, solicitado_por, autorizado_por, motivo)
             VALUES
                (:order_id, :product_id, :requested, :available, :excess, :requested_by, :authorized_by, :reason)'
        );
        $statement->execute([
            'order_id' => $orderId,
            'product_id' => $productId,
            'requested' => number_format($req, 3, '.', ''),
            'available' => number_format($avail, 3, '.', ''),
            'excess' => number_format($excess, 3, '.', ''),
            'requested_by' => $requestedBy,
            'authorized_by' => $authorizedBy,
            'reason' => $reason,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function quantity(string $value, bool $allowZero = false): float
    {
        $value = str_replace(',', '.', trim($value));
        if (!preg_match('/^\d+(\.\d+)?$/', $value)) throw new InvalidArgumentException('Quantidade inválida.');
        $number = (float) $value;
        if ($number < 0.0 || (!$allowZero && $number <= 0.0)) throw new InvalidArgumentException('Quantidade inválida.');
        return $number;
    }
}
