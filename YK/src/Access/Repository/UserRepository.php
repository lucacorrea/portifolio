<?php
declare(strict_types=1);

namespace App\Access\Repository;

use App\Access\Entity\User;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?User
    {
        $this->assertPositiveId($id);

        $statement = $this->connection->prepare(
            'SELECT id, perfil_id, nome, usuario, email, senha_hash, telefone, status,
                    deve_alterar_senha, tentativas_falhas, bloqueado_ate, ultimo_acesso,
                    senha_alterada_em, criado_em, atualizado_em
               FROM usuarios
              WHERE id = :id
              LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();

        return $row === false ? null : User::fromArray($row);
    }

    public function findByUsername(string $username): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT id, perfil_id, nome, usuario, email, senha_hash, telefone, status,
                    deve_alterar_senha, tentativas_falhas, bloqueado_ate, ultimo_acesso,
                    senha_alterada_em, criado_em, atualizado_em
               FROM usuarios
              WHERE usuario = :username
              LIMIT 1'
        );
        $statement->execute(['username' => trim($username)]);

        $row = $statement->fetch();

        return $row === false ? null : User::fromArray($row);
    }

    public function findByEmail(string $email): ?User
    {
        $statement = $this->connection->prepare(
            'SELECT id, perfil_id, nome, usuario, email, senha_hash, telefone, status,
                    deve_alterar_senha, tentativas_falhas, bloqueado_ate, ultimo_acesso,
                    senha_alterada_em, criado_em, atualizado_em
               FROM usuarios
              WHERE email = :email
              LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);

        $row = $statement->fetch();

        return $row === false ? null : User::fromArray($row);
    }

    public function existsByUsername(string $username, ?int $ignoreId = null): bool
    {
        return $this->existsByField('usuario', trim($username), $ignoreId);
    }

    public function existsByEmail(string $email, ?int $ignoreId = null): bool
    {
        return $this->existsByField('email', strtolower(trim($email)), $ignoreId);
    }

    public function findByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return $this->findByEmail($identifier);
        }

        return $this->findByUsername($identifier);
    }

    public function create(User $user): int
    {
        $statement = $this->connection->prepare(
            'INSERT INTO usuarios (
                perfil_id, nome, usuario, email, senha_hash, telefone, status,
                deve_alterar_senha, tentativas_falhas, bloqueado_ate, ultimo_acesso,
                senha_alterada_em
            ) VALUES (
                :profile_id, :name, :username, :email, :password_hash, :phone, :status,
                :must_change_password, :failed_attempts, :locked_until, :last_access,
                :password_changed_at
            )'
        );

        $statement->execute([
            'profile_id' => $user->profileId(),
            'name' => $user->name(),
            'username' => $user->username(),
            'email' => $user->email(),
            'password_hash' => $user->passwordHash(),
            'phone' => $user->phone(),
            'status' => $user->status(),
            'must_change_password' => $user->mustChangePassword() ? 1 : 0,
            'failed_attempts' => $user->failedAttempts(),
            'locked_until' => $user->lockedUntil(),
            'last_access' => $user->lastAccess(),
            'password_changed_at' => $user->passwordChangedAt(),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function registerFailedAttempt(int $userId, int $attempts, ?DateTimeImmutable $lockedUntil): void
    {
        $this->assertPositiveId($userId);
        if ($attempts < 0) {
            throw new InvalidArgumentException('Tentativas falhas invalida.');
        }

        $statement = $this->connection->prepare(
            'UPDATE usuarios
                SET tentativas_falhas = :attempts,
                    bloqueado_ate = :locked_until
              WHERE id = :id'
        );
        $statement->execute([
            'id' => $userId,
            'attempts' => $attempts,
            'locked_until' => $lockedUntil?->format('Y-m-d H:i:s'),
        ]);
    }

    public function resetFailedAttempts(int $userId): void
    {
        $this->assertPositiveId($userId);

        $statement = $this->connection->prepare(
            'UPDATE usuarios
                SET tentativas_falhas = 0,
                    bloqueado_ate = NULL
              WHERE id = :id'
        );
        $statement->execute(['id' => $userId]);
    }

    public function updateLastAccess(int $userId, DateTimeImmutable $date): void
    {
        $this->assertPositiveId($userId);

        $statement = $this->connection->prepare(
            'UPDATE usuarios SET ultimo_acesso = :last_access WHERE id = :id'
        );
        $statement->execute([
            'id' => $userId,
            'last_access' => $date->format('Y-m-d H:i:s'),
        ]);
    }

    public function updatePasswordHash(int $userId, string $passwordHash, DateTimeImmutable $changedAt): void
    {
        $this->assertPositiveId($userId);
        if (trim($passwordHash) === '') {
            throw new InvalidArgumentException('Hash de senha invalido.');
        }

        $statement = $this->connection->prepare(
            'UPDATE usuarios
                SET senha_hash = :password_hash,
                    senha_alterada_em = :changed_at
              WHERE id = :id'
        );
        $statement->execute([
            'id' => $userId,
            'password_hash' => $passwordHash,
            'changed_at' => $changedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function countActiveAdministrators(): int
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(*)
               FROM usuarios u
               INNER JOIN perfis p ON p.id = u.perfil_id
              WHERE u.status = 'ativo'
                AND p.status = 'ativo'
                AND p.nome = 'Administrador'"
        );
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    public function countByProfile(int $profileId): int
    {
        $this->assertPositiveId($profileId);

        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM usuarios WHERE perfil_id = :profile_id'
        );
        $statement->execute(['profile_id' => $profileId]);

        return (int) $statement->fetchColumn();
    }

    private function existsByField(string $field, string $value, ?int $ignoreId): bool
    {
        if (!in_array($field, ['usuario', 'email'], true)) {
            throw new InvalidArgumentException('Campo de usuario invalido.');
        }

        $sql = "SELECT COUNT(*) FROM usuarios WHERE {$field} = :value";
        $params = ['value' => $value];

        if ($ignoreId !== null) {
            $this->assertPositiveId($ignoreId);
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('ID invalido.');
        }
    }
}
