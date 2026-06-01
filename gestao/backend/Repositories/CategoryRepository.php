<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CategoryRepository
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

    public function findAll(int $empresaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nome
             FROM categorias
             WHERE empresa_id = :empresa_id AND ativo = 1
             ORDER BY nome ASC'
        );
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll();
    }

    public function findOrCreate(int $empresaId, string $name): int
    {
        $name = trim($name);

        $stmt = $this->db->prepare(
            'SELECT id
             FROM categorias
             WHERE empresa_id = :empresa_id AND nome = :nome
             LIMIT 1'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':nome' => $name,
        ]);

        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO categorias (empresa_id, nome)
             VALUES (:empresa_id, :nome)'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':nome' => $name,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
