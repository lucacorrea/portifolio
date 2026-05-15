<?php

declare(strict_types=1);

namespace App\Models;

final class Saida extends Model
{
    protected string $table = 'saidas';

    public function listLatestByChurch(int $igrejaId, int $limit = 25): array
    {
        $limit = max(1, min($limit, 100));

        $statement = $this->db->prepare(
            "SELECT s.id,
                    s.categoria_id,
                    s.valor,
                    s.descricao,
                    s.fornecedor,
                    s.forma_pagamento,
                    s.data_saida,
                    s.criado_em,
                    c.nome AS categoria_nome,
                    c.cor AS categoria_cor
             FROM saidas s
             INNER JOIN categorias c
                ON c.id = s.categoria_id
               AND c.igreja_id = s.igreja_id
             WHERE s.igreja_id = :igreja_id
             ORDER BY s.data_saida DESC, s.id DESC
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
             FROM saidas
             WHERE igreja_id = :igreja_id
               AND data_saida >= DATE_FORMAT(CURRENT_DATE, \'%Y-%m-01\')
               AND data_saida < DATE_ADD(DATE_FORMAT(CURRENT_DATE, \'%Y-%m-01\'), INTERVAL 1 MONTH)'
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

    public function categorySummaryByChurch(int $igrejaId): array
    {
        $statement = $this->db->prepare(
            'SELECT c.nome,
                    c.cor,
                    COALESCE(SUM(s.valor), 0) AS total
             FROM saidas s
             INNER JOIN categorias c
                ON c.id = s.categoria_id
               AND c.igreja_id = s.igreja_id
             WHERE s.igreja_id = :igreja_id
             GROUP BY c.id, c.nome, c.cor
             ORDER BY total DESC
             LIMIT 8'
        );

        $statement->execute([
            'igreja_id' => $igrejaId,
        ]);

        return $statement->fetchAll();
    }

    public function create(array $data): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO saidas (
                igreja_id,
                usuario_id,
                categoria_id,
                valor,
                descricao,
                fornecedor,
                forma_pagamento,
                data_saida
            ) VALUES (
                :igreja_id,
                :usuario_id,
                :categoria_id,
                :valor,
                :descricao,
                :fornecedor,
                :forma_pagamento,
                :data_saida
            )'
        );

        $statement->execute([
            'igreja_id' => $data['igreja_id'],
            'usuario_id' => $data['usuario_id'],
            'categoria_id' => $data['categoria_id'],
            'valor' => $data['valor'],
            'descricao' => $data['descricao'],
            'fornecedor' => $data['fornecedor'],
            'forma_pagamento' => $data['forma_pagamento'],
            'data_saida' => $data['data_saida'],
        ]);

        return (int) $this->db->lastInsertId();
    }
}
