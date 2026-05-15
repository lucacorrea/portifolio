<?php

declare(strict_types=1);

namespace App\Models;

final class Usuario extends Model
{
    protected string $table = 'usuarios';

    public function findActiveByEmailAndIgreja(int $igrejaId, string $email): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, igreja_id, nome, email, senha_hash, papel, ativo
             FROM usuarios
             WHERE igreja_id = :igreja_id
               AND email = :email
               AND ativo = 1
             LIMIT 1'
        );

        $statement->execute([
            'igreja_id' => $igrejaId,
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
