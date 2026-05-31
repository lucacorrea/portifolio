<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class SettingRepository
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

    public function getAll(int $empresaId): array
    {
        $stmt = $this->db->prepare(
            'SELECT chave, valor
             FROM configuracoes
             WHERE empresa_id = :empresa_id'
        );
        $stmt->execute([':empresa_id' => $empresaId]);

        $settings = [];

        foreach ($stmt->fetchAll() as $row) {
            $settings[(string)$row['chave']] = $row['valor'];
        }

        return $settings;
    }

    public function upsertMany(int $empresaId, array $settings): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO configuracoes (empresa_id, chave, valor)
             VALUES (:empresa_id, :chave, :valor)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );

        foreach ($settings as $key => $value) {
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':chave' => $key,
                ':valor' => $value,
            ]);
        }
    }
}
