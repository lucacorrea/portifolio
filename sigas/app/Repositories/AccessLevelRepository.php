<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Exceptions\RepositoryException;
use App\Models\AccessLevel;
use PDO;
use PDOException;

final class AccessLevelRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?AccessLevel
    {
        return $this->findOne('id = :id', ['id' => $id]);
    }

    public function findBySlug(string $slug): ?AccessLevel
    {
        return $this->findOne('slug = :slug', ['slug' => $slug]);
    }

    public function findActiveById(int $id): ?AccessLevel
    {
        return $this->findOne('id = :id AND ativo = 1', ['id' => $id]);
    }

    /** @return list<AccessLevel> */
    public function allActive(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT id, nome, slug, descricao, prioridade, ativo, criado_em, atualizado_em
                 FROM niveis_acesso
                 WHERE ativo = 1
                 ORDER BY prioridade, nome'
            );

            return array_map(static fn (array $row): AccessLevel => AccessLevel::fromArray($row), $stmt->fetchAll());
        } catch (PDOException $exception) {
            throw $this->fail('allActive', 'Falha ao consultar níveis.', $exception);
        }
    }

    public function existsActive(int $id): bool
    {
        return $this->findActiveById($id) instanceof AccessLevel;
    }

    /** @param array<string, mixed> $params */
    private function findOne(string $where, array $params): ?AccessLevel
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, nome, slug, descricao, prioridade, ativo, criado_em, atualizado_em
                 FROM niveis_acesso
                 WHERE {$where}
                 LIMIT 1"
            );
            $stmt->execute($params);
            $row = $stmt->fetch();

            return is_array($row) ? AccessLevel::fromArray($row) : null;
        } catch (PDOException $exception) {
            throw $this->fail('findOne', 'Falha ao consultar nível.', $exception);
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
