<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.empresa_id, u.nome, u.email, u.senha_hash, u.nivel, u.ativo,
                    e.nome AS empresa_nome, e.ativo AS empresa_ativa
             FROM usuarios u
             INNER JOIN empresas e ON e.id = u.empresa_id
             WHERE u.email = :email
             LIMIT 1'
        );

        $stmt->execute([':email' => mb_strtolower(trim($email))]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET ultimo_login_em = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function findByCompany(int $empresaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nome, email, nivel, ativo
             FROM usuarios
             WHERE empresa_id = :empresa_id
             ORDER BY nome ASC'
        );
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll();
    }

    public function auditLogin(?int $usuarioId, string $email, bool $success, string $reason): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO login_auditoria (usuario_id, email, ip, user_agent, sucesso, motivo)
             VALUES (:usuario_id, :email, :ip, :user_agent, :sucesso, :motivo)'
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':email' => mb_strtolower(trim($email)),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ':sucesso' => $success ? 1 : 0,
            ':motivo' => $reason,
        ]);
    }
}
