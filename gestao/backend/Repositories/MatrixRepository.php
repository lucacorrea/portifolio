<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class MatrixRepository
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

    public function listMatrices(): array
    {
        $stmt = $this->db->query(
            "SELECT
                e.id, e.empresa_pai_id, COALESCE(e.tipo, 'matriz') AS tipo, e.codigo,
                e.nome, e.nome_fantasia, e.cpf_cnpj, e.telefone, e.endereco, e.logo,
                e.admin_principal_usuario_id, e.ativo, e.criado_em, e.atualizado_em,
                u.nome AS admin_nome, u.email AS admin_email, u.ativo AS admin_ativo,
                (
                    SELECT COUNT(*)
                    FROM empresas f
                    WHERE f.empresa_pai_id = e.id
                      AND COALESCE(f.tipo, 'matriz') = 'loja'
                ) AS filiais_total
             FROM empresas e
             LEFT JOIN usuarios u ON u.id = e.admin_principal_usuario_id
             WHERE e.empresa_pai_id IS NULL
               AND COALESCE(e.tipo, 'matriz') = 'matriz'
             ORDER BY e.ativo DESC, e.nome_fantasia ASC, e.nome ASC"
        );

        return $stmt->fetchAll();
    }

    public function findMatrixById(int $matrizId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT
                e.id, e.empresa_pai_id, COALESCE(e.tipo, 'matriz') AS tipo, e.codigo,
                e.nome, e.nome_fantasia, e.cpf_cnpj, e.telefone, e.endereco, e.logo,
                e.admin_principal_usuario_id, e.ativo, e.criado_em, e.atualizado_em,
                u.nome AS admin_nome, u.email AS admin_email, u.ativo AS admin_ativo
             FROM empresas e
             LEFT JOIN usuarios u ON u.id = e.admin_principal_usuario_id
             WHERE e.id = :id
               AND e.empresa_pai_id IS NULL
               AND COALESCE(e.tipo, 'matriz') = 'matriz'
             LIMIT 1"
        );

        $stmt->execute([':id' => $matrizId]);
        $matrix = $stmt->fetch();

        return $matrix ?: null;
    }

    public function createMatrix(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO empresas (
                empresa_pai_id, tipo, codigo, nome, nome_fantasia, cpf_cnpj,
                telefone, endereco, logo, admin_principal_usuario_id, ativo
             ) VALUES (
                NULL, 'matriz', :codigo, :nome, :nome_fantasia, :cpf_cnpj,
                :telefone, :endereco, :logo, NULL, 1
             )"
        );

        $stmt->execute([
            ':codigo' => $data['codigo'] ?? null,
            ':nome' => $data['nome'],
            ':nome_fantasia' => $data['nome_fantasia'] ?? null,
            ':cpf_cnpj' => $data['cpf_cnpj'] ?? null,
            ':telefone' => $data['telefone'] ?? null,
            ':endereco' => $data['endereco'] ?? null,
            ':logo' => $data['logo'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateMatrix(int $matrizId, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE empresas
             SET codigo = :codigo,
                 nome = :nome,
                 nome_fantasia = :nome_fantasia,
                 cpf_cnpj = :cpf_cnpj,
                 telefone = :telefone,
                 endereco = :endereco
             WHERE id = :id
               AND empresa_pai_id IS NULL
               AND COALESCE(tipo, 'matriz') = 'matriz'
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $matrizId,
            ':codigo' => $data['codigo'] ?? null,
            ':nome' => $data['nome'],
            ':nome_fantasia' => $data['nome_fantasia'] ?? null,
            ':cpf_cnpj' => $data['cpf_cnpj'] ?? null,
            ':telefone' => $data['telefone'] ?? null,
            ':endereco' => $data['endereco'] ?? null,
        ]);

        return $stmt->rowCount() >= 0;
    }

    public function updateLogo(int $matrizId, ?string $logo): void
    {
        $stmt = $this->db->prepare(
            "UPDATE empresas
             SET logo = :logo
             WHERE id = :id
               AND empresa_pai_id IS NULL
               AND COALESCE(tipo, 'matriz') = 'matriz'
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $matrizId,
            ':logo' => $logo,
        ]);
    }

    public function setMatrixActive(int $matrizId, bool $ativo): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE empresas
             SET ativo = :ativo
             WHERE id = :id
               AND empresa_pai_id IS NULL
               AND COALESCE(tipo, 'matriz') = 'matriz'
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $matrizId,
            ':ativo' => $ativo ? 1 : 0,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function assignPrimaryAdmin(int $matrizId, int $usuarioId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE empresas
             SET admin_principal_usuario_id = :usuario_id
             WHERE id = :id
               AND empresa_pai_id IS NULL
               AND COALESCE(tipo, 'matriz') = 'matriz'
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $matrizId,
            ':usuario_id' => $usuarioId,
        ]);
    }
}
