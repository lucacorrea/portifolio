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
                (SELECT COUNT(*)
                 FROM vendas v
                 WHERE v.empresa_id = :empresa_id_count
                   AND v.status = "finalizada"
                   AND DATE(v.criado_em) = CURDATE()) as sales_count,
                (SELECT COALESCE(SUM(v.total), 0)
                 FROM vendas v
                 WHERE v.empresa_id = :empresa_id_total
                   AND v.status = "finalizada"
                   AND DATE(v.criado_em) = CURDATE()) as total_sales,
                (SELECT COALESCE(SUM((vi.preco_unitario - COALESCE(p.preco_custo, 0)) * vi.quantidade), 0)
                 FROM venda_itens vi
                 INNER JOIN vendas v ON v.id = vi.venda_id
                 LEFT JOIN produtos p ON p.id = vi.produto_id
                 WHERE v.empresa_id = :empresa_id_profit
                   AND v.status = "finalizada"
                   AND DATE(v.criado_em) = CURDATE()) as estimated_profit
        ');
        $stmt->execute([
            ':empresa_id_count' => $empresaId,
            ':empresa_id_total' => $empresaId,
            ':empresa_id_profit' => $empresaId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['sales_count' => 0, 'total_sales' => 0.00, 'estimated_profit' => 0.00];
    }

    public function getPaymentMethodsToday(int $empresaId): array
    {
        $stmt = $this->db->prepare('
            SELECT p.metodo, COUNT(*) as total_count, COALESCE(SUM(p.valor), 0) as total_value
            FROM pagamentos p
            INNER JOIN vendas v ON v.id = p.venda_id
            WHERE v.empresa_id = :empresa_id
              AND DATE(v.criado_em) = CURDATE()
              AND v.status <> "cancelada"
            GROUP BY p.metodo
            ORDER BY total_value DESC
        ');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    public function getLowStockProducts(int $empresaId, int $limit = 5): array
    {
        $stmt = $this->db->prepare('
            SELECT id, nome, quantidade, estoque_minimo
            FROM produtos
            WHERE empresa_id = :empresa_id
              AND ativo = 1
              AND quantidade <= estoque_minimo
            ORDER BY quantidade ASC, nome ASC
            LIMIT :limit
        ');
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
