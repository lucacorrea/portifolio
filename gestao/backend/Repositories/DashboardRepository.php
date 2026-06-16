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
                   AND v.status = \'finalizada\'
                   AND DATE(v.criado_em) = CURDATE()) AS sales_count,

                (SELECT COALESCE(SUM(v.total), 0)
                 FROM vendas v
                 WHERE v.empresa_id = :empresa_id_total
                   AND v.status = \'finalizada\'
                   AND DATE(v.criado_em) = CURDATE()) AS total_sales,

                (SELECT COALESCE(SUM((vi.preco_unitario - COALESCE(p.preco_custo, 0)) * vi.quantidade), 0)
                 FROM venda_itens vi
                 INNER JOIN vendas v ON v.id = vi.venda_id
                 LEFT JOIN produtos p ON p.id = vi.produto_id
                                      AND p.empresa_id = v.empresa_id
                 WHERE v.empresa_id = :empresa_id_profit
                   AND v.status = \'finalizada\'
                   AND DATE(v.criado_em) = CURDATE()) AS estimated_profit
        ');

        $stmt->execute([
            ':empresa_id_count' => $empresaId,
            ':empresa_id_total' => $empresaId,
            ':empresa_id_profit' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'sales_count' => (int)($row['sales_count'] ?? 0),
            'total_sales' => (float)($row['total_sales'] ?? 0),
            'estimated_profit' => (float)($row['estimated_profit'] ?? 0),
        ];
    }

    public function getMonthSummary(int $empresaId): array
    {
        $stmt = $this->db->prepare('
            SELECT
                (SELECT COUNT(*)
                 FROM vendas v
                 WHERE v.empresa_id = :empresa_id_count
                   AND v.status = \'finalizada\'
                   AND v.criado_em >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')
                   AND v.criado_em < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)) AS sales_count,

                (SELECT COALESCE(SUM(v.total), 0)
                 FROM vendas v
                 WHERE v.empresa_id = :empresa_id_total
                   AND v.status = \'finalizada\'
                   AND v.criado_em >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')
                   AND v.criado_em < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)) AS total_sales,

                (SELECT COALESCE(SUM((vi.preco_unitario - COALESCE(p.preco_custo, 0)) * vi.quantidade), 0)
                 FROM venda_itens vi
                 INNER JOIN vendas v ON v.id = vi.venda_id
                 LEFT JOIN produtos p ON p.id = vi.produto_id
                                      AND p.empresa_id = v.empresa_id
                 WHERE v.empresa_id = :empresa_id_profit
                   AND v.status = \'finalizada\'
                   AND v.criado_em >= DATE_FORMAT(CURDATE(), \'%Y-%m-01\')
                   AND v.criado_em < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)) AS estimated_profit
        ');

        $stmt->execute([
            ':empresa_id_count' => $empresaId,
            ':empresa_id_total' => $empresaId,
            ':empresa_id_profit' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'sales_count' => (int)($row['sales_count'] ?? 0),
            'total_sales' => (float)($row['total_sales'] ?? 0),
            'estimated_profit' => (float)($row['estimated_profit'] ?? 0),
        ];
    }

    public function getClientAccountsSummary(int $empresaId): array
    {
        $stmt = $this->db->prepare('
            SELECT
                COALESCE(SUM(CASE
                    WHEN cc.status <> \'cancelado\' AND cc.saldo_aberto > 0
                    THEN cc.saldo_aberto ELSE 0
                END), 0) AS total_open,

                COALESCE(SUM(CASE
                    WHEN cc.status <> \'cancelado\'
                     AND cc.saldo_aberto > 0
                     AND cc.vencimento < CURDATE()
                    THEN cc.saldo_aberto ELSE 0
                END), 0) AS total_overdue,

                COUNT(CASE
                    WHEN cc.status <> \'cancelado\' AND cc.saldo_aberto > 0
                    THEN 1
                END) AS open_count,

                COUNT(CASE
                    WHEN cc.status <> \'cancelado\'
                     AND cc.saldo_aberto > 0
                     AND cc.vencimento < CURDATE()
                    THEN 1
                END) AS overdue_count,

                COUNT(DISTINCT CASE
                    WHEN cc.status <> \'cancelado\' AND cc.saldo_aberto > 0
                    THEN cc.cliente_id
                END) AS clients_with_debt
            FROM cliente_contas cc
            WHERE cc.empresa_id = :empresa_id
        ');

        $stmt->execute([
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_open' => (float)($row['total_open'] ?? 0),
            'total_overdue' => (float)($row['total_overdue'] ?? 0),
            'open_count' => (int)($row['open_count'] ?? 0),
            'overdue_count' => (int)($row['overdue_count'] ?? 0),
            'clients_with_debt' => (int)($row['clients_with_debt'] ?? 0),
        ];
    }

    public function getPaymentMethodsToday(int $empresaId): array
    {
        $stmt = $this->db->prepare('
            SELECT
                p.metodo,
                COUNT(*) AS total_count,
                COALESCE(SUM(p.valor), 0) AS total_value
            FROM pagamentos p
            INNER JOIN vendas v ON v.id = p.venda_id
            WHERE v.empresa_id = :empresa_id
              AND DATE(v.criado_em) = CURDATE()
              AND v.status <> \'cancelada\'
            GROUP BY p.metodo
            ORDER BY total_value DESC
        ');

        $stmt->execute([
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSalesEvolution(int $empresaId, int $days = 7): array
    {
        $days = max(1, min($days, 31));

        $stmt = $this->db->prepare('
            SELECT
                DATE(v.criado_em) AS sale_date,
                COUNT(*) AS sales_count,
                COALESCE(SUM(v.total), 0) AS total_sales
            FROM vendas v
            WHERE v.empresa_id = :empresa_id
              AND v.status = \'finalizada\'
              AND v.criado_em >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(v.criado_em)
            ORDER BY sale_date ASC
        ');

        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExpiringProducts(int $empresaId, int $days = 7, int $limit = 5): array
    {
        $days = max(1, min($days, 365));
        $limit = max(1, min($limit, 20));

        $stmt = $this->db->prepare('
            SELECT
                id,
                nome,
                lote,
                validade,
                quantidade
            FROM produtos
            WHERE empresa_id = :empresa_id
              AND ativo = 1
              AND validade IS NOT NULL
              AND validade <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
              AND validade >= CURDATE()
              AND quantidade > 0
            ORDER BY validade ASC, nome ASC
            LIMIT :limit
        ');

        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExpiredProducts(int $empresaId, int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));

        $stmt = $this->db->prepare('
            SELECT
                id,
                nome,
                lote,
                validade,
                quantidade
            FROM produtos
            WHERE empresa_id = :empresa_id
              AND ativo = 1
              AND validade IS NOT NULL
              AND validade < CURDATE()
              AND quantidade > 0
            ORDER BY validade ASC, nome ASC
            LIMIT :limit
        ');

        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLatestSales(int $empresaId, int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));

        $stmt = $this->db->prepare('
            SELECT
                v.id,
                v.total,
                v.criado_em,
                v.numero_venda,
                v.status,
                COALESCE(u.nome, \'Operador\') AS vendedor
            FROM vendas v
            LEFT JOIN usuarios u ON u.id = v.usuario_id
            WHERE v.empresa_id = :empresa_id
            ORDER BY v.id DESC
            LIMIT :limit
        ');

        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFeaturedProducts(int $empresaId, int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));

        $stmt = $this->db->prepare('
            SELECT
                p.id,
                p.nome,
                COALESCE(SUM(vi.quantidade), 0) AS total_vendido,
                COALESCE(SUM(vi.quantidade * vi.preco_unitario), 0) AS total_faturado
            FROM venda_itens vi
            INNER JOIN vendas v ON vi.venda_id = v.id
            INNER JOIN produtos p ON vi.produto_id = p.id
                                 AND p.empresa_id = v.empresa_id
            WHERE v.empresa_id = :empresa_id
              AND v.status = \'finalizada\'
              AND DATE(v.criado_em) = CURDATE()
            GROUP BY p.id, p.nome
            ORDER BY total_vendido DESC, total_faturado DESC
            LIMIT :limit
        ');

        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLowStockProducts(int $empresaId, int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));

        $stmt = $this->db->prepare('
            SELECT
                id,
                nome,
                quantidade,
                estoque_minimo
            FROM produtos
            WHERE empresa_id = :empresa_id
              AND ativo = 1
              AND estoque_minimo IS NOT NULL
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