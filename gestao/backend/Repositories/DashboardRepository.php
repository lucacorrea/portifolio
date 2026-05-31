<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class DashboardRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getTodaySummary(int $empresaId): array
    {
        $stmt = $this->db->prepare('
            SELECT 
                COUNT(*) as sales_count,
                COALESCE(SUM(total), 0) as total_sales
            FROM vendas
            WHERE empresa_id = :empresa_id 
              AND status = "finalizada"
              AND DATE(criado_em) = CURDATE()
        ');
        $stmt->execute(['empresa_id' => $empresaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['sales_count' => 0, 'total_sales' => 0.00];
    }

    public function getExpiringProducts(int $empresaId, int $days = 7): array
    {
        $stmt = $this->db->prepare('
            SELECT id, nome, lote, validade, quantidade 
            FROM produtos
            WHERE empresa_id = :empresa_id 
              AND ativo = 1 
              AND validade IS NOT NULL
              AND validade <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
              AND validade >= CURDATE()
              AND quantidade > 0
            ORDER BY validade ASC
            LIMIT 5
        ');
        // PDO bindParam requires explicit types for integers if emulation is off, 
        // but execute() with array treats everything as string. 
        // We'll bind manually to be safe.
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLatestSales(int $empresaId, int $limit = 3): array
    {
        $stmt = $this->db->prepare('
            SELECT v.id, v.total, v.criado_em, v.numero_venda,
                   (SELECT nome FROM usuarios WHERE id = v.usuario_id) as vendedor
            FROM vendas v
            WHERE v.empresa_id = :empresa_id
            ORDER BY v.id DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFeaturedProducts(int $empresaId, int $limit = 3): array
    {
        $stmt = $this->db->prepare('
            SELECT p.id, p.nome, SUM(vi.quantidade) as total_vendido
            FROM venda_itens vi
            JOIN vendas v ON vi.venda_id = v.id
            JOIN produtos p ON vi.produto_id = p.id
            WHERE v.empresa_id = :empresa_id 
              AND v.status = "finalizada"
              AND DATE(v.criado_em) = CURDATE()
            GROUP BY p.id, p.nome
            ORDER BY total_vendido DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
