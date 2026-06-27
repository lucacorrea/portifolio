<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UserCompanyRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findActiveCompaniesForUser(int $usuarioId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                ue.id AS membership_id,
                ue.usuario_id,
                ue.empresa_id,
                ue.nivel,
                ue.principal,
                ue.ativo AS vinculo_ativo,
                e.nome,
                e.nome_fantasia,
                e.cpf_cnpj,
                e.telefone,
                e.endereco,
                e.logo,
                e.empresa_pai_id,
                COALESCE(e.tipo, 'matriz') AS tipo,
                e.codigo,
                e.ativo AS empresa_ativa,
                e.criado_em,
                e.atualizado_em
             FROM usuario_empresas ue
             INNER JOIN empresas e ON e.id = ue.empresa_id
             WHERE ue.usuario_id = :usuario_id
               AND ue.ativo = 1
               AND e.ativo = 1
             ORDER BY ue.principal DESC, e.empresa_pai_id IS NOT NULL, e.nome ASC"
        );

        $stmt->execute([':usuario_id' => $usuarioId]);

        return $stmt->fetchAll();
    }

    public function findMembership(int $usuarioId, int $empresaId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT
                ue.id AS membership_id,
                ue.usuario_id,
                ue.empresa_id,
                ue.nivel,
                ue.principal,
                ue.ativo AS vinculo_ativo,
                u.nome AS usuario_nome,
                u.email,
                u.ativo AS usuario_ativo,
                u.empresa_id AS empresa_principal_id,
                e.nome AS empresa_nome,
                e.nome_fantasia,
                e.logo,
                e.empresa_pai_id,
                COALESCE(e.tipo, 'matriz') AS empresa_tipo,
                e.codigo,
                e.ativo AS empresa_ativa
             FROM usuario_empresas ue
             INNER JOIN usuarios u ON u.id = ue.usuario_id
             INNER JOIN empresas e ON e.id = ue.empresa_id
             WHERE ue.usuario_id = :usuario_id
               AND ue.empresa_id = :empresa_id
             LIMIT 1"
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':empresa_id' => $empresaId,
        ]);

        $membership = $stmt->fetch();

        return $membership ?: null;
    }

    public function userHasAccess(int $usuarioId, int $empresaId): bool
    {
        $membership = $this->findMembership($usuarioId, $empresaId);

        return $membership !== null
            && (int)$membership['usuario_ativo'] === 1
            && (int)$membership['vinculo_ativo'] === 1
            && (int)$membership['empresa_ativa'] === 1;
    }

    public function userIsAdmin(int $usuarioId, int $empresaId): bool
    {
        $membership = $this->findMembership($usuarioId, $empresaId);

        return $membership !== null
            && (int)$membership['usuario_ativo'] === 1
            && (int)$membership['vinculo_ativo'] === 1
            && (int)$membership['empresa_ativa'] === 1
            && (string)$membership['nivel'] === 'admin';
    }

    public function createMembership(
        int $usuarioId,
        int $empresaId,
        string $nivel,
        bool $principal = false
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO usuario_empresas (
                usuario_id,
                empresa_id,
                nivel,
                principal,
                ativo
             ) VALUES (
                :usuario_id,
                :empresa_id,
                :nivel,
                :principal,
                1
             )
             ON DUPLICATE KEY UPDATE
                nivel = VALUES(nivel),
                principal = VALUES(principal),
                ativo = 1'
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':empresa_id' => $empresaId,
            ':nivel' => $nivel,
            ':principal' => $principal ? 1 : 0,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateRole(int $usuarioId, int $empresaId, string $nivel): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuario_empresas
             SET nivel = :nivel
             WHERE usuario_id = :usuario_id
               AND empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':empresa_id' => $empresaId,
            ':nivel' => $nivel,
        ]);

        return $stmt->rowCount() >= 0;
    }

    public function activate(int $usuarioId, int $empresaId): bool
    {
        return $this->setActive($usuarioId, $empresaId, true);
    }

    public function deactivate(int $usuarioId, int $empresaId): bool
    {
        return $this->setActive($usuarioId, $empresaId, false);
    }

    public function setPrimary(int $usuarioId, int $empresaId): void
    {
        $this->db->prepare(
            'UPDATE usuario_empresas
             SET principal = 0
             WHERE usuario_id = :usuario_id'
        )->execute([':usuario_id' => $usuarioId]);

        $this->db->prepare(
            'UPDATE usuario_empresas
             SET principal = 1
             WHERE usuario_id = :usuario_id
               AND empresa_id = :empresa_id
             LIMIT 1'
        )->execute([
            ':usuario_id' => $usuarioId,
            ':empresa_id' => $empresaId,
        ]);
    }

    public function countActiveMemberships(int $usuarioId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM usuario_empresas ue
             INNER JOIN empresas e ON e.id = ue.empresa_id
             WHERE ue.usuario_id = :usuario_id
               AND ue.ativo = 1
               AND e.ativo = 1'
        );

        $stmt->execute([':usuario_id' => $usuarioId]);

        return (int)$stmt->fetchColumn();
    }

    public function findUsersByCompany(int $empresaId): array
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

        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll();
    }

    private function setActive(int $usuarioId, int $empresaId, bool $active): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE usuario_empresas
             SET ativo = :ativo
             WHERE usuario_id = :usuario_id
               AND empresa_id = :empresa_id
             LIMIT 1'
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':empresa_id' => $empresaId,
            ':ativo' => $active ? 1 : 0,
        ]);

        return $stmt->rowCount() > 0;
    }
}
