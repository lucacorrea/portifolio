<?php

declare(strict_types=1);

namespace App\Finance\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Throwable;

final class AccountsPayableManagementService
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array{open:string,overdue:string,today:string,week:string} */
    public function indicators(): array
    {
        $row = $this->connection->query(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'pendente' THEN valor ELSE 0 END), 0) AS open,
                COALESCE(SUM(CASE WHEN status = 'pendente' AND vencimento_em < CURRENT_DATE THEN valor ELSE 0 END), 0) AS overdue,
                COALESCE(SUM(CASE WHEN status = 'pendente' AND vencimento_em = CURRENT_DATE THEN valor ELSE 0 END), 0) AS today,
                COALESCE(SUM(CASE WHEN status = 'pendente' AND vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY) THEN valor ELSE 0 END), 0) AS week
             FROM contas_pagar"
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
        $status = $this->filterChoice($filters['status'] ?? '', ['', 'pendente', 'vencida', 'paga', 'cancelada']);
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
        if ($status === 'pendente') $where[] = "cp.status = 'pendente' AND cp.vencimento_em >= CURRENT_DATE";
        if ($status === 'vencida') $where[] = "cp.status = 'pendente' AND cp.vencimento_em < CURRENT_DATE";
        if (in_array($status, ['paga', 'cancelada'], true)) {
            $where[] = 'cp.status = :status';
            $params['status'] = $status;
        }
        if ($bucket === 'vencidos') $where[] = "cp.status = 'pendente' AND cp.vencimento_em < CURRENT_DATE";
        if ($bucket === 'hoje') $where[] = "cp.status = 'pendente' AND cp.vencimento_em = CURRENT_DATE";
        if ($bucket === 'semana') $where[] = "cp.status = 'pendente' AND cp.vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)";
        if ($bucket === '15dias') $where[] = "cp.status = 'pendente' AND cp.vencimento_em BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 15 DAY)";

        $sql = "SELECT cp.*, f.nome AS fornecedor_nome, f.nome_fantasia AS fornecedor_fantasia,
                       CASE WHEN cp.status = 'pendente' AND cp.vencimento_em < CURRENT_DATE
                            THEN 'vencida' ELSE cp.status END AS status_exibicao
                  FROM contas_pagar cp
                  JOIN fornecedores f ON f.id = cp.fornecedor_id";
        if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= " ORDER BY
                    CASE WHEN cp.status = 'pendente' AND cp.vencimento_em < CURRENT_DATE THEN 1
                         WHEN cp.status = 'pendente' THEN 2
                         WHEN cp.status = 'paga' THEN 3 ELSE 4 END,
                    cp.vencimento_em, cp.id DESC
                  LIMIT 301";
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
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
            $sameSupplier = $existing !== null && (int) $existing['fornecedor_id'] === (int) $payload['fornecedor_id'];
            $this->assertSupplierAllowed((int) $payload['fornecedor_id'], $sameSupplier);
            $this->assertDocumentAvailable((int) $payload['fornecedor_id'], $payload['documento'], $accountId);

            if ($accountId === null) {
                $statement = $this->connection->prepare(
                    'INSERT INTO contas_pagar
                        (codigo, fornecedor_id, descricao, documento, data_emissao, vencimento_em, valor,
                         status, observacao, criado_por)
                     VALUES
                        (NULL, :fornecedor_id, :descricao, :documento, :data_emissao, :vencimento_em, :valor,
                         "pendente", :observacao, :user_id)'
                );
                $statement->execute($payload + ['user_id' => $userId]);
                $accountId = (int) $this->connection->lastInsertId();
                $code = sprintf('CP-%06d', $accountId);
                $this->connection->prepare('UPDATE contas_pagar SET codigo = :code WHERE id = :id')
                    ->execute(['code' => $code, 'id' => $accountId]);
            } else {
                $statement = $this->connection->prepare(
                    'UPDATE contas_pagar SET
                        fornecedor_id = :fornecedor_id, descricao = :descricao, documento = :documento,
                        data_emissao = :data_emissao, vencimento_em = :vencimento_em, valor = :valor,
                        observacao = :observacao
                     WHERE id = :id'
                );
                $statement->execute($payload + ['id' => $accountId]);
                $code = (string) $existing['codigo'];
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
            if ((string) $account['status'] !== 'pendente') throw new InvalidArgumentException('Somente contas pendentes podem ser canceladas.');
            $this->connection->prepare(
                'UPDATE contas_pagar
                    SET status = "cancelada", cancelada_em = NOW(), cancelada_por = :user_id,
                        motivo_cancelamento = :reason
                  WHERE id = :id'
            )->execute(['id' => $accountId, 'user_id' => $userId, 'reason' => $reason]);
            if ($ownsTransaction) $this->connection->commit();
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
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

        return [
            'fornecedor_id' => $supplierId,
            'descricao' => $this->requiredText($data['descricao'] ?? '', 255, 'Informe a descrição da conta.'),
            'documento' => $this->optionalText($data['documento'] ?? null, 80),
            'data_emissao' => $issueDate,
            'vencimento_em' => $dueDate,
            'valor' => $value,
            'observacao' => $this->optionalText($data['observacao'] ?? null, 1000),
        ];
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

    private function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($id)) throw new InvalidArgumentException('Identificador inválido.');
        return $id;
    }
}
