<?php

declare(strict_types=1);

namespace App\Catalog\Repository;

use App\Catalog\DTO\ServiceFormData;
use App\Catalog\Entity\ServiceDefinition;
use InvalidArgumentException;
use PDO;
use Throwable;

final class ServiceRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /** @return ServiceDefinition[] */
    public function findAll(array $filters = []): array
    {
        $where = ['excluido_em IS NULL'];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $where[] = '(codigo LIKE :search_code OR nome LIKE :search_name OR categoria LIKE :search_category OR equipamentos_compativeis LIKE :search_equipment OR descricao LIKE :search_description)';
            $params['search_code'] = '%' . $search . '%';
            $params['search_name'] = '%' . $search . '%';
            $params['search_category'] = '%' . $search . '%';
            $params['search_equipment'] = '%' . $search . '%';
            $params['search_description'] = '%' . $search . '%';
        }

        foreach (['category' => 'categoria', 'status' => 'status'] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));

            if ($value !== '') {
                $where[] = $column . ' = :' . $key;
                $params[$key] = $value;
            }
        }

        $sql = 'SELECT id, codigo, nome, categoria, equipamentos_compativeis,
                       duracao_minutos, valor, descricao, status, criado_em, atualizado_em
                  FROM servicos';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY nome ASC, id ASC';

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return array_map(
            static fn(array $row): ServiceDefinition => ServiceDefinition::fromArray($row),
            $statement->fetchAll()
        );
    }

    public function findById(int $id): ?ServiceDefinition
    {
        $this->assertPositiveId($id);
        $statement = $this->connection->prepare(
            'SELECT id, codigo, nome, categoria, equipamentos_compativeis,
                    duracao_minutos, valor, descricao, status, criado_em, atualizado_em
               FROM servicos
              WHERE id = :id
                AND excluido_em IS NULL
              LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row === false ? null : ServiceDefinition::fromArray($row);
    }

    public function findByIdForUpdate(int $id): ?ServiceDefinition
    {
        $this->assertPositiveId($id);
        $statement = $this->connection->prepare(
            'SELECT id, codigo, nome, categoria, equipamentos_compativeis,
                    duracao_minutos, valor, descricao, status, criado_em, atualizado_em
               FROM servicos
              WHERE id = :id AND excluido_em IS NULL
              LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return $row === false ? null : ServiceDefinition::fromArray($row);
    }

    /** @return array{total:int,active:int,inactive:int} */
    public function summary(): array
    {
        $statement = $this->connection->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'ativo' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status = 'inativo' THEN 1 ELSE 0 END) AS inactive
               FROM servicos
              WHERE excluido_em IS NULL"
        );
        $row = $statement->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'inactive' => (int) ($row['inactive'] ?? 0),
        ];
    }

    public function create(ServiceFormData $data): ServiceDefinition
    {
        $this->connection->beginTransaction();

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO servicos
                    (nome, categoria, equipamentos_compativeis, duracao_minutos, valor, descricao, status)
                 VALUES
                    (:name, :category, :compatible_equipment, :duration_minutes, :value, :description, :status)'
            );
            $this->bindForm($statement, $data);
            $statement->execute();

            $id = (int) $this->connection->lastInsertId();
            $this->assertPositiveId($id);
            $code = sprintf('SRV-%06d', $id);

            $update = $this->connection->prepare(
                'UPDATE servicos SET codigo = :code WHERE id = :id'
            );
            $update->execute(['id' => $id, 'code' => $code]);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        $service = $this->findById($id);

        if ($service === null) {
            throw new InvalidArgumentException('Serviço não encontrado após cadastro.');
        }

        return $service;
    }

    public function update(int $id, ServiceFormData $data): void
    {
        $this->assertPositiveId($id);
        $statement = $this->connection->prepare(
            'UPDATE servicos
                SET nome = :name,
                    categoria = :category,
                    equipamentos_compativeis = :compatible_equipment,
                    duracao_minutos = :duration_minutes,
                    valor = :value,
                    descricao = :description,
                    status = :status
              WHERE id = :id
                AND excluido_em IS NULL'
        );
        $statement->bindValue('id', $id);
        $this->bindForm($statement, $data);
        $statement->execute();
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->assertPositiveId($id);
        $this->assertPositiveId($userId);
        $statement = $this->connection->prepare(
            "UPDATE servicos
                SET status = 'inativo', excluido_em = CURRENT_TIMESTAMP, excluido_por = :user_id
              WHERE id = :id AND excluido_em IS NULL"
        );
        $statement->execute(['id' => $id, 'user_id' => $userId]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Serviço não encontrado.');
        }
    }

    private function bindForm(\PDOStatement $statement, ServiceFormData $data): void
    {
        $statement->bindValue('name', $data->name());
        $statement->bindValue('category', $data->category());
        $statement->bindValue('compatible_equipment', $data->compatibleEquipment());
        $statement->bindValue('duration_minutes', $data->durationMinutes(), PDO::PARAM_INT);
        $statement->bindValue('value', $data->value());
        $statement->bindValue('description', $data->description());
        $statement->bindValue('status', $data->status());
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID inválido.');
        }
    }
}
