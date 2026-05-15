<?php

declare(strict_types=1);

namespace App\Models;

final class Entrada extends Model
{
    protected string $table = 'entradas';

    public function listLatestByChurch(int $igrejaId, int $limit = 25): array
    {
        $limit = max(1, min($limit, 100));

        $statement = $this->db->prepare(
            "SELECT id,
                    tipo,
                    valor,
                    descricao,
                    contribuinte_nome,
                    forma_pagamento,
                    data_entrada,
                    criado_em
             FROM entradas
             WHERE igreja_id = :igreja_id
             ORDER BY data_entrada DESC, id DESC
             LIMIT {$limit}"
        );

        $statement->execute([
            'igreja_id' => $igrejaId,
        ]);

        return $statement->fetchAll();
    }

    public function currentMonthSummary(int $igrejaId): array
    {
        $statement = $this->db->prepare(
            'SELECT COALESCE(SUM(valor), 0) AS total,
                    COUNT(*) AS quantidade
             FROM entradas
             WHERE igreja_id = :igreja_id
               AND data_entrada >= DATE_FORMAT(CURRENT_DATE, \'%Y-%m-01\')
               AND data_entrada < DATE_ADD(DATE_FORMAT(CURRENT_DATE, \'%Y-%m-01\'), INTERVAL 1 MONTH)'
        );

        $statement->execute([
            'igreja_id' => $igrejaId,
        ]);

        $summary = $statement->fetch();

        return is_array($summary) ? $summary : [
            'total' => 0,
            'quantidade' => 0,
        ];
    }
}
