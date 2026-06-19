<?php
declare(strict_types=1);

namespace App\Access\Repository;

use InvalidArgumentException;
use PDO;
use Throwable;

final class ProfilePermissionRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @return string[]
     */
    public function findPermissionCodesByProfile(int $profileId): array
    {
        $this->assertPositiveId($profileId);

        $statement = $this->connection->prepare(
            "SELECT pe.codigo
               FROM perfil_permissoes pp
               INNER JOIN permissoes pe ON pe.id = pp.permissao_id
              WHERE pp.perfil_id = :profile_id
                AND pe.status = 'ativo'
              ORDER BY pe.ordem ASC, pe.codigo ASC"
        );
        $statement->execute(['profile_id' => $profileId]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function grant(int $profileId, int $permissionId): bool
    {
        $this->assertPositiveId($profileId);
        $this->assertPositiveId($permissionId);

        $statement = $this->connection->prepare(
            'INSERT IGNORE INTO perfil_permissoes (perfil_id, permissao_id)
             VALUES (:profile_id, :permission_id)'
        );

        return $statement->execute([
            'profile_id' => $profileId,
            'permission_id' => $permissionId,
        ]);
    }

    public function revoke(int $profileId, int $permissionId): bool
    {
        $this->assertPositiveId($profileId);
        $this->assertPositiveId($permissionId);

        $statement = $this->connection->prepare(
            'DELETE FROM perfil_permissoes
              WHERE perfil_id = :profile_id
                AND permissao_id = :permission_id'
        );

        return $statement->execute([
            'profile_id' => $profileId,
            'permission_id' => $permissionId,
        ]);
    }

    /**
     * @param int[] $permissionIds
     */
    public function sync(int $profileId, array $permissionIds): void
    {
        $this->assertPositiveId($profileId);
        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));

        foreach ($permissionIds as $permissionId) {
            $this->assertPositiveId($permissionId);
        }

        try {
            $this->connection->beginTransaction();

            $delete = $this->connection->prepare(
                'DELETE FROM perfil_permissoes WHERE perfil_id = :profile_id'
            );
            $delete->execute(['profile_id' => $profileId]);

            $insert = $this->connection->prepare(
                'INSERT INTO perfil_permissoes (perfil_id, permissao_id)
                 VALUES (:profile_id, :permission_id)'
            );

            foreach ($permissionIds as $permissionId) {
                $insert->execute([
                    'profile_id' => $profileId,
                    'permission_id' => $permissionId,
                ]);
            }

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    public function profileHasPermission(int $profileId, string $code): bool
    {
        $this->assertPositiveId($profileId);

        $statement = $this->connection->prepare(
            "SELECT COUNT(*)
               FROM perfil_permissoes pp
               INNER JOIN permissoes pe ON pe.id = pp.permissao_id
              WHERE pp.perfil_id = :profile_id
                AND pe.codigo = :code
                AND pe.status = 'ativo'"
        );
        $statement->execute([
            'profile_id' => $profileId,
            'code' => trim($code),
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID invalido.');
        }
    }
}
