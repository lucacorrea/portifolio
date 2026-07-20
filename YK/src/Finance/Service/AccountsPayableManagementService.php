<?php

declare(strict_types=1);

namespace App\Finance\Service;

require_once __DIR__ . '/AccountsPayableInstallmentPlan.php';

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

final class AccountsPayableManagementService
{
    use AccountsPayableInstallmentPlan;
    public function __construct(private readonly PDO $connection, private readonly CashManagementService $cash)
    {
    }

    /** @return array{open:string,overdue:string,today:string,week:string} */
    public function indicators(): array
    {
        $row = $this->connection->query(
            "SELECT
                COALESCE(SUM(CASE WHEN parcela.status = 'pendente' THEN parcela.valor ELSE 0 END), 0) AS open,
                COALESCE(SUM(CASE WHEN parcela.status = 'pendente' AND parcela.vencimento_em < CURRENT_DATE THEN parcela.valor ELSE 0 END), 0) AS overdue,
                COALESCE(SUM(CASE WHEN parcela.status = 'pendente' AND parcela.vencimento_em = CURRENT_DATE THEN parcela.valor ELSE 0 END), 0) AS today,
                COALESCE(SUM(CASE WHEN parcela.status = 'pendente' AND parcela.vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY) THEN parcela.valor ELSE 0 END), 0) AS week
             FROM contas_pagar_parcelas parcela
             JOIN contas_pagar conta ON conta.id = parcela.conta_pagar_id
            WHERE conta.status <> 'cancelada'"
        )->fetch() ?: [];
        return [
            'open' => $this->decimal((string) ($row['open'] ?? '0')),
            'overdue' => $this->decimal((string) ($row['overdue'] ?? '0')),
            'today' => $this->decimal((string) ($row['today'] ?? '0')),
            'week' => $this->decimal((string) ($row['week'] ?? '0')),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function listAccounts(array $filters = []): array
    {
        $where = [];
        $params = [];
        $search = $this->filterText($filters['search'] ?? '', 150);
        $status = $this->filterChoice($filters['status'] ?? '', ['', 'pendente', 'vencida', 'parcial', 'paga', 'cancelada']);
        $bucket = $this->filterChoice($filters['bucket'] ?? '', ['', 'vencidos', 'hoje', 'semana', '15dias']);
        $supplierId = $this->optionalPositiveInt($filters['supplier_id'] ?? null);

        if ($search !== '') {
            $where[] = '(cp.codigo LIKE :search_code OR cp.descricao LIKE :search_description
                OR cp.documento LIKE :search_document OR f.nome LIKE :search_supplier
                OR f.nome_fantasia LIKE :search_trade_name)';
            foreach (['search_code', 'search_description', 'search_document', 'search_supplier', 'search_trade_name'] as $key) {
                $params[$key] = '%' . $search . '%';
            }
        }
        if ($supplierId !== null) {
            $where[] = 'cp.fornecedor_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
        }
        if ($status === 'pendente') $where[] = "cp.status = 'pendente' AND parcelas.vencidas = 0";
        if ($status === 'vencida') $where[] = "cp.status IN ('pendente', 'parcial') AND parcelas.vencidas > 0";
        if (in_array($status, ['parcial', 'paga', 'cancelada'], true)) {
            $where[] = 'cp.status = :status';
            $params['status'] = $status;
        }
        if ($bucket === 'vencidos') $where[] = 'parcelas.vencidas > 0';
        if ($bucket === 'hoje') $where[] = 'parcelas.vencem_hoje > 0';
        if ($bucket === 'semana') $where[] = 'parcelas.proximos_7 > 0';
        if ($bucket === '15dias') $where[] = 'parcelas.proximos_15 > 0';

        $sql = "SELECT cp.*, f.nome AS fornecedor_nome, f.nome_fantasia AS fornecedor_fantasia,
                       parcelas.parcelas_pagas, parcelas.parcelas_pendentes, parcelas.valor_pago,
                       parcelas.proximo_vencimento, parcelas.vencidas,
                       EXISTS(SELECT 1 FROM contas_pagar_parcela_eventos evento
                               JOIN contas_pagar_parcelas p_evento ON p_evento.id = evento.parcela_id
                              WHERE p_evento.conta_pagar_id = cp.id) AS possui_movimentacao,
                       CASE WHEN cp.status IN ('pendente', 'parcial') AND parcelas.vencidas > 0
                            THEN 'vencida' ELSE cp.status END AS status_exibicao
                  FROM contas_pagar cp
                  JOIN fornecedores f ON f.id = cp.fornecedor_id
                  JOIN (
                       SELECT conta_pagar_id,
                              SUM(status = 'paga') AS parcelas_pagas,
                              SUM(status = 'pendente') AS parcelas_pendentes,
                              COALESCE(SUM(CASE WHEN status = 'paga' THEN valor ELSE 0 END), 0) AS valor_pago,
                              MIN(CASE WHEN status = 'pendente' THEN vencimento_em END) AS proximo_vencimento,
                              SUM(status = 'pendente' AND vencimento_em < CURRENT_DATE) AS vencidas,
                              SUM(status = 'pendente' AND vencimento_em = CURRENT_DATE) AS vencem_hoje,
                              SUM(status = 'pendente' AND vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)) AS proximos_7,
                              SUM(status = 'pendente' AND vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 15 DAY)) AS proximos_15
                         FROM contas_pagar_parcelas
                        GROUP BY conta_pagar_id
                  ) parcelas ON parcelas.conta_pagar_id = cp.id";
        if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY
                    CASE WHEN cp.status IN ('pendente', 'parcial') AND parcelas.vencidas > 0 THEN 1
                         WHEN cp.status = 'parcial' THEN 2 WHEN cp.status = 'pendente' THEN 3
                         WHEN cp.status = 'paga' THEN 4 ELSE 5 END,
                    parcelas.proximo_vencimento, cp.id DESC
                  LIMIT 301";
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        $accounts = $statement->fetchAll();
        $installments = $this->installmentsForAccounts(array_column($accounts, 'id'));
        foreach ($accounts as &$account) {
            $account['parcelas'] = $installments[(int) $account['id']] ?? [];
        }
        unset($account);
        return $accounts;
    }

    /** @return array{id:int,code:string} */
    public function saveAccount(?int $accountId, array $data, int $userId): array
    {
        if ($accountId !== null && $accountId <= 0) throw new InvalidArgumentException('Conta a pagar inválida.');
        if ($userId <= 0) throw new InvalidArgumentException('Usuário inválido.');
        $payload = $this->payload($data);
        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();

        try {
            $existing = $accountId === null ? null : $this->lockAccount($accountId);
            if ($existing !== null && (string) $existing['status'] !== 'pendente') {
                throw new InvalidArgumentException('Somente contas pendentes podem ser editadas.');
            }
            if ($existing !== null && $this->accountHasPaymentHistory($accountId)) {
                throw new InvalidArgumentException('Conta com quitação ou estorno registrado não pode ter o parcelamento alterado.');
            }
            $sameSupplier = $existing !== null && (int) $existing['fornecedor_id'] === (int) $payload['fornecedor_id'];
            $this->assertSupplierAllowed((int) $payload['fornecedor_id'], $sameSupplier);
            $this->assertDocumentAvailable((int) $payload['fornecedor_id'], $payload['documento'], $accountId);

            if ($accountId === null) {
                $statement = $this->connection->prepare(
                    'INSERT INTO contas_pagar
                        (codigo, fornecedor_id, descricao, documento, data_emissao, vencimento_em, valor,
                         tipo_pagamento, quantidade_parcelas, forma_pagamento, status, observacao, criado_por)
                     VALUES
                        (NULL, :fornecedor_id, :descricao, :documento, :data_emissao, :vencimento_em, :valor,
                         :tipo_pagamento, :quantidade_parcelas, :forma_pagamento, "pendente", :observacao, :user_id)'
                );
                $statement->execute($payload + ['user_id' => $userId]);
                $accountId = (int) $this->connection->lastInsertId();
                $code = sprintf('CP-%06d', $accountId);
                $this->connection->prepare('UPDATE contas_pagar SET codigo = :code WHERE id = :id')
                    ->execute(['code' => $code, 'id' => $accountId]);
                $this->replaceInstallments($accountId, $payload);
            } else {
                $statement = $this->connection->prepare(
                    'UPDATE contas_pagar SET
                        fornecedor_id = :fornecedor_id, descricao = :descricao, documento = :documento,
                        data_emissao = :data_emissao, vencimento_em = :vencimento_em, valor = :valor,
                        tipo_pagamento = :tipo_pagamento, quantidade_parcelas = :quantidade_parcelas,
                        forma_pagamento = :forma_pagamento,
                        observacao = :observacao
                     WHERE id = :id'
                );
                $statement->execute($payload + ['id' => $accountId]);
                $code = (string) $existing['codigo'];
                $this->replaceInstallments($accountId, $payload);
            }

            if ($ownsTransaction) $this->connection->commit();
            return ['id' => $accountId, 'code' => $code];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function cancelAccount(int $accountId, string $reason, int $userId): void
    {
        if ($userId <= 0) throw new InvalidArgumentException('Usuário inválido.');
        $reason = $this->requiredText($reason, 255, 'Informe o motivo do cancelamento.');
        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();
        try {
            $account = $this->lockAccount($accountId);
            if ((string) $account['status'] !== 'pendente') throw new InvalidArgumentException('Somente contas sem parcelas quitadas podem ser canceladas.');
            $this->connection->prepare(
                'UPDATE contas_pagar
                    SET status = "cancelada", cancelada_em = NOW(), cancelada_por = :user_id,
                        motivo_cancelamento = :reason
                  WHERE id = :id'
            )->execute(['id' => $accountId, 'user_id' => $userId, 'reason' => $reason]);
            $this->connection->prepare(
                'UPDATE contas_pagar_parcelas SET status = "cancelada"
                  WHERE conta_pagar_id = :id AND status = "pendente"'
            )->execute(['id' => $accountId]);
            if ($ownsTransaction) $this->connection->commit();
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function settleInstallment(int $installmentId, string $paymentMethod, int $userId): void
    {
        if ($userId <= 0) throw new InvalidArgumentException('Usuário inválido.');
        $paymentMethod = $this->choice($paymentMethod, self::paymentMethods(), 'Selecione a forma de pagamento.');
        $this->connection->beginTransaction();
        try {
            $installment = $this->lockInstallment($installmentId);
            if ((string) $installment['account_status'] === 'cancelada') throw new InvalidArgumentException('A conta está cancelada.');
            if ((string) $installment['status'] !== 'pendente') throw new InvalidArgumentException('Somente parcelas pendentes podem ser quitadas.');
            $cashId = $this->registerInstallmentCashOutflow($installment, $paymentMethod, $userId);
            $this->connection->prepare(
                'UPDATE contas_pagar_parcelas
                    SET status = "paga", quitada_em = NOW(), quitada_por = :user_id,
                        forma_pagamento_quitacao = :payment_method, caixa_movimentacao_id = :cash_id
                  WHERE id = :id'
            )->execute(['id' => $installmentId, 'user_id' => $userId, 'payment_method' => $paymentMethod, 'cash_id' => $cashId]);
            $this->recordInstallmentEvent($installmentId, 'quitacao', $paymentMethod, null, $userId, $cashId);
            $this->recalculateAccountStatus((int) $installment['conta_pagar_id']);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function reverseInstallmentPayment(int $installmentId, string $reason, int $userId): void
    {
        if ($userId <= 0) throw new InvalidArgumentException('Usuário inválido.');
        $reason = $this->requiredText($reason, 255, 'Informe o motivo do estorno.');
        $this->connection->beginTransaction();
        try {
            $installment = $this->lockInstallment($installmentId);
            if ((string) $installment['account_status'] === 'cancelada') throw new InvalidArgumentException('A conta está cancelada.');
            if ((string) $installment['status'] !== 'paga') throw new InvalidArgumentException('Somente parcelas quitadas podem ser estornadas.');
            $method = $installment['forma_pagamento_quitacao'] === null ? null : (string) $installment['forma_pagamento_quitacao'];
            $cashId = $this->reverseInstallmentCashOutflow($installment, $reason, $userId);
            $this->connection->prepare(
                'UPDATE contas_pagar_parcelas
                    SET status = "pendente", quitada_em = NULL, quitada_por = NULL,
                        forma_pagamento_quitacao = NULL, caixa_movimentacao_id = NULL
                  WHERE id = :id'
            )->execute(['id' => $installmentId]);
            $this->recordInstallmentEvent($installmentId, 'estorno', $method, $reason, $userId, $cashId);
            $this->recalculateAccountStatus((int) $installment['conta_pagar_id']);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    private function payload(array $data): array
    {
        $supplierId = $this->optionalPositiveInt($data['fornecedor_id'] ?? null);
        if ($supplierId === null) throw new InvalidArgumentException('Selecione um fornecedor.');
        $issueDate = $this->optionalDate($data['data_emissao'] ?? null);
        $dueDate = $this->requiredDate($data['vencimento_em'] ?? null);
        if ($issueDate !== null && $dueDate < $issueDate) throw new InvalidArgumentException('O vencimento não pode ser anterior à emissão.');
        $value = $this->money($data['valor'] ?? '');
        if ($value === '0.00') throw new InvalidArgumentException('O valor da conta deve ser maior que zero.');
        $paymentType = $this->choice($data['tipo_pagamento'] ?? 'avista', ['avista', 'parcelado'], 'Informe se a conta é à vista ou parcelada.');
        $installmentCount = $paymentType === 'avista' ? 1 : $this->requiredInt($data['quantidade_parcelas'] ?? null, 2, 60, 'Informe entre 2 e 60 parcelas.');
        $paymentMethod = $this->choice($data['forma_pagamento'] ?? '', self::paymentMethods(), 'Selecione a forma de pagamento.');

        return [
            'fornecedor_id' => $supplierId,
            'descricao' => $this->requiredText($data['descricao'] ?? '', 255, 'Informe a descrição da conta.'),
            'documento' => $this->optionalText($data['documento'] ?? null, 80),
            'data_emissao' => $issueDate,
            'vencimento_em' => $dueDate,
            'valor' => $value,
            'tipo_pagamento' => $paymentType,
            'quantidade_parcelas' => $installmentCount,
            'forma_pagamento' => $paymentMethod,
            'observacao' => $this->optionalText($data['observacao'] ?? null, 1000),
        ];
    }

    /** @param array<string,mixed> $payload */
    private function replaceInstallments(int $accountId, array $payload): void
    {
        $this->connection->prepare('DELETE FROM contas_pagar_parcelas WHERE conta_pagar_id = :id')
            ->execute(['id' => $accountId]);
        $statement = $this->connection->prepare(
            'INSERT INTO contas_pagar_parcelas (conta_pagar_id, numero, vencimento_em, valor)
             VALUES (:account_id, :number, :due_date, :amount)'
        );
        foreach (self::installmentPlan(
            (string) $payload['valor'],
            (string) $payload['vencimento_em'],
            (int) $payload['quantidade_parcelas']
        ) as $installment) {
            $statement->execute([
                'account_id' => $accountId,
                'number' => $installment['numero'],
                'due_date' => $installment['vencimento_em'],
                'amount' => $installment['valor'],
            ]);
        }
    }

    /** @param array<int,mixed> $accountIds @return array<int,array<int,array<string,mixed>>> */
    private function installmentsForAccounts(array $accountIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $accountIds), static fn(int $id): bool => $id > 0));
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->connection->prepare(
            "SELECT id, conta_pagar_id, numero, vencimento_em, valor, status,
                    quitada_em, forma_pagamento_quitacao, caixa_movimentacao_id
               FROM contas_pagar_parcelas
              WHERE conta_pagar_id IN ($placeholders)
              ORDER BY conta_pagar_id, numero"
        );
        $statement->execute($ids);
        $grouped = [];
        foreach ($statement->fetchAll() as $installment) {
            $grouped[(int) $installment['conta_pagar_id']][] = $installment;
        }
        return $grouped;
    }

    /** @return array<string,mixed> */
    private function lockInstallment(int $installmentId): array
    {
        if ($installmentId <= 0) throw new InvalidArgumentException('Parcela inválida.');
        $statement = $this->connection->prepare(
            'SELECT parcela.*, conta.status AS account_status, conta.codigo AS account_code,
                    conta.descricao AS account_description, fornecedor.nome AS supplier_name
               FROM contas_pagar_parcelas parcela
               JOIN contas_pagar conta ON conta.id = parcela.conta_pagar_id
               JOIN fornecedores fornecedor ON fornecedor.id = conta.fornecedor_id
              WHERE parcela.id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $installmentId]);
        $installment = $statement->fetch();
        if ($installment === false) throw new InvalidArgumentException('Parcela não encontrada.');
        return $installment;
    }

    private function recordInstallmentEvent(int $installmentId, string $type, ?string $method, ?string $notes, int $userId, ?int $cashId): void
    {
        $this->connection->prepare(
            'INSERT INTO contas_pagar_parcela_eventos
                (parcela_id, tipo, forma_pagamento, observacao, usuario_id, caixa_movimentacao_id)
             VALUES (:installment_id, :event_type, :payment_method, :notes, :user_id, :cash_id)'
        )->execute([
            'installment_id' => $installmentId,
            'event_type' => $type,
            'payment_method' => $method,
            'notes' => $notes,
            'user_id' => $userId,
            'cash_id' => $cashId,
        ]);
    }

    private function recalculateAccountStatus(int $accountId): void
    {
        $statement = $this->connection->prepare(
            "SELECT SUM(status = 'paga') AS paid, SUM(status = 'pendente') AS pending
               FROM contas_pagar_parcelas WHERE conta_pagar_id = :id"
        );
        $statement->execute(['id' => $accountId]);
        $totals = $statement->fetch() ?: [];
        $paid = (int) ($totals['paid'] ?? 0);
        $pending = (int) ($totals['pending'] ?? 0);
        $status = $pending === 0 ? 'paga' : ($paid > 0 ? 'parcial' : 'pendente');
        $this->connection->prepare('UPDATE contas_pagar SET status = :status WHERE id = :id')
            ->execute(['status' => $status, 'id' => $accountId]);
    }

    /** @return array<string,mixed> */
    private function lockAccount(int $accountId): array
    {
        if ($accountId <= 0) throw new InvalidArgumentException('Conta a pagar inválida.');
        $statement = $this->connection->prepare('SELECT * FROM contas_pagar WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $accountId]);
        $account = $statement->fetch();
        if ($account === false) throw new InvalidArgumentException('Conta a pagar não encontrada.');
        return $account;
    }

    private function assertSupplierAllowed(int $supplierId, bool $allowInactive): void
    {
        $statement = $this->connection->prepare('SELECT status FROM fornecedores WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $supplierId]);
        $status = $statement->fetchColumn();
        if ($status === false) throw new InvalidArgumentException('Fornecedor não encontrado.');
        if (!$allowInactive && $status !== 'ativo') throw new InvalidArgumentException('Selecione um fornecedor ativo.');
    }

    private function assertDocumentAvailable(int $supplierId, ?string $document, ?int $ignoreId): void
    {
        if ($document === null) return;
        $sql = 'SELECT id FROM contas_pagar WHERE fornecedor_id = :supplier_id AND documento = :document';
        $params = ['supplier_id' => $supplierId, 'document' => $document];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        $statement = $this->connection->prepare($sql . ' LIMIT 1');
        $statement->execute($params);
        if ($statement->fetchColumn() !== false) throw new InvalidArgumentException('Já existe uma conta deste fornecedor com o mesmo documento.');
    }

    private function money(mixed $value): string
    {
        $raw = str_replace(' ', '', trim((string) $value));
        if (str_contains($raw, ',')) $raw = str_replace(',', '.', str_replace('.', '', $raw));
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $raw) !== 1) throw new InvalidArgumentException('Valor da conta inválido.');
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        if (strlen($whole) > 10) throw new InvalidArgumentException('Valor da conta excede o limite permitido.');
        $normalizedWhole = ltrim($whole, '0');
        if ($normalizedWhole === '') $normalizedWhole = '0';
        return $normalizedWhole . '.' . str_pad($fraction, 2, '0');
    }

    private function decimal(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function requiredDate(mixed $value): string
    {
        $date = $this->optionalDate($value);
        if ($date === null) throw new InvalidArgumentException('Informe a data de vencimento.');
        return $date;
    }

    private function optionalDate(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $text) {
            throw new InvalidArgumentException('Data da conta inválida.');
        }
        if ($text < '1000-01-01' || $text > '9999-12-31') throw new InvalidArgumentException('Data da conta fora do intervalo permitido.');
        return $text;
    }

    private function requiredText(mixed $value, int $max, string $message): string
    {
        $text = $this->optionalText($value, $max);
        if ($text === null) throw new InvalidArgumentException($message);
        return $text;
    }

    private function optionalText(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($length > $max || str_contains($text, "\0") || $text !== strip_tags($text)) throw new InvalidArgumentException('Dados da conta a pagar inválidos.');
        return $text;
    }

    private function filterText(mixed $value, int $max): string
    {
        if (!is_string($value)) throw new InvalidArgumentException('Filtro inválido.');
        $text = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($length > $max || str_contains($text, "\0")) throw new InvalidArgumentException('Filtro inválido.');
        return $text;
    }

    /** @param string[] $allowed */
    private function filterChoice(mixed $value, array $allowed): string
    {
        if (!is_string($value) || !in_array(trim($value), $allowed, true)) throw new InvalidArgumentException('Filtro inválido.');
        return trim($value);
    }

    /** @param string[] $allowed */
    private function choice(mixed $value, array $allowed, string $message): string
    {
        $choice = trim((string) $value);
        if (!in_array($choice, $allowed, true)) throw new InvalidArgumentException($message);
        return $choice;
    }

    private function requiredInt(mixed $value, int $minimum, int $maximum, string $message): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum, 'max_range' => $maximum]]);
        if (!is_int($number)) throw new InvalidArgumentException($message);
        return $number;
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($id)) throw new InvalidArgumentException('Identificador inválido.');
        return $id;
    }
}
