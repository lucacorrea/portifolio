<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use PDO;
use PDOException;

final class GovernanceRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{
     *   usuarios_total:int,
     *   usuarios_ativos:int,
     *   usuarios_pendentes:int,
     *   usuarios_bloqueados:int,
     *   niveis_ativos:int,
     *   setores_ativos:int,
     *   permissoes_ativas:int,
     *   sessoes_ativas:int
     * }
     */
    public function summary(): array
    {
        return [
            'usuarios_total' => $this->scalar(
                'SELECT COUNT(*) FROM usuarios WHERE excluido_em IS NULL'
            ),
            'usuarios_ativos' => $this->scalar(
                "SELECT COUNT(*) FROM usuarios WHERE status = 'ativo' AND excluido_em IS NULL"
            ),
            'usuarios_pendentes' => $this->scalar(
                "SELECT COUNT(*) FROM usuarios WHERE status = 'pendente' AND excluido_em IS NULL"
            ),
            'usuarios_bloqueados' => $this->scalar(
                "SELECT COUNT(*) FROM usuarios WHERE status = 'bloqueado' AND excluido_em IS NULL"
            ),
            'niveis_ativos' => $this->scalar(
                'SELECT COUNT(*) FROM niveis_acesso WHERE ativo = 1'
            ),
            'setores_ativos' => $this->scalar(
                'SELECT COUNT(*) FROM setores WHERE ativo = 1 AND excluido_em IS NULL'
            ),
            'permissoes_ativas' => $this->scalar(
                'SELECT COUNT(*) FROM permissoes WHERE ativo = 1'
            ),
            'sessoes_ativas' => $this->scalar(
                'SELECT COUNT(*) FROM sessoes_usuarios
                 WHERE revogada_em IS NULL AND expira_em > UTC_TIMESTAMP()'
            ),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function users(int $limit = 250): array
    {
        $limit = max(1, min($limit, 500));

        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    u.id,
                    u.nome,
                    u.cpf,
                    u.matricula,
                    u.cargo,
                    u.email,
                    u.telefone,
                    u.status,
                    u.ultimo_login_em,
                    u.ultimo_login_ip,
                    u.criado_em,
                    s.nome AS setor_nome,
                    n.nome AS nivel_nome,
                    n.slug AS nivel_slug
                 FROM usuarios u
                 LEFT JOIN setores s ON s.id = u.setor_id
                 LEFT JOIN niveis_acesso n ON n.id = u.nivel_id
                 WHERE u.excluido_em IS NULL
                 ORDER BY
                    CASE u.status
                        WHEN 'pendente' THEN 1
                        WHEN 'bloqueado' THEN 2
                        WHEN 'ativo' THEN 3
                        WHEN 'inativo' THEN 4
                        ELSE 5
                    END,
                    u.nome
                 LIMIT :limit"
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            $this->logFailure('users', $exception);
            return [];
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function levels(): array
    {
        return $this->rows(
            "SELECT
                n.id,
                n.nome,
                n.slug,
                n.descricao,
                n.prioridade,
                n.ativo,
                COUNT(DISTINCT u.id) AS usuarios,
                COUNT(DISTINCT np.permissao_id) AS permissoes
             FROM niveis_acesso n
             LEFT JOIN usuarios u
                ON u.nivel_id = n.id
               AND u.excluido_em IS NULL
             LEFT JOIN nivel_permissoes np
                ON np.nivel_id = n.id
             GROUP BY
                n.id,
                n.nome,
                n.slug,
                n.descricao,
                n.prioridade,
                n.ativo
             ORDER BY n.prioridade, n.nome"
        );
    }

    /**
     * Catálogo completo de permissões com os níveis aos quais estão vinculadas.
     *
     * @return list<array<string,mixed>>
     */
    public function permissions(): array
    {
        return $this->rows(
            "SELECT
                p.id,
                p.nome,
                p.slug,
                p.descricao,
                p.modulo,
                p.ativo,
                GROUP_CONCAT(
                    DISTINCT n.nome
                    ORDER BY n.prioridade
                    SEPARATOR ', '
                ) AS niveis
             FROM permissoes p
             LEFT JOIN nivel_permissoes np
                ON np.permissao_id = p.id
             LEFT JOIN niveis_acesso n
                ON n.id = np.nivel_id
               AND n.ativo = 1
             GROUP BY
                p.id,
                p.nome,
                p.slug,
                p.descricao,
                p.modulo,
                p.ativo
             ORDER BY p.modulo, p.nome"
        );
    }

    /**
     * Permissões efetivamente concedidas a cada nível ativo.
     *
     * @return list<array<string,mixed>>
     */
    public function levelPermissions(): array
    {
        return $this->rows(
            "SELECT
                n.id AS nivel_id,
                n.nome AS nivel_nome,
                n.slug AS nivel_slug,
                n.prioridade,
                p.id AS permissao_id,
                p.nome AS permissao_nome,
                p.slug AS permissao_slug,
                p.modulo
             FROM niveis_acesso n
             LEFT JOIN nivel_permissoes np
                ON np.nivel_id = n.id
             LEFT JOIN permissoes p
                ON p.id = np.permissao_id
               AND p.ativo = 1
             WHERE n.ativo = 1
             ORDER BY n.prioridade, p.modulo, p.nome"
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function sectors(): array
    {
        return $this->rows(
            "SELECT
                s.id,
                s.nome,
                s.slug,
                s.descricao,
                s.ativo,
                COUNT(DISTINCT u.id) AS usuarios
             FROM setores s
             LEFT JOIN usuarios u
                ON u.setor_id = s.id
               AND u.excluido_em IS NULL
             WHERE s.excluido_em IS NULL
             GROUP BY s.id, s.nome, s.slug, s.descricao, s.ativo
             ORDER BY s.nome"
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function sectorModules(): array
    {
        return $this->rows(
            "SELECT
                s.id AS setor_id,
                s.nome AS setor_nome,
                s.slug AS setor_slug,
                sm.modulo,
                sm.permitido
             FROM setores s
             LEFT JOIN setor_modulos sm
                ON sm.setor_id = s.id
             WHERE s.ativo = 1
               AND s.excluido_em IS NULL
             ORDER BY s.nome, sm.modulo"
        );
    }

    private function scalar(string $sql): int
    {
        try {
            $value = $this->pdo->query($sql)->fetchColumn();
            return $value === false ? 0 : (int) $value;
        } catch (PDOException $exception) {
            $this->logFailure('scalar', $exception);
            return 0;
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function rows(string $sql): array
    {
        try {
            $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (PDOException $exception) {
            $this->logFailure('rows', $exception);
            return [];
        }
    }

    private function logFailure(string $operation, PDOException $exception): void
    {
        Logger::application('Governance repository operation failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);
    }
}
