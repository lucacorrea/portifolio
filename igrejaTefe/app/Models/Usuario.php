<?php

declare(strict_types=1);

namespace App\Models;

final class Usuario extends Model
{
    protected string $table = 'usuarios';

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
