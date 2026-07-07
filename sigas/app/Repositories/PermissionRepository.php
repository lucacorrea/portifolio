<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Exceptions\RepositoryException;
use App\Models\Permission;
use PDO;
use PDOException;

final class PermissionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?Permission
    {
        return $this->findOne('id = :id', ['id' => $id]);
    }

    public function findBySlug(string $slug): ?Permission
    {
        return $this->findOne('slug = :slug', ['slug' => $slug]);
    }

    /** @return list<Permission> */
    public function findByLevelId(int $levelId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT p.id, p.nome, p.slug, p.descricao, p.modulo, p.ativo, p.criado_em
                 FROM permissoes p
                 INNER JOIN nivel_permissoes np ON np.permissao_id = p.id
                 WHERE np.nivel_id = :level_id AND p.ativo = 1
                 ORDER BY p.modulo, p.slug'
            );
            $stmt->execute(['level_id' => $levelId]);

            return array_map(static fn (array $row): Permission => Permission::fromArray($row), $stmt->fetchAll());
        } catch (PDOException $exception) {
            throw $this->fail('findByLevelId', 'Falha ao consultar permissões.', $exception);
        }
    }

    /** @return list<string> */
    public function findSlugsByLevelId(int $levelId): array
    {
        return array_map(static fn (Permission $permission): string => $permission->slug, $this->findByLevelId($levelId));
    }

    public function levelHasPermission(int $levelId, string $permissionSlug): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1
                 FROM permissoes p
                 INNER JOIN nivel_permissoes np ON np.permissao_id = p.id
                 WHERE np.nivel_id = :level_id AND p.slug = :slug AND p.ativo = 1
                 LIMIT 1'
            );
            $stmt->execute(['level_id' => $levelId, 'slug' => $permissionSlug]);

            return (bool) $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw $this->fail('levelHasPermission', 'Falha ao verificar permissão.', $exception);
        }
    }

    /** @param array<string, mixed> $params */
    private function findOne(string $where, array $params): ?Permission
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, nome, slug, descricao, modulo, ativo, criado_em
                 FROM permissoes
                 WHERE {$where}
                 LIMIT 1"
            );
            $stmt->execute($params);
            $row = $stmt->fetch();

            return is_array($row) ? Permission::fromArray($row) : null;
        } catch (PDOException $exception) {
            throw $this->fail('findOne', 'Falha ao consultar permissão.', $exception);
        }
    }

    private function fail(string $operation, string $message, PDOException $exception): RepositoryException
    {
        Logger::application('Repository operation failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);

        return new RepositoryException($message, 0, $exception);
    }
}
