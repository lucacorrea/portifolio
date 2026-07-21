<?php

declare(strict_types=1);

namespace App\Finance\Service;

require_once __DIR__ . '/AccountsReceivableOrderPayments.php';

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class AccountsReceivableManagementService
{
    use AccountsReceivableOrderPayments;

    private const PAYMENT_FORMS = ['dinheiro', 'pix', 'cartao_debito', 'cartao_credito', 'transferencia', 'outro'];
    private const ELIGIBLE_PAYMENT_STATUSES = ['pendente', 'parcial', 'vencida'];

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
        $bucket = $this->filterValue($filters, 'bucket', ['', 'vencidos', 'hoje', 'semana', '15dias', 'sem_vencimento']);
        if ($bucket === 'vencidos') $where[] = "cr.vencimento_em < CURRENT_DATE AND cr.status IN ('pendente','parcial','vencida')";
        if ($bucket === 'hoje') $where[] = "cr.vencimento_em = CURRENT_DATE";
        if ($bucket === 'semana') $where[] = "cr.vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)";
        if ($bucket === '15dias') $where[] = "cr.vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 15 DAY)";
        if ($bucket === 'sem_vencimento') $where[] = 'cr.vencimento_em IS NULL';
        $status = $this->filterValue($filters, 'status', ['', 'pendente', 'parcial', 'vencida', 'paga', 'estornada', 'cancelada']);
        if ($status !== '') {
            $where[] = 'cr.status = :status';
            $params['status'] = $status;
        }
        $search = $this->filterSearch($filters['search'] ?? '');
        if ($search !== '') {
            $where[] = '(c.nome LIKE :search_client OR os.numero LIKE :search_order)';
            $searchPattern = '%' . $search . '%';
            $params['search_client'] = $searchPattern;
            $params['search_order'] = $searchPattern;
        }

        $sql = 'SELECT cr.*, os.numero AS os_numero, c.id AS cliente_id,
                       c.nome AS cliente_nome, c.telefone AS cliente_telefone
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

    /** @param int[] $orderIds @return array<int,array{id:int,status:string,valor_total:string,valor_recebido:string,saldo:string}> */
    public function balancesForOrders(array $orderIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $orderIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($ids === []) return [];

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = 'order_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $statement = $this->connection->prepare(
            'SELECT id, ordem_servico_id, status, valor_total, valor_recebido, saldo
               FROM contas_receber
              WHERE ordem_servico_id IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($params);
        $balances = [];
        foreach ($statement->fetchAll() as $row) {
            $balances[(int) $row['ordem_servico_id']] = [
                'id' => (int) $row['id'],
                'status' => (string) $row['status'],
                'valor_total' => (string) $row['valor_total'],
                'valor_recebido' => (string) $row['valor_recebido'],
                'saldo' => (string) $row['saldo'],
            ];
        }
        return $balances;
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

    public function registerPayment(int $accountId, string $value, string $form, ?string $notes, int $userId): int
    {
        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();

        try {
            $account = $this->lockAccount($accountId);
            if (!in_array($account['status'], self::ELIGIBLE_PAYMENT_STATUSES, true)) {
                throw new InvalidArgumentException('A situação da conta não permite novo pagamento.');
            }
            $amount = $this->moneyToCents($value);
            if ($amount <= 0 || $amount > $this->moneyToCents((string) $account['saldo'])) {
                throw new InvalidArgumentException('Valor de pagamento inválido para o saldo.');
            }
            $paymentId = $this->applyPaymentToLockedAccount($account, $amount, $form, $notes, $userId);

            if ($ownsTransaction) $this->connection->commit();
            return $paymentId;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<int,mixed> $accountIds
     * @return array{client_id:int,client_name:string,count:int,total:string,account_ids:array<int,int>}
     */
    public function registerBatchPayment(array $accountIds, string $form, ?string $notes, int $userId): array
    {
        $ids = $this->batchAccountIds($accountIds);
        $form = $this->paymentForm($form);
        $notes = $this->paymentNotes($notes);
        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuário inválido para registrar a baixa.');
        }

        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();

        try {
            $accounts = $this->lockAccountsForBatch($ids);
            if (count($accounts) !== count($ids)) {
                throw new InvalidArgumentException('Uma ou mais contas a receber não foram encontradas.');
            }

            $clientId = (int) $accounts[0]['cliente_id'];
            $clientName = (string) $accounts[0]['cliente_nome'];
            $totalPaid = 0;
            foreach ($accounts as $account) {
                if ((int) $account['cliente_id'] !== $clientId) {
                    throw new InvalidArgumentException('Selecione apenas contas do mesmo cliente.');
                }
                if (!in_array((string) $account['status'], self::ELIGIBLE_PAYMENT_STATUSES, true)) {
                    throw new InvalidArgumentException('Uma ou mais contas não permitem baixa.');
                }
                if ((string) $account['os_status'] !== 'finalizada' || $account['os_excluida_em'] !== null) {
                    throw new InvalidArgumentException('Todas as OS devem estar finalizadas e ativas para a baixa em lote.');
                }

                $balance = $this->moneyToCents((string) $account['saldo']);
                if ($balance <= 0) {
                    throw new InvalidArgumentException('Uma ou mais contas não possuem saldo para baixa.');
                }
                if ($totalPaid > PHP_INT_MAX - $balance) {
                    throw new InvalidArgumentException('Valor total da baixa excede o limite permitido.');
                }
                $totalPaid += $balance;
            }

            foreach ($accounts as $account) {
                $this->applyPaymentToLockedAccount(
                    $account,
                    $this->moneyToCents((string) $account['saldo']),
                    $form,
                    $notes,
                    $userId
                );
            }

            if ($ownsTransaction) $this->connection->commit();

            return [
                'client_id' => $clientId,
                'client_name' => $clientName,
                'count' => count($accounts),
                'total' => $this->centsToDecimal($totalPaid),
                'account_ids' => $ids,
            ];
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

    /** @param int[] $ids @return array<int,array<string,mixed>> */
    private function lockAccountsForBatch(array $ids): array
    {
        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $statement = $this->connection->prepare(
            'SELECT cr.*, os.status AS os_status, os.excluida_em AS os_excluida_em,
                    c.id AS cliente_id, c.nome AS cliente_nome
               FROM contas_receber cr
               JOIN ordens_servico os ON os.id = cr.ordem_servico_id
               JOIN clientes c ON c.id = os.cliente_id
              WHERE cr.id IN (' . implode(', ', $placeholders) . ')
              ORDER BY cr.id
              FOR UPDATE'
        );
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @param array<string,mixed> $account */
    private function applyPaymentToLockedAccount(
        array $account,
        int $amount,
        string $form,
        ?string $notes,
        int $userId,
        ?string $paymentToken = null,
        string $cashDescription = 'Recebimento de conta a receber'
    ): int
    {
        $form = $this->paymentForm($form);
        $notes = $this->paymentNotes($notes);
        $accountId = (int) $account['id'];
        $value = $this->centsToDecimal($amount);
        $cashId = $this->cash->registerEntry(
            'conta_receber_pagamento',
            $accountId,
            $cashDescription,
            $form,
            $value,
            $userId
        );
        $statement = $this->connection->prepare(
            'INSERT INTO ordem_servico_pagamentos
                (ordem_servico_id, valor, forma_pagamento, recebido_em, observacao, status,
                 registrado_por, caixa_movimentacao_id, payment_token)
             VALUES (:order_id, :value, :form, NOW(), :notes, "ativo", :user_id, :cash_id, :payment_token)'
        );
        $statement->execute([
            'order_id' => $account['ordem_servico_id'],
            'value' => $value,
            'form' => $form,
            'notes' => $notes,
            'user_id' => $userId,
            'cash_id' => $cashId,
            'payment_token' => $paymentToken,
        ]);
        $paymentId = (int) $this->connection->lastInsertId();

        $received = $this->moneyToCents((string) $account['valor_recebido']) + $amount;
        $total = $this->moneyToCents((string) $account['valor_total']);
        $balance = max(0, $total - $received);
        $status = $balance === 0 ? 'paga' : 'parcial';
        $this->connection->prepare(
            'UPDATE contas_receber SET valor_recebido = :received, saldo = :balance, status = :status WHERE id = :id'
        )->execute([
            'id' => $accountId,
            'received' => $this->centsToDecimal($received),
            'balance' => $this->centsToDecimal($balance),
            'status' => $status,
        ]);
        $this->event($accountId, $status === 'paga' ? 'quitacao' : 'pagamento', 'Pagamento registrado.', $value, $userId);
        return $paymentId;
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

    /** @param array<string,mixed> $filters @param string[] $allowed */
    private function filterValue(array $filters, string $key, array $allowed): string
    {
        $value = $filters[$key] ?? '';
        if (!is_string($value)) {
            throw new InvalidArgumentException('Filtro inválido.');
        }
        $value = trim($value);
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('Filtro inválido.');
        }
        return $value;
    }

    private function filterSearch(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Busca inválida.');
        }
        $value = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if (str_contains($value, "\0") || $length > 150) {
            throw new InvalidArgumentException('Busca inválida.');
        }
        return $value;
    }

    /** @param array<int,mixed> $values @return int[] */
    private function batchAccountIds(array $values): array
    {
        if (count($values) < 2 || count($values) > 100) {
            throw new InvalidArgumentException('Selecione de 2 a 100 contas para a baixa em lote.');
        }
        $ids = [];
        foreach ($values as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!is_int($id)) {
                throw new InvalidArgumentException('Conta inválida para a baixa em lote.');
            }
            $ids[] = $id;
        }
        if (count(array_unique($ids, SORT_NUMERIC)) !== count($ids)) {
            throw new InvalidArgumentException('Não repita contas na baixa em lote.');
        }
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    private function paymentForm(string $form): string
    {
        $form = trim($form);
        if (!in_array($form, self::PAYMENT_FORMS, true)) {
            throw new InvalidArgumentException('Forma de pagamento inválida.');
        }
        return $form;
    }

    private function paymentNotes(?string $notes): ?string
    {
        $notes = trim((string) ($notes ?? ''));
        if ($notes === '') return null;
        $length = function_exists('mb_strlen') ? mb_strlen($notes, 'UTF-8') : strlen($notes);
        if (str_contains($notes, "\0") || $length > 255) {
            throw new InvalidArgumentException('Observação de pagamento inválida.');
        }
        return $notes;
    }

    private function moneyToCents(string $value): int
    {
        $value = str_replace(' ', '', trim($value));
        if (str_contains($value, ',')) {
            $value = str_replace(',', '.', str_replace('.', '', $value));
        }
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Valor monetário inválido.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        if (strlen($whole) > 16) {
            throw new InvalidArgumentException('Valor monetário excede o limite permitido.');
        }
        $wholeValue = (int) $whole;
        if ($wholeValue > intdiv(PHP_INT_MAX - 99, 100)) {
            throw new InvalidArgumentException('Valor monetário excede o limite permitido.');
        }
        return ($wholeValue * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function centsToDecimal(int $value): string
    {
        return intdiv($value, 100) . '.' . str_pad((string) ($value % 100), 2, '0', STR_PAD_LEFT);
    }

    private function format(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
