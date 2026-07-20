<?php

declare(strict_types=1);

namespace App\Finance\Service;

use DateTimeImmutable;
use InvalidArgumentException;

trait AccountsPayableInstallmentPlan
{
    /** @return string[] */
    public static function paymentMethods(): array
    {
        return ['dinheiro', 'pix', 'boleto', 'cartao_credito', 'cartao_debito', 'transferencia', 'cheque', 'outro'];
    }

    private function accountHasPaymentHistory(int $accountId): bool
    {
        $statement = $this->connection->prepare('SELECT EXISTS(SELECT 1 FROM contas_pagar_parcela_eventos evento JOIN contas_pagar_parcelas parcela ON parcela.id = evento.parcela_id WHERE parcela.conta_pagar_id = :id)');
        $statement->execute(['id' => $accountId]);
        return (int) $statement->fetchColumn() === 1;
    }

    /** @param array<string,mixed> $installment */
    private function registerInstallmentCashOutflow(array $installment, string $method, int $userId): int
    {
        $description = 'Pagamento ' . $installment['account_code'] . ' parcela ' . $installment['numero']
            . ' - ' . $installment['supplier_name'];
        $statement = $this->connection->prepare(
            'INSERT INTO caixa_movimentacoes
                (tipo, origem_tipo, origem_id, descricao, forma_pagamento, valor, data_movimento, usuario_id)
             VALUES ("saida", "conta_pagar_parcela", :origin_id, :description, :method, :amount, NOW(), :user_id)'
        );
        $statement->execute([
            'origin_id' => $installment['id'],
            'description' => function_exists('mb_substr') ? mb_substr($description, 0, 255, 'UTF-8') : substr($description, 0, 255),
            'method' => $method,
            'amount' => $installment['valor'],
            'user_id' => $userId,
        ]);
        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string,mixed> $installment */
    private function reverseInstallmentCashOutflow(array $installment, string $reason, int $userId): ?int
    {
        if ($installment['caixa_movimentacao_id'] === null) return null;
        $statement = $this->connection->prepare('SELECT * FROM caixa_movimentacoes WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $installment['caixa_movimentacao_id']]);
        $cash = $statement->fetch();
        if ($cash === false || (string) $cash['tipo'] !== 'saida') throw new InvalidArgumentException('Saída original do Caixa não encontrada.');
        $statement = $this->connection->prepare('SELECT id FROM caixa_movimentacoes WHERE estornado_de_id = :id LIMIT 1');
        $statement->execute(['id' => $cash['id']]);
        if ($statement->fetchColumn() !== false) throw new InvalidArgumentException('A saída desta parcela já foi estornada.');
        $description = 'Estorno: ' . $cash['descricao'] . '. Motivo: ' . $reason;
        $statement = $this->connection->prepare(
            'INSERT INTO caixa_movimentacoes
                (tipo, origem_tipo, origem_id, descricao, forma_pagamento, valor, data_movimento, usuario_id, estornado_de_id)
             VALUES ("estorno_saida", "conta_pagar_estorno", :origin_id, :description, :method, :amount, NOW(), :user_id, :source_id)'
        );
        $statement->execute([
            'origin_id' => $installment['id'],
            'description' => function_exists('mb_substr') ? mb_substr($description, 0, 255, 'UTF-8') : substr($description, 0, 255),
            'method' => $cash['forma_pagamento'], 'amount' => $cash['valor'],
            'user_id' => $userId, 'source_id' => $cash['id'],
        ]);
        return (int) $this->connection->lastInsertId();
    }

    /** @return array<int,array{numero:int,vencimento_em:string,valor:string}> */
    public static function installmentPlan(string $total, string $firstDueDate, int $count): array
    {
        if ($count < 1 || $count > 60 || preg_match('/^\d+\.\d{2}$/', $total) !== 1) {
            throw new InvalidArgumentException('Plano de parcelas inválido.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $firstDueDate);
        if ($date === false || $date->format('Y-m-d') !== $firstDueDate) {
            throw new InvalidArgumentException('Vencimento das parcelas inválido.');
        }
        [$whole, $fraction] = explode('.', $total);
        $cents = ((int) $whole * 100) + (int) $fraction;
        if ($cents < $count) {
            throw new InvalidArgumentException('O valor total deve permitir ao menos R$ 0,01 por parcela.');
        }
        $base = intdiv($cents, $count);
        $remainder = $cents % $count;
        $day = (int) $date->format('j');
        $monthBase = $date->modify('first day of this month');
        $plan = [];
        for ($index = 0; $index < $count; ++$index) {
            $month = $monthBase->modify('+' . $index . ' months');
            $due = $month->setDate(
                (int) $month->format('Y'),
                (int) $month->format('m'),
                min($day, (int) $month->format('t'))
            );
            $value = $base + ($index < $remainder ? 1 : 0);
            $plan[] = [
                'numero' => $index + 1,
                'vencimento_em' => $due->format('Y-m-d'),
                'valor' => intdiv($value, 100) . '.' . str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT),
            ];
        }
        return $plan;
    }
}
