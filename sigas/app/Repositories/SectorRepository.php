<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Exceptions\RepositoryException;
use App\Models\Sector;
use PDO;
use PDOException;

final class SectorRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?Sector
    {
        return $this->findOne('id = :id', ['id' => $id]);
    }

    public function findBySlug(string $slug): ?Sector
    {
        return $this->findOne('slug = :slug', ['slug' => $slug]);
    }

    public function findActiveById(int $id): ?Sector
    {
        return $this->findOne('id = :id AND ativo = 1', ['id' => $id]);
    }

    /** @return list<Sector> */
    public function allActive(): array
    {
        try {
            $stmt = $this->pdo->query(
                'SELECT id, nome, slug, descricao, ativo, criado_em, atualizado_em, excluido_em
                 FROM setores
                 WHERE ativo = 1 AND excluido_em IS NULL
                 ORDER BY nome'
            );

            return array_map(static fn (array $row): Sector => Sector::fromArray($row), $stmt->fetchAll());
        } catch (PDOException $exception) {
            throw $this->fail('allActive', 'Falha ao consultar setores.', $exception);
        }
    }

    public function existsActive(int $id): bool
    {
        return $this->findActiveById($id) instanceof Sector;
    }

    /** @param array<string, mixed> $params */
    private function findOne(string $where, array $params): ?Sector
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, nome, slug, descricao, ativo, criado_em, atualizado_em, excluido_em
                 FROM setores
                 WHERE {$where} AND excluido_em IS NULL
                 LIMIT 1"
            );
            $stmt->execute($params);
            $row = $stmt->fetch();

            return is_array($row) ? Sector::fromArray($row) : null;
        } catch (PDOException $exception) {
            throw $this->fail('findOne', 'Falha ao consultar setor.', $exception);
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
