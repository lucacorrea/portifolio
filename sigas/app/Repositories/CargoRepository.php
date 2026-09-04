<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Exceptions\RepositoryException;
use PDO;
use PDOException;

final class CargoRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function schemaReady(): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'cargos'");
            return (bool) $stmt->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        try {
            $stmt = $this->pdo->query(
                "SELECT
                    c.id,
                    c.nome,
                    c.slug,
                    c.descricao,
                    c.ativo,
                    c.criado_em,
                    c.atualizado_em,
                    COUNT(u.id) AS usuarios
                 FROM cargos c
                 LEFT JOIN usuarios u
                   ON u.excluido_em IS NULL
                  AND LOWER(TRIM(COALESCE(u.cargo, ''))) = LOWER(TRIM(c.nome))
                 WHERE c.excluido_em IS NULL
                 GROUP BY c.id, c.nome, c.slug, c.descricao, c.ativo, c.criado_em, c.atualizado_em
                 ORDER BY c.ativo DESC, c.nome"
            );

            return $stmt->fetchAll() ?: [];
        } catch (PDOException $exception) {
            throw $this->fail('all', 'Falha ao consultar cargos.', $exception);
        }
    }

    /** @return list<array{id:int,nome:string,slug:string}> */
    public function activeOptions(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        try {
            $stmt = $this->pdo->query(
                "SELECT id, nome, slug
                 FROM cargos
                 WHERE ativo = 1 AND excluido_em IS NULL
                 ORDER BY nome"
            );

            return array_map(
                static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'nome' => (string) $row['nome'],
                    'slug' => (string) $row['slug'],
                ],
                $stmt->fetchAll() ?: []
            );
        } catch (PDOException $exception) {
            throw $this->fail('activeOptions', 'Falha ao consultar cargos ativos.', $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, nome, slug, descricao, ativo, criado_em, atualizado_em
                 FROM cargos
                 WHERE id = :id AND excluido_em IS NULL
                 LIMIT 1"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();

            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            throw $this->fail('findById', 'Falha ao consultar cargo.', $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function findActiveById(int $id): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, nome, slug, descricao, ativo
                 FROM cargos
                 WHERE id = :id AND ativo = 1 AND excluido_em IS NULL
                 LIMIT 1"
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch();

            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            throw $this->fail('findActiveById', 'Falha ao consultar cargo ativo.', $exception);
        }
    }

    public function nameExists(string $name, ?int $ignoreId = null): bool
    {
        return $this->exists('nome', trim($name), $ignoreId);
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return $this->exists('slug', trim($slug), $ignoreId);
    }

    public function create(string $name, string $slug, ?string $description): int
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO cargos (nome, slug, descricao, ativo, criado_em)
                 VALUES (:nome, :slug, :descricao, 1, NOW())"
            );
            $stmt->execute([
                'nome' => $name,
                'slug' => $slug,
                'descricao' => $description,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw $this->fail('create', 'Falha ao cadastrar cargo.', $exception);
        }
    }

    public function update(int $id, string $name, string $slug, ?string $description): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE cargos
                 SET nome = :nome, slug = :slug, descricao = :descricao
                 WHERE id = :id AND excluido_em IS NULL"
            );
            $stmt->execute([
                'id' => $id,
                'nome' => $name,
                'slug' => $slug,
                'descricao' => $description,
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('update', 'Falha ao atualizar cargo.', $exception);
        }
    }

    public function setActive(int $id, bool $active): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE cargos
                 SET ativo = :ativo
                 WHERE id = :id AND excluido_em IS NULL"
            );
            $stmt->execute([
                'id' => $id,
                'ativo' => $active ? 1 : 0,
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('setActive', 'Falha ao alterar situação do cargo.', $exception);
        }
    }

    public function renameUsers(string $oldName, string $newName): int
    {
        if (mb_strtolower(trim($oldName)) === mb_strtolower(trim($newName))) {
            return 0;
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE usuarios
                 SET cargo = :novo
                 WHERE excluido_em IS NULL
                   AND LOWER(TRIM(COALESCE(cargo, ''))) = LOWER(TRIM(:antigo))"
            );
            $stmt->execute([
                'novo' => $newName,
                'antigo' => $oldName,
            ]);

            return $stmt->rowCount();
        } catch (PDOException $exception) {
            throw $this->fail('renameUsers', 'Falha ao sincronizar o cargo dos usuários.', $exception);
        }
    }

    private function exists(string $column, string $value, ?int $ignoreId): bool
    {
        if (!$this->schemaReady() || $value === '' || !in_array($column, ['nome', 'slug'], true)) {
            return false;
        }

        try {
            $sql = "SELECT 1 FROM cargos WHERE {$column} = :value AND excluido_em IS NULL";
            $params = ['value' => $value];

            if ($ignoreId !== null) {
                $sql .= ' AND id <> :ignore_id';
                $params['ignore_id'] = $ignoreId;
            }

            $sql .= ' LIMIT 1';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (bool) $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw $this->fail('exists', 'Falha ao verificar duplicidade de cargo.', $exception);
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
