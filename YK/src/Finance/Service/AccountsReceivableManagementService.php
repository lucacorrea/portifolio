<?php

declare(strict_types=1);

namespace App\Finance\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class AccountsReceivableManagementService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly CashManagementService $cash
    ) {
    }

    /** @return array{total:string,overdue:string,today:string,week:string,next15:string,received:string} */
    public function indicators(): array
    {
        $statement = $this->connection->query(
            "SELECT
                SUM(CASE WHEN status IN ('pendente','parcial','vencida') THEN saldo ELSE 0 END) AS total,
                SUM(CASE WHEN status IN ('pendente','parcial','vencida') AND vencimento_em < CURRENT_DATE THEN saldo ELSE 0 END) AS overdue,
                SUM(CASE WHEN status IN ('pendente','parcial','vencida') AND vencimento_em = CURRENT_DATE THEN saldo ELSE 0 END) AS today,
                SUM(CASE WHEN status IN ('pendente','parcial','vencida') AND vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY) THEN saldo ELSE 0 END) AS week,
                SUM(CASE WHEN status IN ('pendente','parcial','vencida') AND vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 15 DAY) THEN saldo ELSE 0 END) AS next15
             FROM contas_receber"
        );
        $row = $statement->fetch() ?: [];
        $received = $this->connection->query(
            "SELECT SUM(valor) FROM ordem_servico_pagamentos WHERE status = 'ativo' AND DATE(recebido_em) = CURRENT_DATE"
        )->fetchColumn();

        return [
            'total' => $this->format($row['total'] ?? 0),
            'overdue' => $this->format($row['overdue'] ?? 0),
            'today' => $this->format($row['today'] ?? 0),
            'week' => $this->format($row['week'] ?? 0),
            'next15' => $this->format($row['next15'] ?? 0),
            'received' => $this->format($received ?: 0),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function listAccounts(array $filters = []): array
    {
        $where = [];
        $params = [];
        $bucket = (string) ($filters['bucket'] ?? '');
        if ($bucket === 'vencidos') $where[] = "cr.vencimento_em < CURRENT_DATE AND cr.status IN ('pendente','parcial','vencida')";
        if ($bucket === 'hoje') $where[] = "cr.vencimento_em = CURRENT_DATE";
        if ($bucket === 'semana') $where[] = "cr.vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)";
        if ($bucket === '15dias') $where[] = "cr.vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 15 DAY)";
        if ($bucket === 'sem_vencimento') $where[] = 'cr.vencimento_em IS NULL';
        if (trim((string) ($filters['status'] ?? '')) !== '') {
            $where[] = 'cr.status = :status';
            $params['status'] = trim((string) $filters['status']);
        }
        if (trim((string) ($filters['search'] ?? '')) !== '') {
            $where[] = '(c.nome LIKE :search OR os.numero LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        $sql = 'SELECT cr.*, os.numero AS os_numero, c.nome AS cliente_nome, c.telefone AS cliente_telefone
                  FROM contas_receber cr
                  JOIN ordens_servico os ON os.id = cr.ordem_servico_id
                  JOIN clientes c ON c.id = os.cliente_id';
        if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY
                    CASE
                        WHEN cr.vencimento_em < CURRENT_DATE AND cr.status IN ('pendente','parcial','vencida') THEN 1
                        WHEN cr.vencimento_em = CURRENT_DATE THEN 2
                        WHEN cr.vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY) THEN 3
                        WHEN cr.vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 15 DAY) THEN 4
                        WHEN cr.vencimento_em IS NULL THEN 5
                        ELSE 6
                    END,
                    cr.vencimento_em ASC,
                    cr.id DESC";

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function upsertForOrder(int $orderId, string $total, string $received, ?string $dueDate, ?string $reminderDate, ?string $notes, int $userId): ?int
    {
        $totalValue = $this->money($total);
        $receivedValue = $this->money($received);
        $balance = max(0.0, $totalValue - $receivedValue);
        if ($receivedValue > $totalValue) {
            throw new InvalidArgumentException('Valor recebido maior que o total da OS.');
        }

        $status = $balance <= 0.0 ? 'paga' : ($receivedValue > 0.0 ? 'parcial' : 'pendente');
        if ($balance > 0.0 && $dueDate !== null && $dueDate !== '' && $dueDate < date('Y-m-d')) {
            $status = 'vencida';
        }

        $statement = $this->connection->prepare(
            'INSERT INTO contas_receber
                (ordem_servico_id, valor_total, valor_recebido, saldo, vencimento_em, proximo_lembrete_em, status, observacao, criado_por)
             VALUES
                (:order_id, :total, :received, :balance, :due_date, :reminder_date, :status, :notes, :user_id)
             ON DUPLICATE KEY UPDATE
                valor_total = VALUES(valor_total),
                valor_recebido = VALUES(valor_recebido),
                saldo = VALUES(saldo),
                vencimento_em = VALUES(vencimento_em),
                proximo_lembrete_em = VALUES(proximo_lembrete_em),
                status = VALUES(status),
                observacao = VALUES(observacao)'
        );
        $statement->execute([
            'order_id' => $orderId,
            'total' => number_format($totalValue, 2, '.', ''),
            'received' => number_format($receivedValue, 2, '.', ''),
            'balance' => number_format($balance, 2, '.', ''),
            'due_date' => $dueDate ?: null,
            'reminder_date' => $reminderDate ?: null,
            'status' => $status,
            'notes' => $notes,
            'user_id' => $userId,
        ]);

        $id = (int) ($this->connection->lastInsertId() ?: $this->findIdByOrder($orderId));
        $this->event(
            $id,
            $status === 'paga' ? 'quitacao' : 'criacao',
            $status === 'paga'
                ? 'Conta a receber gerada como paga pela finalização da OS.'
                : 'Conta a receber gerada pela finalização da OS.',
            number_format($balance, 2, '.', ''),
            $userId
        );
        return $id;
    }

    public function registerPayment(int $accountId, string $value, string $form, ?string $notes, int $userId): void
    {
        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();

        try {
            $account = $this->lockAccount($accountId);
            if (!in_array($account['status'], ['pendente', 'parcial', 'vencida'], true)) {
                throw new InvalidArgumentException('A situação da conta não permite novo pagamento.');
            }
            $amount = $this->money($value);
            if ($amount <= 0.0 || $amount > (float) $account['saldo']) {
                throw new InvalidArgumentException('Valor de pagamento inválido para o saldo.');
            }

            $cashId = $this->cash->registerEntry('conta_receber_pagamento', $accountId, 'Recebimento de conta a receber', $form, number_format($amount, 2, '.', ''), $userId);
            $statement = $this->connection->prepare(
                'INSERT INTO ordem_servico_pagamentos
                    (ordem_servico_id, valor, forma_pagamento, recebido_em, observacao, status, registrado_por, caixa_movimentacao_id)
                 VALUES (:order_id, :value, :form, NOW(), :notes, "ativo", :user_id, :cash_id)'
            );
            $statement->execute([
                'order_id' => $account['ordem_servico_id'],
                'value' => number_format($amount, 2, '.', ''),
                'form' => $form,
                'notes' => $notes,
                'user_id' => $userId,
                'cash_id' => $cashId,
            ]);

            $received = (float) $account['valor_recebido'] + $amount;
            $balance = max(0.0, (float) $account['valor_total'] - $received);
            $status = $balance <= 0.0 ? 'paga' : 'parcial';
            $this->connection->prepare(
                'UPDATE contas_receber SET valor_recebido = :received, saldo = :balance, status = :status WHERE id = :id'
            )->execute([
                'id' => $accountId,
                'received' => number_format($received, 2, '.', ''),
                'balance' => number_format($balance, 2, '.', ''),
                'status' => $status,
            ]);
            $this->event($accountId, $status === 'paga' ? 'quitacao' : 'pagamento', 'Pagamento registrado.', number_format($amount, 2, '.', ''), $userId);

            if ($ownsTransaction) $this->connection->commit();
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    private function lockAccount(int $id): array
    {
        $statement = $this->connection->prepare('SELECT * FROM contas_receber WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        if ($row === false) throw new InvalidArgumentException('Conta a receber não encontrada.');
        return $row;
    }

    private function findIdByOrder(int $orderId): int
    {
        $statement = $this->connection->prepare('SELECT id FROM contas_receber WHERE ordem_servico_id = :id');
        $statement->execute(['id' => $orderId]);
        return (int) $statement->fetchColumn();
    }

    private function event(int $accountId, string $type, string $description, ?string $value, int $userId): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO contas_receber_eventos (conta_receber_id, tipo, descricao, valor, data_evento, usuario_id)
             VALUES (:account_id, :type, :description, :value, NOW(), :user_id)'
        );
        $statement->execute(['account_id' => $accountId, 'type' => $type, 'description' => $description, 'value' => $value, 'user_id' => $userId]);
    }

    private function money(string $value): float
    {
        $value = str_replace(' ', '', trim($value));
        if (str_contains($value, ',')) $value = str_replace(',', '.', str_replace('.', '', $value));
        if (!preg_match('/^\d+(\.\d+)?$/', $value)) throw new InvalidArgumentException('Valor monetário inválido.');
        return (float) $value;
    }

    private function format(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
