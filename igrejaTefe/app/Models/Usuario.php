<?php

declare(strict_types=1);

namespace App\Models;

final class Usuario extends Model
{
    protected string $table = 'usuarios';

    public function listByChurch(int $igrejaId): array
    {
        $statement = $this->db->prepare(
            'SELECT id,
                    nome,
                    email,
                    papel,
                    ativo,
                    ultimo_login_em,
                    criado_em,
                    atualizado_em
             FROM usuarios
             WHERE igreja_id = :igreja_id
             ORDER BY ativo DESC, nome ASC'
        );

        $statement->execute([
            'igreja_id' => $igrejaId,
        ]);

        return $statement->fetchAll();
    }

    public function findByChurch(int $id, int $igrejaId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id,
                    igreja_id,
                    nome,
                    email,
                    papel,
                    ativo,
                    ultimo_login_em,
                    criado_em,
                    atualizado_em
             FROM usuarios
             WHERE id = :id
               AND igreja_id = :igreja_id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $id,
            'igreja_id' => $igrejaId,
        ]);

        $usuario = $statement->fetch();

        return is_array($usuario) ? $usuario : null;
    }

    public function emailExists(string $email, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM usuarios WHERE email = :email';
        $params = [
            'email' => strtolower(trim($email)),
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO usuarios (
                igreja_id,
                nome,
                email,
                senha_hash,
                papel,
                ativo
            ) VALUES (
                :igreja_id,
                :nome,
                :email,
                :senha_hash,
                :papel,
                1
            )'
        );

        $statement->execute([
            'igreja_id' => $data['igreja_id'],
            'nome' => $data['nome'],
            'email' => strtolower(trim((string) $data['email'])),
            'senha_hash' => $data['senha_hash'],
            'papel' => $data['papel'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateProfile(int $id, int $igrejaId, array $data): void
    {
        $statement = $this->db->prepare(
            'UPDATE usuarios
             SET nome = :nome,
                 email = :email,
                 papel = :papel,
                 ativo = :ativo,
                 atualizado_em = CURRENT_TIMESTAMP
             WHERE id = :id
               AND igreja_id = :igreja_id'
        );

        $statement->execute([
            'id' => $id,
            'igreja_id' => $igrejaId,
            'nome' => $data['nome'],
            'email' => strtolower(trim((string) $data['email'])),
            'papel' => $data['papel'],
            'ativo' => (int) $data['ativo'],
        ]);
    }

    public function setActive(int $id, int $igrejaId, bool $active): void
    {
        $statement = $this->db->prepare(
            'UPDATE usuarios
             SET ativo = :ativo,
                 atualizado_em = CURRENT_TIMESTAMP
             WHERE id = :id
               AND igreja_id = :igreja_id'
        );

        $statement->execute([
            'id' => $id,
            'igreja_id' => $igrejaId,
            'ativo' => $active ? 1 : 0,
        ]);
    }

    public function findActiveByEmail(string $email): ?array
    {
        $statement = $this->db->prepare(
            'SELECT u.id,
                    u.igreja_id,
                    u.nome,
                    u.email,
                    u.senha_hash,
                    u.papel,
                    u.ativo,
                    i.nome AS igreja_nome,
                    NULL AS igreja_logo_url
             FROM usuarios u
             INNER JOIN igrejas i ON i.id = u.igreja_id
             WHERE u.email = :email
               AND u.ativo = 1
               AND i.status = \'ativa\'
             LIMIT 1'
        );

        $statement->execute([
            'email' => strtolower(trim($email)),
        ]);

        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function updateLastLogin(int $userId, int $igrejaId): void
    {
        $statement = $this->db->prepare(
            'UPDATE usuarios
             SET ultimo_login_em = CURRENT_TIMESTAMP
             WHERE id = :id
               AND igreja_id = :igreja_id'
        );

        $statement->execute([
            'id' => $userId,
            'igreja_id' => $igrejaId,
        ]);
    }

    public function updatePasswordHash(int $userId, int $igrejaId, string $hash): void
    {
        $statement = $this->db->prepare(
            'UPDATE usuarios
             SET senha_hash = :senha_hash
             WHERE id = :id
               AND igreja_id = :igreja_id'
        );

        $statement->execute([
            'id' => $userId,
            'igreja_id' => $igrejaId,
            'senha_hash' => $hash,
        ]);
    }
}
