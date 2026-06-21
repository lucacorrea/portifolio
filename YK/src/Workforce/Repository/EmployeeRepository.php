<?php

declare(strict_types=1);

namespace App\Workforce\Repository;

use App\Workforce\DTO\EmployeeFormData;
use App\Workforce\Entity\Employee;
use InvalidArgumentException;
use PDO;
use Throwable;

final class EmployeeRepository
{
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * @return Employee[]
     */
    public function findAll(string $search = ''): array
    {
        $search = trim($search);

        $sql = 'SELECT id, codigo, nome, criado_em, atualizado_em
                  FROM funcionarios';
        $parameters = [];

        if ($search !== '') {
            $sql .= ' WHERE codigo LIKE :search_code
                       OR nome LIKE :search_name';
            $parameters['search_code'] = '%' . $search . '%';
            $parameters['search_name'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY nome ASC, id ASC';

        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);

        return array_map(
            static fn(array $row): Employee => Employee::fromArray($row),
            $statement->fetchAll()
        );
    }

    public function findById(int $id): ?Employee
    {
        $this->assertPositiveId($id);

        $statement = $this->connection->prepare(
            'SELECT id, codigo, nome, criado_em, atualizado_em
               FROM funcionarios
              WHERE id = :id
              LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : Employee::fromArray($row);
    }

    public function create(EmployeeFormData $data): Employee
    {
        $this->connection->beginTransaction();

        try {
            $statement = $this->connection->prepare(
                'INSERT INTO funcionarios (nome)
                 VALUES (:name)'
            );
            $statement->execute([
                'name' => $data->name(),
            ]);

            $id = (int) $this->connection->lastInsertId();
            $this->assertPositiveId($id);

            $code = sprintf('FUN-%06d', $id);

            $update = $this->connection->prepare(
                'UPDATE funcionarios
                    SET codigo = :code
                  WHERE id = :id'
            );
            $update->execute([
                'id' => $id,
                'code' => $code,
            ]);

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        $employee = $this->findById($id);

        if ($employee === null) {
            throw new InvalidArgumentException(
                'Funcionário não encontrado após cadastro.'
            );
        }

        return $employee;
    }

    public function updateName(int $id, string $name): void
    {
        $this->assertPositiveId($id);

        $statement = $this->connection->prepare(
            'UPDATE funcionarios
                SET nome = :name
              WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
        ]);
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'ID de funcionário inválido.'
            );
        }
    }
}
