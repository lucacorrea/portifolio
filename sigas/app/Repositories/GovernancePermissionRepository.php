<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Exceptions\RepositoryException;
use PDO;
use PDOException;

final class GovernancePermissionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->rows(
            "SELECT
                p.id,
                p.nome,
                p.slug,
                p.descricao,
                p.modulo,
                p.ativo,
                p.criado_em,
                COUNT(DISTINCT np.nivel_id) AS niveis_count,
                GROUP_CONCAT(DISTINCT n.nome ORDER BY n.prioridade SEPARATOR ', ') AS niveis
             FROM permissoes p
             LEFT JOIN nivel_permissoes np ON np.permissao_id = p.id
             LEFT JOIN niveis_acesso n ON n.id = np.nivel_id
             GROUP BY p.id, p.nome, p.slug, p.descricao, p.modulo, p.ativo, p.criado_em
             ORDER BY p.modulo, p.nome"
        );
    }

    /** @return array<string,mixed>|null */
    public function findById(int $permissionId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    p.id,
                    p.nome,
                    p.slug,
                    p.descricao,
                    p.modulo,
                    p.ativo,
                    p.criado_em,
                    COUNT(DISTINCT np.nivel_id) AS niveis_count,
                    GROUP_CONCAT(DISTINCT n.nome ORDER BY n.prioridade SEPARATOR ', ') AS niveis
                 FROM permissoes p
                 LEFT JOIN nivel_permissoes np ON np.permissao_id = p.id
                 LEFT JOIN niveis_acesso n ON n.id = np.nivel_id
                 WHERE p.id = :id
                 GROUP BY p.id, p.nome, p.slug, p.descricao, p.modulo, p.ativo, p.criado_em
                 LIMIT 1"
            );
            $stmt->execute(['id' => $permissionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            throw $this->fail('findById', 'Falha ao consultar permissão.', $exception);
        }
    }

    public function slugExists(string $slug): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM permissoes WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw $this->fail('slugExists', 'Falha ao verificar identificador da permissão.', $exception);
        }
    }

    public function create(string $name, string $slug, ?string $description, string $module): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO permissoes (nome, slug, descricao, modulo, ativo, criado_em)
                 VALUES (:nome, :slug, :descricao, :modulo, 1, NOW())'
            );
            $stmt->execute([
                'nome' => $name,
                'slug' => $slug,
                'descricao' => $description,
                'modulo' => $module,
            ]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw $this->fail('create', 'Falha ao criar permissão.', $exception);
        }
    }

    public function updateMetadata(int $permissionId, string $name, ?string $description): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE permissoes
                 SET nome = :nome, descricao = :descricao
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => $permissionId,
                'nome' => $name,
                'descricao' => $description,
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('updateMetadata', 'Falha ao atualizar permissão.', $exception);
        }
    }

    public function setActive(int $permissionId, bool $active): void
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE permissoes SET ativo = :ativo WHERE id = :id');
            $stmt->execute([
                'id' => $permissionId,
                'ativo' => $active ? 1 : 0,
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('setActive', 'Falha ao alterar situação da permissão.', $exception);
        }
    }

    public function incrementAuthorizationVersionsForPermission(int $permissionId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE usuarios u
                 INNER JOIN nivel_permissoes np ON np.nivel_id = u.nivel_id
                 SET u.versao_autorizacao = u.versao_autorizacao + 1
                 WHERE np.permissao_id = :permissao_id
                   AND u.excluido_em IS NULL'
            );
            $stmt->execute(['permissao_id' => $permissionId]);
            return $stmt->rowCount();
        } catch (PDOException $exception) {
            throw $this->fail('incrementAuthorizationVersionsForPermission', 'Falha ao invalidar autorizações afetadas.', $exception);
        }
    }

    public function revokeActiveSessionsForPermission(int $permissionId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE sessoes_usuarios su
                 INNER JOIN usuarios u ON u.id = su.usuario_id
                 INNER JOIN nivel_permissoes np ON np.nivel_id = u.nivel_id
                 SET su.revogada_em = UTC_TIMESTAMP()
                 WHERE np.permissao_id = :permissao_id
                   AND su.revogada_em IS NULL'
            );
            $stmt->execute(['permissao_id' => $permissionId]);
            return $stmt->rowCount();
        } catch (PDOException $exception) {
            throw $this->fail('revokeActiveSessionsForPermission', 'Falha ao revogar sessões afetadas.', $exception);
        }
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $sql): array
    {
        try {
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            throw $this->fail('rows', 'Falha ao listar permissões.', $exception);
        }
    }

    private function fail(string $operation, string $message, PDOException $exception): RepositoryException
    {
        Logger::application('Governance permission repository operation failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);

        return new RepositoryException($message, 0, $exception);
    }
}
