<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ReportRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function summary(int $empresaId, string $start, string $end): array
    {
        $stmt = $this->db->prepare('
            SELECT
                COUNT(*) AS sales_count,
                COALESCE(SUM(total), 0) AS total_sales,
                COALESCE(AVG(total), 0) AS average_ticket
            FROM vendas
            WHERE empresa_id = :empresa_id
              AND status <> "cancelada"
              AND DATE(criado_em) BETWEEN :start_date AND :end_date
        ');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':start_date' => $start,
            ':end_date' => $end,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['sales_count' => 0, 'total_sales' => 0, 'average_ticket' => 0];
    }

    public function sales(int $empresaId, string $start, string $end): array
    {
        $stmt = $this->db->prepare('
            SELECT v.id, v.numero_venda, v.status, v.total, v.criado_em,
                   u.nome AS vendedor, COALESCE(c.nome, "Venda balcão") AS cliente,
                   COALESCE(p.metodo, "") AS metodo
            FROM vendas v
            INNER JOIN usuarios u ON u.id = v.usuario_id
            LEFT JOIN clientes c ON c.id = v.cliente_id
            LEFT JOIN pagamentos p ON p.venda_id = v.id
            WHERE v.empresa_id = :empresa_id
              AND DATE(v.criado_em) BETWEEN :start_date AND :end_date
            ORDER BY v.criado_em DESC, v.id DESC
        ');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':start_date' => $start,
            ':end_date' => $end,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function paymentMethods(int $empresaId, string $start, string $end): array
    {
        $stmt = $this->db->prepare('
            SELECT p.metodo, COUNT(*) AS total_count, COALESCE(SUM(p.valor), 0) AS total_value
            FROM pagamentos p
            INNER JOIN vendas v ON v.id = p.venda_id
            WHERE v.empresa_id = :empresa_id
              AND v.status <> "cancelada"
              AND DATE(v.criado_em) BETWEEN :start_date AND :end_date
            GROUP BY p.metodo
            ORDER BY total_value DESC
        ');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':start_date' => $start,
            ':end_date' => $end,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function productsSold(int $empresaId, string $start, string $end): array
    {
        $stmt = $this->db->prepare('
            SELECT vi.produto_nome AS produto, COALESCE(SUM(vi.quantidade), 0) AS quantidade,
                   COALESCE(SUM(vi.subtotal), 0) AS receita
            FROM venda_itens vi
            INNER JOIN vendas v ON v.id = vi.venda_id
            WHERE v.empresa_id = :empresa_id
              AND v.status <> "cancelada"
              AND DATE(v.criado_em) BETWEEN :start_date AND :end_date
            GROUP BY vi.produto_nome
            ORDER BY quantidade DESC, receita DESC
        ');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':start_date' => $start,
            ':end_date' => $end,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lowStock(int $empresaId): array
    {
        $stmt = $this->db->prepare('
            SELECT id, nome, quantidade, estoque_minimo
            FROM produtos
            WHERE empresa_id = :empresa_id
              AND ativo = 1
              AND quantidade <= estoque_minimo
            ORDER BY quantidade ASC, nome ASC
        ');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function expiring(int $empresaId, int $days): array
    {
        $stmt = $this->db->prepare('
            SELECT id, nome, lote, DATE_FORMAT(validade, "%Y-%m-%d") AS validade, quantidade
            FROM produtos
            WHERE empresa_id = :empresa_id
              AND ativo = 1
              AND validade IS NOT NULL
              AND validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
            ORDER BY validade ASC, nome ASC
        ');
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
