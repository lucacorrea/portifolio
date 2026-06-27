<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UserRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function connection(): PDO
    {
        return $this->db;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->findIdentityByEmail($email);
    }

    public function findIdentityById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.empresa_id, u.nome, u.email, u.senha_hash, u.nivel, u.ativo,
                    u.telefone, u.ultimo_login_em, u.criado_em, u.atualizado_em
             FROM usuarios u
             WHERE u.id = :id
             LIMIT 1'
        );

        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findIdentityByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.empresa_id, u.nome, u.email, u.senha_hash, u.nivel, u.ativo,
                    u.ultimo_login_em, u.criado_em, u.atualizado_em
             FROM usuarios u
             WHERE u.email = :email
             LIMIT 1'
        );

        $stmt->execute([
            ':email' => mb_strtolower(trim($email)),
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findExistingOwnersByEmails(array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map(
            static fn (string $email): string => mb_strtolower(trim($email)),
            $emails
        ))));

        if ($emails === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($emails as $index => $email) {
            $key = ':email_' . $index;
            $placeholders[] = $key;
            $params[$key] = $email;
        }

        $stmt = $this->db->prepare(
            'SELECT id, empresa_id, nome, email, telefone, nivel, ativo
             FROM usuarios
             WHERE email IN (' . implode(',', $placeholders) . ')
             ORDER BY email ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findById(int $id, int $empresaId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
                u.id,
                u.empresa_id AS empresa_principal_id,
                ue.empresa_id,
                u.nome,
                u.email,
                u.telefone,
                ue.nivel,
                ue.ativo,
                u.ativo AS usuario_ativo,
                ue.principal,
                u.ultimo_login_em,
                u.criado_em,
                u.atualizado_em
             FROM usuarios u
             INNER JOIN usuario_empresas ue ON ue.usuario_id = u.id
             WHERE u.id = :id
               AND ue.empresa_id = :empresa_id
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
            'SELECT
                u.id,
                u.empresa_id AS empresa_principal_id,
                ue.empresa_id,
                u.nome,
                u.email,
                u.telefone,
                ue.nivel,
                ue.ativo,
                u.ativo AS usuario_ativo,
                ue.principal,
                u.ultimo_login_em,
                u.criado_em,
                u.atualizado_em
             FROM usuario_empresas ue
             INNER JOIN usuarios u ON u.id = ue.usuario_id
             WHERE ue.empresa_id = :empresa_id
             ORDER BY ue.ativo DESC, u.nome ASC'
        );

        $stmt->execute([
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll();
    }

    public function emailExists(string $email, int $empresaId, ?int $ignoreUserId = null): bool
    {
        return $this->emailExistsGlobally($email, $ignoreUserId);
    }

    public function emailExistsGlobally(string $email, ?int $ignoreUserId = null): bool
    {
        $sql = 'SELECT COUNT(*) 
                FROM usuarios 
                WHERE email = :email';

        $params = [
            ':email' => mb_strtolower(trim($email)),
        ];

        if ($ignoreUserId !== null) {
            $sql .= ' AND id <> :ignore_user_id';
            $params[':ignore_user_id'] = $ignoreUserId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function isLinkedToCompany(int $usuarioId, int $empresaId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM usuario_empresas
             WHERE usuario_id = :usuario_id
               AND empresa_id = :empresa_id'
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':empresa_id' => $empresaId,
        ]);

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

    public function updatePrimaryCompany(int $id, int $empresaId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET empresa_id = :empresa_id,
                 nivel = CASE WHEN nivel = "admin" THEN nivel ELSE nivel END
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);
    }

    public function update(int $id, int $empresaId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuarios
             SET nome = :nome,
                 email = :email,
                 telefone = :telefone
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $id,
            ':nome' => trim((string)$data['nome']),
            ':email' => mb_strtolower(trim((string)$data['email'])),
            ':telefone' => $data['telefone'] ?? null,
        ]);

        $this->db->prepare(
            'UPDATE usuario_empresas
             SET nivel = :nivel,
                 ativo = :ativo
             WHERE usuario_id = :usuario_id
               AND empresa_id = :empresa_id
             LIMIT 1'
        )->execute([
            ':usuario_id' => $id,
            ':empresa_id' => $empresaId,
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
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $id,
            ':senha_hash' => $senhaHash,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function activate(int $id, int $empresaId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuario_empresas
             SET ativo = 1
             WHERE usuario_id = :id
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
            'UPDATE usuario_empresas
             SET ativo = 0
             WHERE usuario_id = :id
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
