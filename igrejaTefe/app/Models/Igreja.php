<?php

declare(strict_types=1);

namespace App\Models;

final class Igreja extends Model
{
    protected string $table = 'igrejas';

    public function findActiveSummary(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id,
                    nome,
                    NULL AS logo_url
             FROM igrejas
             WHERE id = :id
               AND status = \'ativa\'
             LIMIT 1'
        );

        $statement->execute([
            'id' => $id,
        ]);

        $igreja = $statement->fetch();

        return is_array($igreja) ? $igreja : null;
    }
}
