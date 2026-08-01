<?php

declare(strict_types=1);

namespace App\Admin\Repository;

use PDO;

final class AdminAccessRepository
{
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Registra a entrada administrativa em uma empresa.
     */
    public function open(
        int $companyId,
        int $userId,
        string $ip,
        string $userAgent,
        string $reason,
        string $sessionKey
    ): int {
        $statement = $this->connection->prepare(
            'INSERT INTO empresa_acessos_administrativos (
                empresa_id,
                usuario_id,
                motivo,
                ip,
                user_agent,
                sessao_chave,
                iniciado_em
            ) VALUES (
                :empresa_id,
                :usuario_id,
                :motivo,
                :ip,
                :user_agent,
                :sessao_chave,
                NOW()
            )'
        );

        $statement->execute([
            'empresa_id' => $companyId,
            'usuario_id' => $userId,
            'motivo' => $reason,
            'ip' => $ip !== ''
                ? $ip
                : null,
            'user_agent' => $userAgent !== ''
                ? $userAgent
                : null,
            'sessao_chave' => $sessionKey,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Encerra todos os acessos ainda abertos da mesma sessão.
     *
     * Isso impede que o suporte permaneça com duas empresas
     * simultaneamente abertas na mesma sessão administrativa.
     */
    public function closeOpenForSession(
        int $userId,
        string $sessionKey
    ): void {
        $statement = $this->connection->prepare(
            'UPDATE empresa_acessos_administrativos
                SET encerrado_em = NOW()
              WHERE usuario_id = :usuario_id
                AND sessao_chave = :sessao_chave
                AND encerrado_em IS NULL'
        );

        $statement->execute([
            'usuario_id' => $userId,
            'sessao_chave' => $sessionKey,
        ]);
    }

    /**
     * Verifica se o contexto administrativo ainda é válido.
     *
     * @return array<string, mixed>|null
     */
    public function findOpenAuthorized(
        int $accessId,
        int $userId,
        int $companyId,
        string $sessionBindingHash
    ): ?array {
        $statement = $this->connection->prepare(
            "SELECT
                a.id,
                a.empresa_id,
                a.usuario_id,
                a.iniciado_em,
                e.uuid,
                COALESCE(
                    NULLIF(e.nome_fantasia, ''),
                    e.razao_social
                ) AS empresa_nome
             FROM empresa_acessos_administrativos AS a
             INNER JOIN empresas AS e
                ON e.id = a.empresa_id
               AND e.status IN (
                    'ativo',
                    'pendente',
                    'inativo'
               )
             WHERE a.id = :access_id
               AND a.usuario_id = :user_id
               AND a.empresa_id = :company_id
               AND a.sessao_chave = :session_key
               AND a.encerrado_em IS NULL
               AND a.iniciado_em >= DATE_SUB(
                    NOW(),
                    INTERVAL 4 HOUR
               )
             LIMIT 1"
        );

        $statement->execute([
            'access_id' => $accessId,
            'user_id' => $userId,
            'company_id' => $companyId,
            'session_key' => $sessionBindingHash,
        ]);

        $row = $statement->fetch();

        return is_array($row)
            ? $row
            : null;
    }

    /**
     * Encerra um acesso pertencente ao usuário e à sessão atual.
     */
    public function closeOwned(
        int $accessId,
        int $userId,
        string $sessionBindingHash
    ): void {
        $statement = $this->connection->prepare(
            'UPDATE empresa_acessos_administrativos
                SET encerrado_em = NOW()
              WHERE id = :access_id
                AND usuario_id = :user_id
                AND sessao_chave = :session_key
                AND encerrado_em IS NULL'
        );

        $statement->execute([
            'access_id' => $accessId,
            'user_id' => $userId,
            'session_key' => $sessionBindingHash,
        ]);
    }

    /**
     * Encerra um acesso inválido ou expirado pelo vínculo
     * entre usuário e empresa.
     */
    public function closeForUserAndCompany(
        int $accessId,
        int $userId,
        int $companyId
    ): void {
        $statement = $this->connection->prepare(
            'UPDATE empresa_acessos_administrativos
                SET encerrado_em = NOW()
              WHERE id = :access_id
                AND usuario_id = :user_id
                AND empresa_id = :company_id
                AND encerrado_em IS NULL'
        );

        $statement->execute([
            'access_id' => $accessId,
            'user_id' => $userId,
            'company_id' => $companyId,
        ]);
    }

    /**
     * Lista o histórico administrativo.
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     total: int
     * }
     */
    public function paginate(
        int $page,
        int $perPage
    ): array {
        $total = (int) $this->connection
            ->query(
                'SELECT COUNT(*)
                   FROM empresa_acessos_administrativos'
            )
            ->fetchColumn();

        $offset = max(
            0,
            ($page - 1) * $perPage
        );

        $statement = $this->connection->prepare(
            'SELECT
                a.id,
                a.empresa_id,
                a.usuario_id,
                a.ip,
                a.motivo,
                a.iniciado_em,
                a.encerrado_em,
                e.nome_fantasia,
                e.razao_social,
                u.nome AS usuario
             FROM empresa_acessos_administrativos AS a
             LEFT JOIN empresas AS e
                ON e.id = a.empresa_id
             LEFT JOIN usuarios AS u
                ON u.id = a.usuario_id
             ORDER BY a.id DESC
             LIMIT :limit
             OFFSET :offset'
        );

        $statement->bindValue(
            ':limit',
            $perPage,
            PDO::PARAM_INT
        );

        $statement->bindValue(
            ':offset',
            $offset,
            PDO::PARAM_INT
        );

        $statement->execute();

        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
        ];
    }
}