<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Exceptions\RepositoryException;
use PDO;
use PDOException;

final class GovernanceAccessLevelRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT
                    n.id,
                    n.nome,
                    n.slug,
                    n.descricao,
                    n.prioridade,
                    n.ativo,
                    n.criado_em,
                    n.atualizado_em,
                    COUNT(DISTINCT u.id) AS usuarios,
                    COUNT(DISTINCT np.permissao_id) AS permissoes
                 FROM niveis_acesso n
                 LEFT JOIN usuarios u
                    ON u.nivel_id = n.id
                   AND u.excluido_em IS NULL
                 LEFT JOIN nivel_permissoes np
                    ON np.nivel_id = n.id
                 GROUP BY
                    n.id, n.nome, n.slug, n.descricao, n.prioridade, n.ativo,
                    n.criado_em, n.atualizado_em
                 ORDER BY n.prioridade, n.nome"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            throw $this->fail('all', 'Falha ao consultar níveis de acesso.', $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, nome, slug, descricao, prioridade, ativo, criado_em, atualizado_em
                 FROM niveis_acesso
                 WHERE id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            throw $this->fail('findById', 'Falha ao consultar nível de acesso.', $exception);
        }
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        try {
            $sql = 'SELECT 1 FROM niveis_acesso WHERE slug = :slug';
            $params = ['slug' => $slug];
            if ($ignoreId !== null) {
                $sql .= ' AND id <> :ignore_id';
                $params['ignore_id'] = $ignoreId;
            }
            $sql .= ' LIMIT 1';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw $this->fail('slugExists', 'Falha ao validar identificador do nível.', $exception);
        }
    }

    public function create(string $name, string $slug, ?string $description, int $priority): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO niveis_acesso (nome, slug, descricao, prioridade, ativo, criado_em)
                 VALUES (:nome, :slug, :descricao, :prioridade, 1, NOW())'
            );
            $stmt->execute([
                'nome' => $name,
                'slug' => $slug,
                'descricao' => $description,
                'prioridade' => $priority,
            ]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw $this->fail('create', 'Falha ao criar nível de acesso.', $exception);
        }
    }

    public function update(int $id, string $name, ?string $description, int $priority): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE niveis_acesso
                 SET nome = :nome,
                     descricao = :descricao,
                     prioridade = :prioridade,
                     atualizado_em = NOW()
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $id,
                'nome' => $name,
                'descricao' => $description,
                'prioridade' => $priority,
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('update', 'Falha ao atualizar nível de acesso.', $exception);
        }
    }

    public function setActive(int $id, bool $active): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE niveis_acesso
                 SET ativo = :ativo, atualizado_em = NOW()
                 WHERE id = :id'
            );
            $stmt->execute(['id' => $id, 'ativo' => $active ? 1 : 0]);
        } catch (PDOException $exception) {
            throw $this->fail('setActive', 'Falha ao alterar situação do nível.', $exception);
        }
    }

    public function incrementAuthorizationVersionsForLevel(int $levelId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE usuarios
                 SET versao_autorizacao = versao_autorizacao + 1
                 WHERE nivel_id = :nivel_id
                   AND excluido_em IS NULL'
            );
            $stmt->execute(['nivel_id' => $levelId]);
            return $stmt->rowCount();
        } catch (PDOException $exception) {
            throw $this->fail('incrementAuthorizationVersionsForLevel', 'Falha ao atualizar autorizações do nível.', $exception);
        }
    }

    public function revokeActiveSessionsForLevel(int $levelId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE sessoes_usuarios su
                 INNER JOIN usuarios u ON u.id = su.usuario_id
                 SET su.revogada_em = UTC_TIMESTAMP()
                 WHERE u.nivel_id = :nivel_id
                   AND u.excluido_em IS NULL
                   AND su.revogada_em IS NULL'
            );
            $stmt->execute(['nivel_id' => $levelId]);
            return $stmt->rowCount();
        } catch (PDOException $exception) {
            throw $this->fail('revokeActiveSessionsForLevel', 'Falha ao encerrar sessões vinculadas ao nível.', $exception);
        }
    }

    private function fail(string $operation, string $message, PDOException $exception): RepositoryException
    {
        Logger::application('Governance access level repository failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);

        return new RepositoryException($message, 0, $exception);
    }
}
