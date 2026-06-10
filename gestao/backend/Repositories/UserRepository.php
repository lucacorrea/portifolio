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

        $stmt->execute([
            ':email' => mb_strtolower(trim($email)),
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findById(int $id, int $empresaId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, empresa_id, nome, email, telefone, nivel, ativo, ultimo_login_em, criado_em, atualizado_em
             FROM usuarios
             WHERE id = :id
               AND empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findByCompany(int $empresaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, empresa_id, nome, email, telefone, nivel, ativo, ultimo_login_em, criado_em, atualizado_em
             FROM usuarios
             WHERE empresa_id = :empresa_id
             ORDER BY ativo DESC, nome ASC'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll();
    }

    public function emailExists(string $email, int $empresaId, ?int $ignoreUserId = null): bool
    {
        $sql = 'SELECT COUNT(*) 
                FROM usuarios 
                WHERE email = :email 
                  AND empresa_id = :empresa_id';

        $params = [
            ':email' => mb_strtolower(trim($email)),
            ':empresa_id' => $empresaId,
        ];

        if ($ignoreUserId !== null) {
            $sql .= ' AND id <> :ignore_user_id';
            $params[':ignore_user_id'] = $ignoreUserId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(int $empresaId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (
                empresa_id,
                nome,
                email,
                senha_hash,
                telefone,
                nivel,
                ativo
             ) VALUES (
                :empresa_id,
                :nome,
                :email,
                :senha_hash,
                :telefone,
                :nivel,
                :ativo
             )'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':nome' => trim((string)$data['nome']),
            ':email' => mb_strtolower(trim((string)$data['email'])),
            ':senha_hash' => $data['senha_hash'],
            ':telefone' => $data['telefone'] ?? null,
            ':nivel' => $data['nivel'],
            ':ativo' => (int)($data['ativo'] ?? 1),
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $empresaId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET nome = :nome,
                 email = :email,
                 telefone = :telefone,
                 nivel = :nivel,
                 ativo = :ativo
             WHERE id = :id
               AND empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':nome' => trim((string)$data['nome']),
            ':email' => mb_strtolower(trim((string)$data['email'])),
            ':telefone' => $data['telefone'] ?? null,
            ':nivel' => $data['nivel'],
            ':ativo' => (int)($data['ativo'] ?? 1),
        ]);

        return $stmt->rowCount() >= 0;
    }

    public function updatePassword(int $id, int $empresaId, string $senhaHash): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET senha_hash = :senha_hash
             WHERE id = :id
               AND empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':senha_hash' => $senhaHash,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function activate(int $id, int $empresaId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET ativo = 1
             WHERE id = :id
               AND empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $id, int $empresaId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET ativo = 0
             WHERE id = :id
               AND empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios 
             SET ultimo_login_em = NOW() 
             WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $id,
        ]);
    }

    public function auditLogin(?int $usuarioId, string $email, bool $success, string $reason): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO login_auditoria (
                usuario_id,
                email,
                ip,
                user_agent,
                sucesso,
                motivo
             ) VALUES (
                :usuario_id,
                :email,
                :ip,
                :user_agent,
                :sucesso,
                :motivo
             )'
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