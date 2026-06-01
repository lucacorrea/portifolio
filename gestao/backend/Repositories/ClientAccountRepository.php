<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ClientAccountRepository
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

    public function findOpenByClient(int $empresaId, int $clienteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, venda_id, valor_original, valor_pago, saldo_aberto,
                    DATE_FORMAT(vencimento, "%Y-%m-%d") AS vencimento, status, criado_em
             FROM cliente_contas
             WHERE empresa_id = :empresa_id
               AND cliente_id = :cliente_id
               AND status IN ("em_aberto", "parcial", "atrasado")
             ORDER BY vencimento ASC, id ASC'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
        ]);

        return array_map(static fn (array $account): array => [
            'id' => (int) $account['id'],
            'saleId' => $account['venda_id'] ? (int) $account['venda_id'] : null,
            'original' => (float) $account['valor_original'],
            'paid' => (float) $account['valor_pago'],
            'balance' => (float) $account['saldo_aberto'],
            'due' => $account['vencimento'],
            'status' => $account['status'],
            'createdAt' => $account['criado_em'],
        ], $stmt->fetchAll());
    }

    public function createForSale(int $empresaId, int $clienteId, int $vendaId, float $amount, string $dueDate): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cliente_contas (empresa_id, cliente_id, venda_id, valor_original, saldo_aberto, vencimento, status)
             VALUES (:empresa_id, :cliente_id, :venda_id, :valor_original, :saldo_aberto, :vencimento, "em_aberto")'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':cliente_id' => $clienteId,
            ':venda_id' => $vendaId,
            ':valor_original' => $amount,
            ':saldo_aberto' => $amount,
            ':vencimento' => $dueDate,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function registerPayment(int $empresaId, int $contaId, int $usuarioId, float $amount, string $method, ?string $newDueDate = null, string $note = ''): void
    {
        $stmt = $this->db->prepare(
            'SELECT id, saldo_aberto, valor_pago
             FROM cliente_contas
             WHERE empresa_id = :empresa_id AND id = :id
             LIMIT 1'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $contaId,
        ]);

        $account = $stmt->fetch();

        if (!$account) {
            throw new \RuntimeException('Conta não encontrada.');
        }

        $balance = max((float) $account['saldo_aberto'] - $amount, 0.0);
        $paid = (float) $account['valor_pago'] + $amount;
        $status = $balance <= 0 ? 'pago' : 'parcial';

        $stmt = $this->db->prepare(
            'INSERT INTO cliente_pagamentos (conta_id, usuario_id, valor, metodo, novo_vencimento, observacao)
             VALUES (:conta_id, :usuario_id, :valor, :metodo, :novo_vencimento, :observacao)'
        );
        $stmt->execute([
            ':conta_id' => $contaId,
            ':usuario_id' => $usuarioId,
            ':valor' => $amount,
            ':metodo' => $method,
            ':novo_vencimento' => $newDueDate,
            ':observacao' => $note ?: null,
        ]);

        $stmt = $this->db->prepare(
            'UPDATE cliente_contas
             SET valor_pago = :valor_pago,
                 saldo_aberto = :saldo_aberto,
                 vencimento = COALESCE(:novo_vencimento, vencimento),
                 status = :status
             WHERE empresa_id = :empresa_id AND id = :id'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':id' => $contaId,
            ':valor_pago' => $paid,
            ':saldo_aberto' => $balance,
            ':novo_vencimento' => $newDueDate,
            ':status' => $status,
        ]);
    }

    public function cancelBySale(int $empresaId, int $saleId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE cliente_contas
             SET status = "cancelado",
                 saldo_aberto = 0
             WHERE empresa_id = :empresa_id AND venda_id = :venda_id'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':venda_id' => $saleId,
        ]);
    }
}
