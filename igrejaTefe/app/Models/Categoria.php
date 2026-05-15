<?php

declare(strict_types=1);

namespace App\Models;

final class Categoria extends Model
{
    protected string $table = 'categorias';

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
