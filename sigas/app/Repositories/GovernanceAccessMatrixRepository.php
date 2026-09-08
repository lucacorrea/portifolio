<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Exceptions\RepositoryException;
use PDO;
use PDOException;

final class GovernanceAccessMatrixRepository
{
    private const MODULES = [
        'kit_maternidade',
        'aluguel_social',
        'beneficios_eventuais',
        'governanca',
        'comida_mesa',
        'primeiro_emprego',
    ];

    private const EDITABLE_MODULES = [
        'kit_maternidade',
        'aluguel_social',
        'beneficios_eventuais',
        'comida_mesa',
        'primeiro_emprego',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array<string,mixed>> */
    public function activeLevels(): array
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
                    COUNT(DISTINCT u.id) AS usuarios
                 FROM niveis_acesso n
                 LEFT JOIN usuarios u
                    ON u.nivel_id = n.id
                   AND u.excluido_em IS NULL
                 WHERE n.ativo = 1
                 GROUP BY n.id, n.nome, n.slug, n.descricao, n.prioridade, n.ativo
                 ORDER BY n.prioridade, n.nome"
            );

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            throw $this->fail('activeLevels', 'Falha ao consultar níveis de acesso.', $exception);
        }
    }

    /** @return list<array<string,mixed>> */
    public function activeModulePermissions(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::MODULES), '?'));

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, nome, slug, descricao, modulo, ativo
                 FROM permissoes
                 WHERE ativo = 1
                   AND modulo IN ({$placeholders})
                 ORDER BY modulo, nome, id"
            );
            $stmt->execute(self::MODULES);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            throw $this->fail('activeModulePermissions', 'Falha ao consultar permissões da matriz.', $exception);
        }
    }

    /** @return list<int> */
    public function grantedPermissionIds(int $levelId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT np.permissao_id
                 FROM nivel_permissoes np
                 INNER JOIN permissoes p ON p.id = np.permissao_id
                 WHERE np.nivel_id = :nivel_id
                   AND p.ativo = 1'
            );
            $stmt->execute(['nivel_id' => $levelId]);

            return array_values(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        } catch (PDOException $exception) {
            throw $this->fail('grantedPermissionIds', 'Falha ao consultar permissões do nível.', $exception);
        }
    }

    /** @return list<string> */
    public function grantedEditablePermissionSlugs(int $levelId): array
    {
        $placeholders = implode(',', array_fill(0, count(self::EDITABLE_MODULES), '?'));
        $params = array_merge([$levelId], self::EDITABLE_MODULES);

        try {
            $stmt = $this->pdo->prepare(
                "SELECT p.slug
                 FROM nivel_permissoes np
                 INNER JOIN permissoes p ON p.id = np.permissao_id
                 WHERE np.nivel_id = ?
                   AND p.ativo = 1
                   AND p.modulo IN ({$placeholders})
                 ORDER BY p.modulo, p.slug"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return array_values(array_map('strval', $rows));
        } catch (PDOException $exception) {
            throw $this->fail('grantedEditablePermissionSlugs', 'Falha ao consultar a matriz atual.', $exception);
        }
    }

    /** @param list<int> $permissionIds */
    public function replaceEditablePermissions(int $levelId, array $permissionIds): void
    {
        $placeholders = implode(',', array_fill(0, count(self::EDITABLE_MODULES), '?'));
        $deleteParams = array_merge([$levelId], self::EDITABLE_MODULES);

        try {
            $delete = $this->pdo->prepare(
                "DELETE np
                 FROM nivel_permissoes np
                 INNER JOIN permissoes p ON p.id = np.permissao_id
                 WHERE np.nivel_id = ?
                   AND p.modulo IN ({$placeholders})"
            );
            $delete->execute($deleteParams);

            if ($permissionIds === []) {
                return;
            }

            $insert = $this->pdo->prepare(
                'INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
                 VALUES (:nivel_id, :permissao_id)'
            );

            foreach ($permissionIds as $permissionId) {
                $insert->execute([
                    'nivel_id' => $levelId,
                    'permissao_id' => $permissionId,
                ]);
            }
        } catch (PDOException $exception) {
            throw $this->fail('replaceEditablePermissions', 'Falha ao atualizar permissões do nível.', $exception);
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
            throw $this->fail('incrementAuthorizationVersionsForLevel', 'Falha ao invalidar autorizações do nível.', $exception);
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
                   AND su.revogada_em IS NULL
                   AND su.expira_em > UTC_TIMESTAMP()'
            );
            $stmt->execute(['nivel_id' => $levelId]);

            return $stmt->rowCount();
        } catch (PDOException $exception) {
            throw $this->fail('revokeActiveSessionsForLevel', 'Falha ao encerrar sessões afetadas.', $exception);
        }
    }

    /** @return list<string> */
    public static function editableModules(): array
    {
        return self::EDITABLE_MODULES;
    }

    private function fail(string $operation, string $message, PDOException $exception): RepositoryException
    {
        Logger::application('Governance access matrix repository failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);

        return new RepositoryException($message, 0, $exception);
    }
}
