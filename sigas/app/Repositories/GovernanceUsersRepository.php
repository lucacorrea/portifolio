<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use PDO;
use PDOException;

final class GovernanceUsersRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array{total:int,ativos:int,pendentes:int,bloqueados:int,inativos:int} */
    public function summary(): array
    {
        try {
            $row = $this->pdo->query(
                "SELECT
                    COUNT(*) AS total,
                    SUM(status = 'ativo') AS ativos,
                    SUM(status = 'pendente') AS pendentes,
                    SUM(status = 'bloqueado') AS bloqueados,
                    SUM(status = 'inativo') AS inativos
                 FROM usuarios
                 WHERE excluido_em IS NULL"
            )->fetch(PDO::FETCH_ASSOC);

            return [
                'total' => (int) ($row['total'] ?? 0),
                'ativos' => (int) ($row['ativos'] ?? 0),
                'pendentes' => (int) ($row['pendentes'] ?? 0),
                'bloqueados' => (int) ($row['bloqueados'] ?? 0),
                'inativos' => (int) ($row['inativos'] ?? 0),
            ];
        } catch (PDOException $exception) {
            $this->logFailure('summary', $exception);
            return ['total' => 0, 'ativos' => 0, 'pendentes' => 0, 'bloqueados' => 0, 'inativos' => 0];
        }
    }

    /** @return list<array<string,mixed>> */
    public function users(int $limit = 500): array
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
                    u.precisa_trocar_senha,
                    u.tentativas_login,
                    u.bloqueado_ate,
                    u.ultimo_login_em,
                    u.ultimo_login_ip,
                    u.aprovado_em,
                    u.rejeitado_em,
                    u.motivo_rejeicao,
                    u.observacao_interna,
                    u.versao_autorizacao,
                    u.criado_em,
                    u.atualizado_em,
                    s.nome AS setor_nome,
                    ss.nome AS setor_solicitado_nome,
                    n.nome AS nivel_nome,
                    n.slug AS nivel_slug,
                    ap.nome AS aprovado_por_nome,
                    rp.nome AS rejeitado_por_nome
                 FROM usuarios u
                 LEFT JOIN setores s ON s.id = u.setor_id
                 LEFT JOIN setores ss ON ss.id = u.setor_solicitado_id
                 LEFT JOIN niveis_acesso n ON n.id = u.nivel_id
                 LEFT JOIN usuarios ap ON ap.id = u.aprovado_por
                 LEFT JOIN usuarios rp ON rp.id = u.rejeitado_por
                 WHERE u.excluido_em IS NULL
                 ORDER BY
                    CASE u.status
                        WHEN 'pendente' THEN 1
                        WHEN 'bloqueado' THEN 2
                        WHEN 'ativo' THEN 3
                        WHEN 'inativo' THEN 4
                        WHEN 'rejeitado' THEN 5
                        ELSE 6
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

    private function logFailure(string $operation, PDOException $exception): void
    {
        Logger::application('Governance users repository operation failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);
    }
}
