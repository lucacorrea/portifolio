<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Exceptions\RepositoryException;
use PDO;
use PDOException;

final class GovernanceUserOverrideRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,bool> */
    public function moduleOverrides(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT modulo, permitido
                 FROM usuario_modulo_excecoes
                 WHERE usuario_id = :usuario_id'
            );
            $stmt->execute(['usuario_id' => $userId]);
            $result = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $module = trim((string) ($row['modulo'] ?? ''));
                if ($module !== '') {
                    $result[$module] = (bool) ($row['permitido'] ?? false);
                }
            }
            return $result;
        } catch (PDOException $exception) {
            $this->logCompatibility('moduleOverrides', $exception);
            return [];
        }
    }

    /** @return array<string,bool> */
    public function permissionOverrides(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT p.slug, upe.permitido
                 FROM usuario_permissao_excecoes upe
                 INNER JOIN permissoes p ON p.id = upe.permissao_id
                 WHERE upe.usuario_id = :usuario_id
                   AND p.ativo = 1'
            );
            $stmt->execute(['usuario_id' => $userId]);
            $result = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $slug = trim((string) ($row['slug'] ?? ''));
                if ($slug !== '') {
                    $result[$slug] = (bool) ($row['permitido'] ?? false);
                }
            }
            return $result;
        } catch (PDOException $exception) {
            $this->logCompatibility('permissionOverrides', $exception);
            return [];
        }
    }

    /** @return list<string> */
    public function levelPermissionSlugs(?int $levelId): array
    {
        if ($levelId === null || $levelId <= 0) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT p.slug
                 FROM nivel_permissoes np
                 INNER JOIN permissoes p ON p.id = np.permissao_id
                 WHERE np.nivel_id = :nivel_id
                   AND p.ativo = 1'
            );
            $stmt->execute(['nivel_id' => $levelId]);
            return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
        } catch (PDOException $exception) {
            throw $this->fail('levelPermissionSlugs', 'Falha ao consultar permissões do nível.', $exception);
        }
    }

    /** @return array<string,bool> */
    public function sectorModuleRules(?int $sectorId): array
    {
        if ($sectorId === null || $sectorId <= 0) {
            return [];
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT modulo, permitido
                 FROM setor_modulos
                 WHERE setor_id = :setor_id'
            );
            $stmt->execute(['setor_id' => $sectorId]);
            $result = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $module = trim((string) ($row['modulo'] ?? ''));
                if ($module !== '') {
                    $result[$module] = (bool) ($row['permitido'] ?? false);
                }
            }
            return $result;
        } catch (PDOException $exception) {
            $this->logCompatibility('sectorModuleRules', $exception);
            return [];
        }
    }

    public function sectorHasConfiguration(?int $sectorId): bool
    {
        if ($sectorId === null || $sectorId <= 0) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM setor_modulos WHERE setor_id = :setor_id LIMIT 1');
            $stmt->execute(['setor_id' => $sectorId]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $exception) {
            $this->logCompatibility('sectorHasConfiguration', $exception);
            return false;
        }
    }

    /** @param list<string> $permissionModules @return list<array<string,mixed>> */
    public function permissionCatalog(array $permissionModules): array
    {
        if ($permissionModules === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($permissionModules) as $index => $module) {
            $key = 'module_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $module;
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, nome, slug, descricao, modulo
                 FROM permissoes
                 WHERE ativo = 1
                   AND modulo IN (' . implode(',', $placeholders) . ')
                 ORDER BY modulo, nome, slug'
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            throw $this->fail('permissionCatalog', 'Falha ao consultar catálogo de permissões.', $exception);
        }
    }

    public function setModuleOverride(
        int $userId,
        string $module,
        ?bool $allowed,
        int $operatorId,
        string $reason,
    ): void {
        try {
            if ($allowed === null) {
                $stmt = $this->pdo->prepare(
                    'DELETE FROM usuario_modulo_excecoes
                     WHERE usuario_id = :usuario_id AND modulo = :modulo'
                );
                $stmt->execute(['usuario_id' => $userId, 'modulo' => $module]);
                return;
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO usuario_modulo_excecoes
                    (usuario_id, modulo, permitido, motivo, atualizado_por)
                 VALUES
                    (:usuario_id, :modulo, :permitido, :motivo, :atualizado_por)
                 ON DUPLICATE KEY UPDATE
                    permitido = VALUES(permitido),
                    motivo = VALUES(motivo),
                    atualizado_por = VALUES(atualizado_por),
                    atualizado_em = CURRENT_TIMESTAMP'
            );
            $stmt->execute([
                'usuario_id' => $userId,
                'modulo' => $module,
                'permitido' => $allowed ? 1 : 0,
                'motivo' => $reason,
                'atualizado_por' => $operatorId,
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('setModuleOverride', 'Falha ao alterar exceção de módulo.', $exception);
        }
    }

    public function setPermissionOverride(
        int $userId,
        string $permissionSlug,
        ?bool $allowed,
        int $operatorId,
        string $reason,
    ): void {
        try {
            if ($allowed === null) {
                $stmt = $this->pdo->prepare(
                    'DELETE upe
                     FROM usuario_permissao_excecoes upe
                     INNER JOIN permissoes p ON p.id = upe.permissao_id
                     WHERE upe.usuario_id = :usuario_id
                       AND p.slug = :slug'
                );
                $stmt->execute(['usuario_id' => $userId, 'slug' => $permissionSlug]);
                return;
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO usuario_permissao_excecoes
                    (usuario_id, permissao_id, permitido, motivo, atualizado_por)
                 SELECT :usuario_id, p.id, :permitido, :motivo, :atualizado_por
                 FROM permissoes p
                 WHERE p.slug = :slug AND p.ativo = 1
                 ON DUPLICATE KEY UPDATE
                    permitido = VALUES(permitido),
                    motivo = VALUES(motivo),
                    atualizado_por = VALUES(atualizado_por),
                    atualizado_em = CURRENT_TIMESTAMP'
            );
            $stmt->execute([
                'usuario_id' => $userId,
                'permitido' => $allowed ? 1 : 0,
                'motivo' => $reason,
                'atualizado_por' => $operatorId,
                'slug' => $permissionSlug,
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('setPermissionOverride', 'Falha ao alterar exceção de permissão.', $exception);
        }
    }

    private function logCompatibility(string $operation, PDOException $exception): void
    {
        Logger::application('Governance user override compatibility fallback.', [
            'repository' => self::class,
            'operation' => $operation,
            'code' => $exception->getCode(),
        ]);
    }

    private function fail(string $operation, string $message, PDOException $exception): RepositoryException
    {
        Logger::application('Governance user override repository failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);
        return new RepositoryException($message, 0, $exception);
    }
}
