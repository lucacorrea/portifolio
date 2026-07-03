<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\Exceptions\RepositoryException;
use App\Models\UserSession;
use DateTimeInterface;
use PDO;
use PDOException;

final class UserSessionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $userId, string $identifier, ?string $ip, ?string $userAgent, DateTimeInterface $expiresAt): int
    {
        // Sessões são persistidas em UTC para evitar divergências entre PHP e MySQL.
        $expiresAtUtc = gmdate('Y-m-d H:i:s', $expiresAt->getTimestamp());

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO sessoes_usuarios
                    (usuario_id, identificador, ip, user_agent, ultimo_acesso_em, expira_em, criado_em)
                 VALUES
                    (:usuario_id, :identificador, :ip, :user_agent, UTC_TIMESTAMP(), :expira_em, UTC_TIMESTAMP())'
            );
            $stmt->execute([
                'usuario_id' => $userId,
                'identificador' => hash('sha256', $identifier),
                'ip' => $ip,
                'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
                'expira_em' => $expiresAtUtc,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw $this->fail('create', 'Falha ao criar sessão.', $exception);
        }
    }

    public function findActiveByIdentifier(string $identifier): ?UserSession
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, usuario_id, identificador, ip, user_agent, ultimo_acesso_em, expira_em, revogada_em, criado_em
                 FROM sessoes_usuarios
                 WHERE identificador = :identificador AND revogada_em IS NULL AND expira_em > UTC_TIMESTAMP()
                 LIMIT 1'
            );
            $stmt->execute(['identificador' => hash('sha256', $identifier)]);
            $row = $stmt->fetch();

            return is_array($row) ? UserSession::fromArray($row) : null;
        } catch (PDOException $exception) {
            throw $this->fail('findActiveByIdentifier', 'Falha ao consultar sessão.', $exception);
        }
    }

    public function touch(string $identifier): void
    {
        $this->executeByIdentifier('UPDATE sessoes_usuarios SET ultimo_acesso_em = UTC_TIMESTAMP() WHERE identificador = :identificador', $identifier);
    }

    public function revoke(string $identifier): void
    {
        $this->executeByIdentifier('UPDATE sessoes_usuarios SET revogada_em = UTC_TIMESTAMP() WHERE identificador = :identificador', $identifier);
    }

    public function revokeAllForUser(int $userId): int
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE sessoes_usuarios SET revogada_em = UTC_TIMESTAMP() WHERE usuario_id = :usuario_id AND revogada_em IS NULL');
            $stmt->execute(['usuario_id' => $userId]);

            return $stmt->rowCount();
        } catch (PDOException $exception) {
            throw $this->fail('revokeAllForUser', 'Falha ao revogar sessões.', $exception);
        }
    }

    public function isRevoked(string $identifier): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT revogada_em FROM sessoes_usuarios WHERE identificador = :identificador LIMIT 1');
            $stmt->execute(['identificador' => hash('sha256', $identifier)]);
            $value = $stmt->fetchColumn();

            return $value !== false && $value !== null;
        } catch (PDOException $exception) {
            throw $this->fail('isRevoked', 'Falha ao verificar sessão.', $exception);
        }
    }

    public function deleteExpired(): int
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM sessoes_usuarios WHERE expira_em < UTC_TIMESTAMP()');
            $stmt->execute();

            return $stmt->rowCount();
        } catch (PDOException $exception) {
            throw $this->fail('deleteExpired', 'Falha ao remover sessões expiradas.', $exception);
        }
    }

    private function executeByIdentifier(string $sql, string $identifier): void
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['identificador' => hash('sha256', $identifier)]);
        } catch (PDOException $exception) {
            throw $this->fail('executeByIdentifier', 'Falha ao atualizar sessão.', $exception);
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
