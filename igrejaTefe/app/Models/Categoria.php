<?php

declare(strict_types=1);

namespace App\Models;

final class Categoria extends Model
{
    protected string $table = 'categorias';

    public function paginateByChurch(int $igrejaId, int $limit, int $offset): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $statement = $this->db->prepare(
            "SELECT id,
                    nome,
                    descricao,
                    cor,
                    ativo,
                    criado_em
             FROM categorias
             WHERE igreja_id = :igreja_id
             ORDER BY ativo DESC, nome ASC
             LIMIT {$limit} OFFSET {$offset}"
        );

        $statement->execute([
            'igreja_id' => $igrejaId,
        ]);

        return $statement->fetchAll();
    }

    public function countByChurch(int $igrejaId): int
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*)
             FROM categorias
             WHERE igreja_id = :igreja_id'
        );

        $statement->execute([
            'igreja_id' => $igrejaId,
        ]);

        return (int) $statement->fetchColumn();
    }

    public function statusSummaryByChurch(int $igrejaId): array
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END), 0) AS ativas
             FROM categorias
             WHERE igreja_id = :igreja_id'
        );

        $statement->execute([
            'igreja_id' => $igrejaId,
        ]);
        $summary = $statement->fetch() ?: ['total' => 0, 'ativas' => 0];
        $total = (int) $summary['total'];
        $ativas = (int) $summary['ativas'];

        return [
            'total' => $total,
            'ativas' => $ativas,
            'inativas' => max(0, $total - $ativas),
        ];
    }

    public function listByChurch(int $igrejaId): array
    {
        $statement = $this->db->prepare(
            'SELECT id,
                    nome,
                    descricao,
                    cor,
                    ativo,
                    criado_em
             FROM categorias
             WHERE igreja_id = :igreja_id
             ORDER BY ativo DESC, nome ASC'
        );

        $statement->execute([
            'igreja_id' => $igrejaId,
        ]);

        return $statement->fetchAll();
    }

    public function findActiveByChurch(int $id, int $igrejaId): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, nome, cor
             FROM categorias
             WHERE id = :id
               AND igreja_id = :igreja_id
               AND ativo = 1
             LIMIT 1'
        );

        $statement->execute([
            'id' => $id,
            'igreja_id' => $igrejaId,
        ]);

        $categoria = $statement->fetch();

        return is_array($categoria) ? $categoria : null;
    }

    public function create(array $data): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO categorias (
                igreja_id,
                nome,
                descricao,
                cor,
                ativo
            ) VALUES (
                :igreja_id,
                :nome,
                :descricao,
                :cor,
                1
            )'
        );

        $statement->execute([
            'igreja_id' => $data['igreja_id'],
            'nome' => $data['nome'],
            'descricao' => $data['descricao'],
            'cor' => $data['cor'],
        ]);

        return (int) $this->db->lastInsertId();
    }
}
