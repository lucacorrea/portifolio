<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CompanyRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function connection(): PDO
    {
        return $this->db;
    }

    public function findById(int $empresaId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nome, nome_fantasia, cpf_cnpj, telefone, endereco, logo, atualizado_em
             FROM empresas
             WHERE id = :id AND ativo = 1
             LIMIT 1'
        );
        $stmt->execute([':id' => $empresaId]);

        $company = $stmt->fetch();

        return $company ?: null;
    }

    public function updateProfile(int $empresaId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE empresas
             SET nome = :nome,
                 telefone = :telefone,
                 endereco = :endereco
             WHERE id = :id'
        );

        $stmt->execute([
            ':id' => $empresaId,
            ':nome' => $data['nome'],
            ':telefone' => $data['telefone'] ?: null,
            ':endereco' => $data['endereco'] ?: null,
        ]);
    }
}
