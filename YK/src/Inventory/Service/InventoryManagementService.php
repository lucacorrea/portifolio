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
        if ($next < 0.0) {
            $this->validateNegativeStockAuthorization($authorizationId, $orderId, $productId, $qty, $current);
        } elseif ($authorizationId !== null) {
            throw new InvalidArgumentException('Autorização de estoque negativo informada sem necessidade.');
        }
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
        $movementId = (int) $this->connection->lastInsertId();

        if ($authorizationId !== null) {
            $this->connection->prepare(
                'UPDATE estoque_autorizacoes
                    SET utilizada_em = CURRENT_TIMESTAMP,
                        movimentacao_id = :movement_id
                  WHERE id = :authorization_id'
            )->execute([
                'movement_id' => $movementId,
                'authorization_id' => $authorizationId,
            ]);
        }
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

    private function validateNegativeStockAuthorization(?int $authorizationId, int $orderId, int $productId, float $quantity, float $currentStock): void
    {
        if ($authorizationId === null) {
            throw new InvalidArgumentException('Estoque insuficiente. Informe uma autorização válida para baixa negativa.');
        }

        $statement = $this->connection->prepare(
            'SELECT ea.id, ea.quantidade_solicitada, ea.saldo_disponivel, ea.utilizada_em,
                    pe.id AS permissao_id
               FROM estoque_autorizacoes ea
               JOIN usuarios u ON u.id = ea.autorizado_por
               JOIN perfil_permissoes pp ON pp.perfil_id = u.perfil_id
               JOIN permissoes pe ON pe.id = pp.permissao_id
              WHERE ea.id = :authorization_id
                AND ea.ordem_servico_id = :order_id
                AND ea.produto_id = :product_id
                AND pe.codigo = "estoque.autorizar_saldo_negativo"
              LIMIT 1
              FOR UPDATE'
        );
        $statement->execute([
            'authorization_id' => $authorizationId,
            'order_id' => $orderId,
            'product_id' => $productId,
        ]);
        $authorization = $statement->fetch();
        if ($authorization === false) {
            throw new InvalidArgumentException('Autorização de estoque negativo inválida para esta OS e produto.');
        }
        if ($authorization['utilizada_em'] !== null) {
            throw new InvalidArgumentException('Autorização de estoque negativo já utilizada.');
        }
        if ((float) $authorization['quantidade_solicitada'] < $quantity || (float) $authorization['saldo_disponivel'] > $currentStock) {
            throw new InvalidArgumentException('Autorização de estoque negativo não cobre a quantidade atual.');
        }
    }
}
