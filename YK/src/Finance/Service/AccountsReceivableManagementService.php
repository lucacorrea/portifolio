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

    private const PAYMENT_FORMS = ['dinheiro', 'pix', 'boleto', 'cartao_debito', 'cartao_credito', 'transferencia', 'outro'];
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

    /**
     * Edita os dados comerciais da conta a receber sem reescrever o histórico de pagamentos.
     * Valor recebido, saldo e situação são derivados dos pagamentos ativos e recalculados automaticamente.
     *
     * @return array{id:int,valor_total:string,valor_recebido:string,saldo:string,status:string}
     */
    public function editAccount(
        int $accountId,
        string $total,
        ?string $dueDate,
        ?string $reminderDate,
        ?string $notes,
        int $userId
    ): array {
        if ($accountId <= 0) {
            throw new InvalidArgumentException('Conta a receber inválida.');
        }
        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuário inválido.');
        }

        $totalCents = $this->moneyToCents($total);
        if ($totalCents <= 0) {
            throw new InvalidArgumentException('O valor total deve ser maior que zero.');
        }
        $dueDate = $this->accountDate($dueDate, 'Data de vencimento inválida.');
        $reminderDate = $this->accountDate($reminderDate, 'Data do próximo lembrete inválida.');
        $notes = $this->accountNotes($notes);

        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();

        try {
            $account = $this->lockAccount($accountId);
            if (in_array((string) $account['status'], ['cancelada', 'estornada'], true)) {
                throw new InvalidArgumentException('Esta conta não pode mais ser editada.');
            }

            $receivedCents = $this->moneyToCents((string) $account['valor_recebido']);
            if ($totalCents < $receivedCents) {
                throw new InvalidArgumentException(
                    'O valor total não pode ser menor que o valor já recebido ('
                    . $this->centsToDecimal($receivedCents) . '). Edite ou estorne os pagamentos primeiro.'
                );
            }

            $oldTotalCents = $this->moneyToCents((string) $account['valor_total']);
            $oldDueDate = $account['vencimento_em'] === null ? null : (string) $account['vencimento_em'];
            $oldReminderDate = $account['proximo_lembrete_em'] === null ? null : (string) $account['proximo_lembrete_em'];
            $oldNotes = $account['observacao'] === null ? null : (string) $account['observacao'];

            $balanceCents = max(0, $totalCents - $receivedCents);
            $status = $this->accountStatusAfterCorrection($receivedCents, $balanceCents, $dueDate);

            $this->connection->prepare(
                'UPDATE contas_receber
                    SET valor_total = :total, saldo = :balance, vencimento_em = :due_date,
                        proximo_lembrete_em = :reminder_date, status = :status, observacao = :notes
                  WHERE id = :id'
            )->execute([
                'id' => $accountId,
                'total' => $this->centsToDecimal($totalCents),
                'balance' => $this->centsToDecimal($balanceCents),
                'due_date' => $dueDate,
                'reminder_date' => $reminderDate,
                'status' => $status,
                'notes' => $notes,
            ]);

            if ($oldTotalCents !== $totalCents) {
                $this->event(
                    $accountId,
                    'negociacao',
                    'Valor total alterado de ' . $this->centsToDecimal($oldTotalCents)
                    . ' para ' . $this->centsToDecimal($totalCents) . '.',
                    $this->centsToDecimal($totalCents),
                    $userId
                );
            }
            if ($oldDueDate !== $dueDate) {
                $this->event(
                    $accountId,
                    'alteracao_vencimento',
                    'Vencimento alterado de ' . ($oldDueDate ?? 'sem vencimento')
                    . ' para ' . ($dueDate ?? 'sem vencimento') . '.',
                    null,
                    $userId
                );
            }
            if ($oldReminderDate !== $reminderDate) {
                $this->event(
                    $accountId,
                    'lembrete',
                    'Próximo lembrete alterado de ' . ($oldReminderDate ?? 'sem lembrete')
                    . ' para ' . ($reminderDate ?? 'sem lembrete') . '.',
                    null,
                    $userId
                );
            }
            if ($oldNotes !== $notes) {
                $this->event(
                    $accountId,
                    'observacao',
                    'Observação da conta atualizada.',
                    null,
                    $userId
                );
            }

            if ($ownsTransaction) $this->connection->commit();

            return [
                'id' => $accountId,
                'valor_total' => $this->centsToDecimal($totalCents),
                'valor_recebido' => $this->centsToDecimal($receivedCents),
                'saldo' => $this->centsToDecimal($balanceCents),
                'status' => $status,
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function registerPayment(
        int $accountId,
        string $value,
        string $form,
        string $paymentDate,
        ?string $notes,
        int $userId
    ): int {
        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();

        try {
            $account = $this->lockAccount($accountId);
            if (!in_array($account['status'], self::ELIGIBLE_PAYMENT_STATUSES, true)) {
                throw new InvalidArgumentException('A situação da conta não permite novo pagamento.');
            }
            $amount = $this->moneyToCents($value);
            $receivedAt = $this->paymentDate($paymentDate);
            if ($amount <= 0 || $amount > $this->moneyToCents((string) $account['saldo'])) {
                throw new InvalidArgumentException('Valor de pagamento inválido para o saldo.');
            }
            $paymentId = $this->applyPaymentToLockedAccount($account, $amount, $form, $notes, $receivedAt, $userId);

            if ($ownsTransaction) $this->connection->commit();
            return $paymentId;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    /** @return array{payment_id:int,financial_correction:bool,receipt_cancelled:bool} */
    public function editPayment(
        int $paymentId,
        string $value,
        string $form,
        string $paymentDate,
        ?string $notes,
        int $userId
    ): array {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuário inválido para editar o pagamento.');
        }

        $amount = $this->moneyToCents($value);
        $form = $this->paymentForm($form);
        $receivedAt = $this->paymentDate($paymentDate);
        $notes = $this->paymentNotes($notes);

        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();

        try {
            $payment = $this->lockPaymentForCorrection($paymentId);
            $this->assertPaymentCanBeCorrected($payment);

            $oldAmount = $this->moneyToCents((string) $payment['payment_value']);
            $maxAllowed = $this->moneyToCents((string) $payment['account_balance']) + $oldAmount;
            if ($amount <= 0 || $amount > $maxAllowed) {
                throw new InvalidArgumentException('Valor corrigido inválido para o saldo da conta.');
            }

            $sameFinancialData = $amount === $oldAmount
                && $form === (string) $payment['payment_form']
                && $receivedAt->format('Y-m-d') === substr((string) $payment['received_at'], 0, 10);
            $sameNotes = $notes === $this->paymentNotes(
                isset($payment['payment_notes']) ? (string) $payment['payment_notes'] : null
            );

            if ($sameFinancialData) {
                if (!$sameNotes) {
                    $statement = $this->connection->prepare(
                        "UPDATE ordem_servico_pagamentos
                            SET observacao = :notes
                          WHERE id = :id AND status = 'ativo'"
                    );
                    $statement->execute(['id' => $paymentId, 'notes' => $notes]);
                    $this->event(
                        (int) $payment['account_id'],
                        'observacao',
                        'Observação do pagamento #' . $paymentId . ' atualizada.',
                        $this->centsToDecimal($oldAmount),
                        $userId
                    );
                }

                if ($ownsTransaction) $this->connection->commit();
                return [
                    'payment_id' => $paymentId,
                    'financial_correction' => false,
                    'receipt_cancelled' => false,
                ];
            }

            $this->assertNoBlockingFiscalDocument($paymentId);
            $reason = 'Pagamento #' . $paymentId . ' substituído por correção manual.';
            $receiptCancelled = $this->cancelPaymentReceipt($paymentId, $reason, $userId);
            $account = $this->reverseLockedPayment($payment, $reason, $userId);

            $newPaymentId = $this->applyPaymentToLockedAccount(
                $account,
                $amount,
                $form,
                $notes,
                $receivedAt,
                $userId,
                max(1, (int) ($payment['installment_count'] ?? 1)),
                null,
                'Correção de recebimento de conta a receber'
            );

            if ($ownsTransaction) $this->connection->commit();
            return [
                'payment_id' => $newPaymentId,
                'financial_correction' => true,
                'receipt_cancelled' => $receiptCancelled,
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    /** @return array{receipt_cancelled:bool,account_status:string} */
    public function reversePayment(int $paymentId, string $reason, int $userId): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Usuário inválido para excluir o pagamento.');
        }
        $reason = trim($reason);
        $length = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
        if ($reason === '' || $length > 255 || str_contains($reason, "\0")) {
            throw new InvalidArgumentException('Informe um motivo válido para excluir o pagamento.');
        }

        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();

        try {
            $payment = $this->lockPaymentForCorrection($paymentId);
            $this->assertPaymentCanBeCorrected($payment);
            $this->assertNoBlockingFiscalDocument($paymentId);
            $receiptCancelled = $this->cancelPaymentReceipt($paymentId, $reason, $userId);
            $account = $this->reverseLockedPayment($payment, $reason, $userId);

            if ($ownsTransaction) $this->connection->commit();
            return [
                'receipt_cancelled' => $receiptCancelled,
                'account_status' => (string) $account['status'],
            ];
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<int,mixed> $accountIds
     * @return array{client_id:int,client_name:string,count:int,total:string,account_ids:array<int,int>}
     */
    public function registerBatchPayment(
        array $accountIds,
        string $form,
        string $paymentDate,
        ?string $notes,
        int $userId
    ): array {
        $ids = $this->batchAccountIds($accountIds);
        $form = $this->paymentForm($form);
        $receivedAt = $this->paymentDate($paymentDate);
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
                    $receivedAt,
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

    /** @return array<string,mixed> */
    private function lockPaymentForCorrection(int $paymentId): array
    {
        if ($paymentId <= 0) {
            throw new InvalidArgumentException('Pagamento inválido.');
        }

        $statement = $this->connection->prepare(
            "SELECT
                payment.id AS payment_id,
                payment.ordem_servico_id AS order_id,
                payment.valor AS payment_value,
                payment.forma_pagamento AS payment_form,
                payment.quantidade_parcelas AS installment_count,
                payment.recebido_em AS received_at,
                payment.observacao AS payment_notes,
                payment.status AS payment_status,
                payment.caixa_movimentacao_id AS cash_movement_id,
                account.id AS account_id,
                account.valor_total AS account_total,
                account.valor_recebido AS account_received,
                account.saldo AS account_balance,
                account.vencimento_em AS due_date,
                account.status AS account_status,
                service_order.numero AS order_number,
                service_order.status AS order_status,
                service_order.excluida_em AS order_deleted_at
             FROM ordem_servico_pagamentos payment
             JOIN contas_receber account ON account.ordem_servico_id = payment.ordem_servico_id
             JOIN ordens_servico service_order ON service_order.id = payment.ordem_servico_id
             WHERE payment.id = :id
             FOR UPDATE"
        );
        $statement->execute(['id' => $paymentId]);
        $payment = $statement->fetch();
        if ($payment === false) {
            throw new InvalidArgumentException('Pagamento não encontrado.');
        }
        return $payment;
    }

    /** @param array<string,mixed> $payment */
    private function assertPaymentCanBeCorrected(array $payment): void
    {
        if ((string) $payment['payment_status'] !== 'ativo') {
            throw new InvalidArgumentException('Este pagamento já foi excluído/estornado.');
        }
        if ((string) $payment['order_status'] !== 'finalizada' || $payment['order_deleted_at'] !== null) {
            throw new InvalidArgumentException('Somente pagamentos de OS finalizada e ativa podem ser corrigidos.');
        }
        if (in_array((string) $payment['account_status'], ['estornada', 'cancelada'], true)) {
            throw new InvalidArgumentException('A conta a receber não permite correção de pagamento.');
        }
    }

    private function assertNoBlockingFiscalDocument(int $paymentId): void
    {
        $statement = $this->connection->prepare(
            "SELECT id, processamento_status
               FROM documentos_fiscais
              WHERE pagamento_id = :payment_id
                AND processamento_status IN ('preparado','processando','pendente_reconsulta','autorizado')
              LIMIT 1
              FOR UPDATE"
        );
        $statement->execute(['payment_id' => $paymentId]);
        $document = $statement->fetch();
        if ($document !== false) {
            throw new InvalidArgumentException(
                'Este pagamento possui documento fiscal em processamento ou autorizado. Regularize/cancele o documento fiscal antes de editar ou excluir o pagamento.'
            );
        }
    }

    private function cancelPaymentReceipt(int $paymentId, string $reason, int $userId): bool
    {
        $statement = $this->connection->prepare(
            "UPDATE recibos
                SET status = 'cancelado', cancelado_por = :user_id,
                    cancelado_em = CURRENT_TIMESTAMP, motivo_cancelamento = :reason
              WHERE pagamento_id = :payment_id AND status = 'emitido'"
        );
        $statement->execute([
            'payment_id' => $paymentId,
            'user_id' => $userId,
            'reason' => $reason,
        ]);
        return $statement->rowCount() > 0;
    }

    /** @param array<string,mixed> $payment @return array<string,mixed> */
    private function reverseLockedPayment(array $payment, string $reason, int $userId): array
    {
        $paymentId = (int) $payment['payment_id'];
        $accountId = (int) $payment['account_id'];
        $oldAmount = $this->moneyToCents((string) $payment['payment_value']);

        if ($payment['cash_movement_id'] !== null) {
            $this->cash->reverseMovement(
                (int) $payment['cash_movement_id'],
                'conta_receber_estorno',
                $accountId,
                $this->limitText('Estorno do pagamento #' . $paymentId . ': ' . $reason, 255),
                $userId
            );
        }

        $statement = $this->connection->prepare(
            "UPDATE ordem_servico_pagamentos
                SET status = 'estornado', estornado_em = CURRENT_TIMESTAMP,
                    estornado_por = :user_id, motivo_estorno = :reason
              WHERE id = :id AND status = 'ativo'"
        );
        $statement->execute([
            'id' => $paymentId,
            'user_id' => $userId,
            'reason' => $reason,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('O pagamento não está mais disponível para correção.');
        }

        $received = max(0, $this->moneyToCents((string) $payment['account_received']) - $oldAmount);
        $total = $this->moneyToCents((string) $payment['account_total']);
        $balance = max(0, $total - $received);
        $status = $this->accountStatusAfterCorrection($received, $balance, $payment['due_date'] ?? null);

        $this->connection->prepare(
            'UPDATE contas_receber
                SET valor_recebido = :received, saldo = :balance, status = :status
              WHERE id = :id'
        )->execute([
            'id' => $accountId,
            'received' => $this->centsToDecimal($received),
            'balance' => $this->centsToDecimal($balance),
            'status' => $status,
        ]);

        $this->event(
            $accountId,
            'estorno',
            'Pagamento #' . $paymentId . ' excluído/estornado. Motivo: ' . $reason,
            $this->centsToDecimal($oldAmount),
            $userId
        );

        return [
            'id' => $accountId,
            'ordem_servico_id' => (int) $payment['order_id'],
            'valor_total' => $this->centsToDecimal($total),
            'valor_recebido' => $this->centsToDecimal($received),
            'saldo' => $this->centsToDecimal($balance),
            'status' => $status,
        ];
    }

    private function accountStatusAfterCorrection(int $received, int $balance, mixed $dueDate): string
    {
        if ($balance <= 0) return 'paga';
        $due = trim((string) ($dueDate ?? ''));
        if ($due !== '' && $due < date('Y-m-d')) return 'vencida';
        return $received > 0 ? 'parcial' : 'pendente';
    }

    private function accountDate(?string $value, string $message): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException($message);
        }
        return $value;
    }

    private function accountNotes(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return null;
        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
        if ($length > 2000 || str_contains($value, "\0")) {
            throw new InvalidArgumentException('A observação deve ter no máximo 2.000 caracteres.');
        }
        return $value;
    }

    private function limitText(string $value, int $max): string
    {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > $max ? mb_substr($value, 0, $max, 'UTF-8') : $value;
        }
        return strlen($value) > $max ? substr($value, 0, $max) : $value;
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
        DateTimeImmutable $receivedAt,
        int $userId,
        int $installmentCount = 1,
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
            $userId,
            $receivedAt
        );
        $statement = $this->connection->prepare(
            'INSERT INTO ordem_servico_pagamentos
                (ordem_servico_id, valor, forma_pagamento, quantidade_parcelas, recebido_em, observacao, status,
                 registrado_por, caixa_movimentacao_id, payment_token)
             VALUES (:order_id, :value, :form, :installment_count, :received_at, :notes, :status, :user_id, :cash_id, :payment_token)'
        );
        $statement->execute([
            'order_id' => $account['ordem_servico_id'],
            'value' => $value,
            'form' => $form,
            'installment_count' => $installmentCount,
            'received_at' => $receivedAt->format('Y-m-d H:i:s'),
            'notes' => $notes,
            'status' => 'ativo',
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

    private function paymentDate(string $value): DateTimeImmutable
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException('Informe a data do pagamento.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException('Data do pagamento inválida.');
        }

        if ($date > new DateTimeImmutable('today')) {
            throw new InvalidArgumentException('A data do pagamento não pode ser futura.');
        }

        return $date;
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
