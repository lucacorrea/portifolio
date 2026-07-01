<?php

declare(strict_types=1);

namespace App\Finance\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class CashManagementService
{
    private const FORMS = ['dinheiro', 'pix', 'cartao_debito', 'cartao_credito', 'transferencia', 'outro'];

    public function __construct(private readonly PDO $connection)
    {
    }

    public function registerEntry(string $originType, int $originId, string $description, string $form, string $value, int $userId, ?DateTimeImmutable $date = null): int
    {
        $amount = $this->money($value);
        if ($amount <= 0.0) {
            throw new InvalidArgumentException('Valor de caixa deve ser maior que zero.');
        }
        if (!in_array($form, self::FORMS, true)) {
            throw new InvalidArgumentException('Forma de pagamento inválida.');
        }

        $statement = $this->connection->prepare(
            'INSERT INTO caixa_movimentacoes
                (tipo, origem_tipo, origem_id, descricao, forma_pagamento, valor, data_movimento, usuario_id)
             VALUES
                ("entrada", :origin_type, :origin_id, :description, :form, :value, :date, :user_id)'
        );
        $statement->execute([
            'origin_type' => $originType,
            'origin_id' => $originId,
            'description' => $description,
            'form' => $form,
            'value' => number_format($amount, 2, '.', ''),
            'date' => ($date ?? new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @return array<int,array<string,mixed>> */
    public function listByDate(string $date): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $statement = $this->connection->prepare(
            'SELECT cm.*, u.nome AS usuario_nome
               FROM caixa_movimentacoes cm
               JOIN usuarios u ON u.id = cm.usuario_id
              WHERE DATE(cm.data_movimento) = :date
              ORDER BY cm.data_movimento DESC, cm.id DESC'
        );
        $statement->execute(['date' => $date]);
        return $statement->fetchAll();
    }

    private function money(string $value): float
    {
        $value = str_replace(' ', '', trim($value));
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Valor monetário inválido.');
        }
        return (float) $value;
    }
}
