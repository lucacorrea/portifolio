<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PaymentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function connection(): PDO
    {
        return $this->db;
    }

    public function create(int $saleId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pagamentos (venda_id, metodo, valor, valor_recebido, troco, status)
             VALUES (:venda_id, :metodo, :valor, :valor_recebido, :troco, :status)'
        );
        $stmt->execute([
            ':venda_id' => $saleId,
            ':metodo' => $data['metodo'],
            ':valor' => $data['valor'],
            ':valor_recebido' => $data['valor_recebido'] ?? null,
            ':troco' => $data['troco'] ?? null,
            ':status' => $data['status'] ?? 'pago',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getBySale(int $saleId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, metodo, valor, valor_recebido, troco, status, criado_em
             FROM pagamentos
             WHERE venda_id = :venda_id
             ORDER BY id ASC'
        );
        $stmt->execute([':venda_id' => $saleId]);

        return $stmt->fetchAll();
    }

    public function markReversedBySale(int $saleId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE pagamentos
             SET status = "estornado"
             WHERE venda_id = :venda_id'
        );
        $stmt->execute([':venda_id' => $saleId]);
    }
}
