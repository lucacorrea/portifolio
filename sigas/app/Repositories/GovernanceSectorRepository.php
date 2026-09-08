<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Config\AccessModuleCatalog;
use App\Core\Logger;
use App\Exceptions\RepositoryException;
use PDO;
use PDOException;

final class GovernanceSectorRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        try {
            $rows = $this->pdo->query(
                "SELECT
                    s.id,
                    s.nome,
                    s.slug,
                    s.descricao,
                    s.ativo,
                    s.criado_em,
                    s.atualizado_em,
                    COUNT(DISTINCT u.id) AS usuarios
                 FROM setores s
                 LEFT JOIN usuarios u
                   ON u.setor_id = s.id
                  AND u.excluido_em IS NULL
                 WHERE s.excluido_em IS NULL
                 GROUP BY s.id, s.nome, s.slug, s.descricao, s.ativo, s.criado_em, s.atualizado_em
                 ORDER BY s.ativo DESC, s.nome"
            )->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            throw $this->fail('all', 'Falha ao consultar setores.', $exception);
        }
    }

    /** @return array<string,mixed>|null */
    public function findById(int $sectorId): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, nome, slug, descricao, ativo, criado_em, atualizado_em
                 FROM setores
                 WHERE id = :id AND excluido_em IS NULL
                 LIMIT 1'
            );
            $stmt->execute(['id' => $sectorId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (PDOException $exception) {
            throw $this->fail('findById', 'Falha ao consultar setor.', $exception);
        }
    }

    /** @return array<int,list<string>> */
    public function moduleRules(): array
    {
        $modules = array_keys(AccessModuleCatalog::operational());
        if ($modules === []) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($modules), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT setor_id, modulo, permitido
                 FROM setor_modulos
                 WHERE modulo IN ({$placeholders})
                 ORDER BY setor_id, modulo"
            );
            $stmt->execute($modules);
            $result = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ((int) ($row['permitido'] ?? 0) !== 1) {
                    continue;
                }
                $sectorId = (int) ($row['setor_id'] ?? 0);
                $module = (string) ($row['modulo'] ?? '');
                if ($sectorId > 0 && $module !== '') {
                    $result[$sectorId][] = $module;
                }
            }

            return $result;
        } catch (PDOException $exception) {
            throw $this->fail('moduleRules', 'Falha ao consultar módulos por setor.', $exception);
        }
    }

    public function slugExists(string $slug): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM setores WHERE slug = :slug LIMIT 1');
            $stmt->execute(['slug' => $slug]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $exception) {
            throw $this->fail('slugExists', 'Falha ao validar identificador do setor.', $exception);
        }
    }

    public function create(string $name, string $slug, ?string $description): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO setores (nome, slug, descricao, ativo, criado_em)
                 VALUES (:nome, :slug, :descricao, 1, NOW())'
            );
            $stmt->execute([
                'nome' => $name,
                'slug' => $slug,
                'descricao' => $description,
            ]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw $this->fail('create', 'Falha ao criar setor.', $exception);
        }
    }

    public function update(int $sectorId, string $name, ?string $description): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE setores
                 SET nome = :nome, descricao = :descricao
                 WHERE id = :id AND excluido_em IS NULL'
            );
            $stmt->execute([
                'id' => $sectorId,
                'nome' => $name,
                'descricao' => $description,
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('update', 'Falha ao atualizar setor.', $exception);
        }
    }

    public function setActive(int $sectorId, bool $active): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE setores SET ativo = :ativo WHERE id = :id AND excluido_em IS NULL'
            );
            $stmt->execute(['id' => $sectorId, 'ativo' => $active ? 1 : 0]);
        } catch (PDOException $exception) {
            throw $this->fail('setActive', 'Falha ao alterar situação do setor.', $exception);
        }
    }

    /** @param list<string> $allowedModules */
    public function replaceModuleRules(int $sectorId, array $allowedModules, int $operatorId): void
    {
        $allowed = array_fill_keys($allowedModules, true);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO setor_modulos (setor_id, modulo, permitido, atualizado_por, criado_em)
                 VALUES (:setor_id, :modulo, :permitido, :atualizado_por, NOW())
                 ON DUPLICATE KEY UPDATE
                    permitido = VALUES(permitido),
                    atualizado_por = VALUES(atualizado_por)'
            );

            foreach (array_keys(AccessModuleCatalog::operational()) as $module) {
                $stmt->execute([
                    'setor_id' => $sectorId,
                    'modulo' => $module,
                    'permitido' => isset($allowed[$module]) ? 1 : 0,
                    'atualizado_por' => $operatorId,
                ]);
            }
        } catch (PDOException $exception) {
            throw $this->fail('replaceModuleRules', 'Falha ao atualizar módulos do setor.', $exception);
        }
    }

    public function incrementAuthorizationVersionsForSector(int $sectorId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE usuarios
                 SET versao_autorizacao = versao_autorizacao + 1
                 WHERE setor_id = :setor_id AND excluido_em IS NULL'
            );
            $stmt->execute(['setor_id' => $sectorId]);
            return $stmt->rowCount();
        } catch (PDOException $exception) {
            throw $this->fail('incrementAuthorizationVersionsForSector', 'Falha ao invalidar autorizações do setor.', $exception);
        }
    }

    public function revokeActiveSessionsForSector(int $sectorId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE sessoes_usuarios su
                 INNER JOIN usuarios u ON u.id = su.usuario_id
                 SET su.revogada_em = UTC_TIMESTAMP()
                 WHERE u.setor_id = :setor_id
                   AND u.excluido_em IS NULL
                   AND su.revogada_em IS NULL
                   AND su.expira_em > UTC_TIMESTAMP()'
            );
            $stmt->execute(['setor_id' => $sectorId]);
            return $stmt->rowCount();
        } catch (PDOException $exception) {
            throw $this->fail('revokeActiveSessionsForSector', 'Falha ao encerrar sessões do setor.', $exception);
        }
    }

    private function fail(string $operation, string $message, PDOException $exception): RepositoryException
    {
        Logger::application('Governance sector repository failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);

        return new RepositoryException($message, 0, $exception);
    }
}
