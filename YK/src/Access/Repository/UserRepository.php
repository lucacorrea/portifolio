<?php
declare(strict_types=1);

namespace App\Access\Repository;

use App\Access\Entity\User;
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
