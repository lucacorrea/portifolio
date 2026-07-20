<?php

declare(strict_types=1);

namespace App\Purchasing\Service;

use InvalidArgumentException;
use PDO;
use Throwable;

final class SupplierManagementService
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function listSuppliers(array $filters = []): array
    {
        $where = [];
        $params = [];
        $search = $this->filterText($filters['search'] ?? '', 150);
        $type = $this->filterChoice($filters['type'] ?? '', ['', 'fisica', 'juridica']);
        $status = $this->filterChoice($filters['status'] ?? '', ['', 'ativo', 'inativo']);
        $city = $this->filterText($filters['city'] ?? '', 100);

        if ($search !== '') {
            $where[] = '(f.codigo LIKE :search_code OR f.nome LIKE :search_name OR f.nome_fantasia LIKE :search_trade_name
                OR f.documento LIKE :search_document OR f.telefone LIKE :search_phone OR f.email LIKE :search_email)';
            foreach (['search_code', 'search_name', 'search_trade_name', 'search_document', 'search_phone', 'search_email'] as $key) {
                $params[$key] = '%' . $search . '%';
            }
        }
        if ($type !== '') {
            $where[] = 'f.tipo_pessoa = :type';
            $params['type'] = $type;
        }
        if ($status !== '') {
            $where[] = 'f.status = :status';
            $params['status'] = $status;
        }
        if ($city !== '') {
            $where[] = 'f.cidade = :city';
            $params['city'] = $city;
        }

        $sql = 'SELECT f.* FROM fornecedores f';
        if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY f.nome, f.id LIMIT 201';
        $statement = $this->connection->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return array{total:int,active:int,inactive:int} */
    public function summary(): array
    {
        $row = $this->connection->query(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'ativo') AS active,
                    SUM(status = 'inativo') AS inactive
               FROM fornecedores"
        )->fetch() ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'inactive' => (int) ($row['inactive'] ?? 0),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function activeSuppliers(): array
    {
        return $this->connection->query(
            "SELECT id, codigo, nome, nome_fantasia
               FROM fornecedores
              WHERE status = 'ativo'
              ORDER BY nome, id"
        )->fetchAll();
    }

    /** @return string[] */
    public function cities(): array
    {
        $rows = $this->connection->query(
            "SELECT DISTINCT cidade FROM fornecedores
              WHERE cidade IS NOT NULL AND cidade <> '' ORDER BY cidade"
        )->fetchAll(PDO::FETCH_COLUMN);
        return array_map('strval', $rows);
    }

    /** @return array<int,array<string,mixed>> */
    public function supplierOptions(): array
    {
        return $this->connection->query(
            'SELECT id, codigo, nome, status FROM fornecedores ORDER BY nome, id'
        )->fetchAll();
    }

    /** @return array{id:int,code:string} */
    public function saveSupplier(?int $supplierId, array $data, int $userId): array
    {
        if ($supplierId !== null && $supplierId <= 0) throw new InvalidArgumentException('Fornecedor inválido.');
        if ($userId <= 0) throw new InvalidArgumentException('Usuário inválido.');
        $payload = $this->payload($data);
        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();

        try {
            if ($supplierId !== null) {
                $this->lockSupplier($supplierId);
            }
            $this->assertDocumentAvailable($payload['documento'], $supplierId);

            if ($supplierId === null) {
                $statement = $this->connection->prepare(
                    'INSERT INTO fornecedores
                        (codigo, tipo_pessoa, nome, nome_fantasia, documento, inscricao_estadual, contato,
                         telefone, whatsapp, email, cep, endereco, numero, complemento, bairro, cidade,
                         estado, observacao, status, criado_por)
                     VALUES
                        (NULL, :tipo_pessoa, :nome, :nome_fantasia, :documento, :inscricao_estadual, :contato,
                         :telefone, :whatsapp, :email, :cep, :endereco, :numero, :complemento, :bairro, :cidade,
                         :estado, :observacao, "ativo", :user_id)'
                );
                $statement->execute($payload + ['user_id' => $userId]);
                $supplierId = (int) $this->connection->lastInsertId();
                $code = sprintf('FOR-%06d', $supplierId);
                $this->connection->prepare('UPDATE fornecedores SET codigo = :code WHERE id = :id')
                    ->execute(['code' => $code, 'id' => $supplierId]);
            } else {
                $statement = $this->connection->prepare(
                    'UPDATE fornecedores SET
                        tipo_pessoa = :tipo_pessoa, nome = :nome, nome_fantasia = :nome_fantasia,
                        documento = :documento, inscricao_estadual = :inscricao_estadual, contato = :contato,
                        telefone = :telefone, whatsapp = :whatsapp, email = :email, cep = :cep,
                        endereco = :endereco, numero = :numero, complemento = :complemento, bairro = :bairro,
                        cidade = :cidade, estado = :estado, observacao = :observacao
                     WHERE id = :id'
                );
                $statement->execute($payload + ['id' => $supplierId]);
                $codeStatement = $this->connection->prepare('SELECT codigo FROM fornecedores WHERE id = :id');
                $codeStatement->execute(['id' => $supplierId]);
                $code = (string) $codeStatement->fetchColumn();
            }

            if ($ownsTransaction) $this->connection->commit();
            return ['id' => $supplierId, 'code' => $code];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    public function setStatus(int $supplierId, string $status): void
    {
        if (!in_array($status, ['ativo', 'inativo'], true)) throw new InvalidArgumentException('Status de fornecedor inválido.');
        $ownsTransaction = !$this->connection->inTransaction();
        if ($ownsTransaction) $this->connection->beginTransaction();
        try {
            $this->lockSupplier($supplierId);
            $this->connection->prepare('UPDATE fornecedores SET status = :status WHERE id = :id')
                ->execute(['status' => $status, 'id' => $supplierId]);
            if ($ownsTransaction) $this->connection->commit();
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->connection->inTransaction()) $this->connection->rollBack();
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    private function payload(array $data): array
    {
        $type = $this->filterChoice($data['tipo_pessoa'] ?? '', ['fisica', 'juridica']);
        $document = preg_replace('/\D+/', '', (string) ($data['documento'] ?? '')) ?: null;
        if ($document !== null && (($type === 'fisica' && !self::isValidCpf($document)) || ($type === 'juridica' && !self::isValidCnpj($document)))) {
            throw new InvalidArgumentException($type === 'fisica' ? 'Informe um CPF válido.' : 'Informe um CNPJ válido.');
        }
        $email = $this->optionalText($data['email'] ?? null, 150);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new InvalidArgumentException('E-mail do fornecedor inválido.');
        $state = strtoupper((string) ($this->optionalText($data['estado'] ?? null, 2) ?? '')) ?: null;
        if ($state !== null && preg_match('/^[A-Z]{2}$/', $state) !== 1) throw new InvalidArgumentException('UF do fornecedor inválida.');

        return [
            'tipo_pessoa' => $type,
            'nome' => $this->requiredText($data['nome'] ?? '', 150, 'Informe o nome ou razão social do fornecedor.'),
            'nome_fantasia' => $this->optionalText($data['nome_fantasia'] ?? null, 150),
            'documento' => $document,
            'inscricao_estadual' => $this->optionalText($data['inscricao_estadual'] ?? null, 30),
            'contato' => $this->optionalText($data['contato'] ?? null, 120),
            'telefone' => $this->optionalText($data['telefone'] ?? null, 30),
            'whatsapp' => $this->optionalText($data['whatsapp'] ?? null, 30),
            'email' => $email,
            'cep' => $this->optionalText($data['cep'] ?? null, 10),
            'endereco' => $this->optionalText($data['endereco'] ?? null, 180),
            'numero' => $this->optionalText($data['numero'] ?? null, 20),
            'complemento' => $this->optionalText($data['complemento'] ?? null, 100),
            'bairro' => $this->optionalText($data['bairro'] ?? null, 100),
            'cidade' => $this->optionalText($data['cidade'] ?? null, 100),
            'estado' => $state,
            'observacao' => $this->optionalText($data['observacao'] ?? null, 1000),
        ];
    }

    /** @return array<string,mixed> */
    private function lockSupplier(int $supplierId): array
    {
        if ($supplierId <= 0) throw new InvalidArgumentException('Fornecedor inválido.');
        $statement = $this->connection->prepare('SELECT * FROM fornecedores WHERE id = :id FOR UPDATE');
        $statement->execute(['id' => $supplierId]);
        $supplier = $statement->fetch();
        if ($supplier === false) throw new InvalidArgumentException('Fornecedor não encontrado.');
        return $supplier;
    }

    private function assertDocumentAvailable(?string $document, ?int $ignoreId): void
    {
        if ($document === null) return;
        $sql = 'SELECT id FROM fornecedores WHERE documento = :document';
        $params = ['document' => $document];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        $statement = $this->connection->prepare($sql . ' LIMIT 1');
        $statement->execute($params);
        if ($statement->fetchColumn() !== false) throw new InvalidArgumentException('Já existe um fornecedor com este CPF/CNPJ.');
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
        if ($length > $max || str_contains($text, "\0") || $text !== strip_tags($text)) throw new InvalidArgumentException('Dados do fornecedor inválidos.');
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
        if (!is_string($value) || !in_array(trim($value), $allowed, true)) throw new InvalidArgumentException('Opção inválida.');
        return trim($value);
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (preg_match('/^\d{11}$/', $cpf) !== 1 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) return false;
        for ($position = 9; $position < 11; ++$position) {
            $sum = 0;
            for ($index = 0; $index < $position; ++$index) {
                $sum += (int) $cpf[$index] * (($position + 1) - $index);
            }
            if ((int) $cpf[$position] !== ((10 * $sum) % 11) % 10) return false;
        }
        return true;
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (preg_match('/^\d{14}$/', $cnpj) !== 1 || preg_match('/^(\d)\1{13}$/', $cnpj) === 1) return false;
        $weights = [[5,4,3,2,9,8,7,6,5,4,3,2], [6,5,4,3,2,9,8,7,6,5,4,3,2]];
        foreach ($weights as $round => $roundWeights) {
            $sum = 0;
            foreach ($roundWeights as $index => $weight) $sum += (int) $cnpj[$index] * $weight;
            $digit = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
            if ((int) $cnpj[12 + $round] !== $digit) return false;
        }
        return true;
    }
}
