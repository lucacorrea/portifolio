<?php
declare(strict_types=1);

namespace App\Access\Repository;

use App\Access\Entity\Permission;
use InvalidArgumentException;
use PDO;

final class PermissionRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?Permission
    {
        $this->assertPositiveId($id);

        $statement = $this->connection->prepare(
            'SELECT id, grupo, modulo, codigo, nome, descricao, ordem, status, criado_em
               FROM permissoes
              WHERE id = :id
              LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : Permission::fromArray($row);
    }

    public function findByCode(string $code): ?Permission
    {
        $statement = $this->connection->prepare(
            'SELECT id, grupo, modulo, codigo, nome, descricao, ordem, status, criado_em
               FROM permissoes
              WHERE codigo = :code
              LIMIT 1'
        );
        $statement->execute(['code' => trim($code)]);

        $row = $statement->fetch();

        return $row === false ? null : Permission::fromArray($row);
    }

    /**
     * @return Permission[]
     */
    public function findAllActive(): array
    {
        $statement = $this->connection->prepare(
            "SELECT id, grupo, modulo, codigo, nome, descricao, ordem, status, criado_em
               FROM permissoes
              WHERE status = 'ativo'
              ORDER BY grupo ASC, ordem ASC, nome ASC"
        );
        $statement->execute();

        return array_map(
            static fn (array $row): Permission => Permission::fromArray($row),
            $statement->fetchAll()
        );
    }

    /**
     * @return array<string, Permission[]>
     */
    public function findByGroup(): array
    {
        $permissions = $this->findAllActive();
        $grouped = [];

        foreach ($permissions as $permission) {
            $grouped[$permission->group()][] = $permission;
        }

        return $grouped;
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID invalido.');
        }
    }
}
