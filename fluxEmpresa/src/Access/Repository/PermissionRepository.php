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
              ORDER BY grupo ASC, modulo ASC, ordem ASC, nome ASC"
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
    public function findAllActiveGrouped(): array
    {
        $permissions = $this->findAllActive();
        $grouped = [];

        foreach ($permissions as $permission) {
            $grouped[$permission->group()][] = $permission;
        }

        return $grouped;
    }

    /**
     * @return int[]
     */
    public function findActiveIds(): array
    {
        $statement = $this->connection->prepare(
            "SELECT id
               FROM permissoes
              WHERE status = 'ativo'
              ORDER BY grupo ASC, modulo ASC, ordem ASC, nome ASC"
        );
        $statement->execute();

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * @param int[] $ids
     */
    public function countActiveByIds(array $ids): int
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return 0;
        }

        $statement = $this->connection->prepare(
            "SELECT COUNT(*)
               FROM permissoes
              WHERE status = 'ativo'
                AND id IN (" . $this->placeholders($ids) . ')'
        );
        $statement->execute($ids);

        return (int) $statement->fetchColumn();
    }

    /**
     * @param int[] $ids
     * @return Permission[]
     */
    public function findActiveByIds(array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        $statement = $this->connection->prepare(
            "SELECT id, grupo, modulo, codigo, nome, descricao, ordem, status, criado_em
               FROM permissoes
              WHERE status = 'ativo'
                AND id IN (" . $this->placeholders($ids) . ')
              ORDER BY grupo ASC, modulo ASC, ordem ASC, nome ASC'
        );
        $statement->execute($ids);

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
        return $this->findAllActiveGrouped();
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = array_values(array_unique(array_map('intval', $ids)));

        foreach ($normalized as $id) {
            $this->assertPositiveId($id);
        }

        return $normalized;
    }

    /**
     * @param int[] $ids
     */
    private function placeholders(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID invalido.');
        }
    }
}
