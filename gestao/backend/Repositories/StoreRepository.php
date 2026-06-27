<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class StoreRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $empresaId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, empresa_pai_id, COALESCE(tipo, 'matriz') AS tipo, codigo,
                    nome, nome_fantasia, cpf_cnpj, telefone, endereco, logo, ativo,
                    criado_em, atualizado_em
             FROM empresas
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute([':id' => $empresaId]);
        $company = $stmt->fetch();

        return $company ?: null;
    }

    public function findChildren(int $empresaPaiId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, empresa_pai_id, COALESCE(tipo, 'matriz') AS tipo, codigo,
                    nome, nome_fantasia, cpf_cnpj, telefone, endereco, logo, ativo,
                    criado_em, atualizado_em
             FROM empresas
             WHERE empresa_pai_id = :empresa_pai_id
             ORDER BY ativo DESC, nome_fantasia ASC, nome ASC"
        );

        $stmt->execute([':empresa_pai_id' => $empresaPaiId]);

        return $stmt->fetchAll();
    }

    public function findChild(int $empresaPaiId, int $lojaId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, empresa_pai_id, COALESCE(tipo, 'matriz') AS tipo, codigo,
                    nome, nome_fantasia, cpf_cnpj, telefone, endereco, logo, ativo,
                    criado_em, atualizado_em
             FROM empresas
             WHERE id = :id
               AND empresa_pai_id = :empresa_pai_id
             LIMIT 1"
        );

        $stmt->execute([
            ':id' => $lojaId,
            ':empresa_pai_id' => $empresaPaiId,
        ]);

        $store = $stmt->fetch();

        return $store ?: null;
    }

    public function create(int $empresaPaiId, array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO empresas (
                empresa_pai_id,
                tipo,
                codigo,
                nome,
                nome_fantasia,
                cpf_cnpj,
                telefone,
                endereco,
                ativo
             ) VALUES (
                :empresa_pai_id,
                'loja',
                :codigo,
                :nome,
                :nome_fantasia,
                :cpf_cnpj,
                :telefone,
                :endereco,
                1
             )"
        );

        $stmt->execute([
            ':empresa_pai_id' => $empresaPaiId,
            ':codigo' => $data['codigo'] ?? null,
            ':nome' => $data['nome'],
            ':nome_fantasia' => $data['nome_fantasia'] ?? null,
            ':cpf_cnpj' => $data['cpf_cnpj'] ?? null,
            ':telefone' => $data['telefone'] ?? null,
            ':endereco' => $data['endereco'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $empresaPaiId, int $lojaId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE empresas
             SET codigo = :codigo,
                 nome = :nome,
                 nome_fantasia = :nome_fantasia,
                 cpf_cnpj = :cpf_cnpj,
                 telefone = :telefone,
                 endereco = :endereco
             WHERE id = :id
               AND empresa_pai_id = :empresa_pai_id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $lojaId,
            ':empresa_pai_id' => $empresaPaiId,
            ':codigo' => $data['codigo'] ?? null,
            ':nome' => $data['nome'],
            ':nome_fantasia' => $data['nome_fantasia'] ?? null,
            ':cpf_cnpj' => $data['cpf_cnpj'] ?? null,
            ':telefone' => $data['telefone'] ?? null,
            ':endereco' => $data['endereco'] ?? null,
        ]);

        return $stmt->rowCount() >= 0;
    }

    public function updateLogo(int $lojaId, ?string $logo): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE empresas
             SET logo = :logo
             WHERE id = :id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $lojaId,
            ':logo' => $logo,
        ]);

        return $stmt->rowCount() >= 0;
    }

    public function setActive(int $empresaPaiId, int $lojaId, bool $ativo): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE empresas
             SET ativo = :ativo
             WHERE id = :id
               AND empresa_pai_id = :empresa_pai_id
             LIMIT 1'
        );

        $stmt->execute([
            ':id' => $lojaId,
            ':empresa_pai_id' => $empresaPaiId,
            ':ativo' => $ativo ? 1 : 0,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function codeExists(int $empresaPaiId, string $codigo, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM empresas
                WHERE empresa_pai_id = :empresa_pai_id
                  AND codigo = :codigo';
        $params = [
            ':empresa_pai_id' => $empresaPaiId,
            ':codigo' => $codigo,
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params[':ignore_id'] = $ignoreId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->fetchColumn() > 0;
    }
}
