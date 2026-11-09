<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Exceptions\RepositoryException;
use PDO;
use PDOException;

final class GovernanceUserAdminRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function registerApproval(int $userId, int $approverId, string $observation): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE usuarios
                 SET aprovado_por = :aprovado_por,
                     aprovado_em = NOW(),
                     rejeitado_por = NULL,
                     rejeitado_em = NULL,
                     motivo_rejeicao = NULL,
                     observacao_interna = CASE
                        WHEN observacao_interna IS NULL OR TRIM(observacao_interna) = '' THEN :observacao_nova
                        ELSE CONCAT(observacao_interna, CHAR(10), :observacao_append)
                     END
                 WHERE id = :id
                   AND excluido_em IS NULL"
            );
            $stmt->execute([
                'id' => $userId,
                'aprovado_por' => $approverId,
                'observacao_nova' => $observation,
                'observacao_append' => $observation,
            ]);
        } catch (PDOException $exception) {
            throw $this->fail('registerApproval', 'Falha ao registrar aprovação do usuário.', $exception);
        }
    }

    public function clearLoginLock(int $userId): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE usuarios
                 SET tentativas_login = 0,
                     bloqueado_ate = NULL
                 WHERE id = :id
                   AND excluido_em IS NULL'
            );
            $stmt->execute(['id' => $userId]);
        } catch (PDOException $exception) {
            throw $this->fail('clearLoginLock', 'Falha ao limpar bloqueio temporário.', $exception);
        }
    }

    private function fail(string $operation, string $message, PDOException $exception): RepositoryException
    {
        Logger::application('Governance user admin repository operation failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);

        return new RepositoryException($message, 0, $exception);
    }
}
