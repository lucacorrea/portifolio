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

    public function summary(int $empresaId, array $filters): array
    {
        [$where, $params] = $this->saleWhere($empresaId, $filters, 'v', 'p');

        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT v.id) AS sales_count,
                COALESCE(SUM(v.total), 0) AS total_sales,
                COALESCE(SUM(v.desconto), 0) AS total_discount,
                COALESCE(AVG(v.total), 0) AS average_ticket
            FROM vendas v
            LEFT JOIN pagamentos p ON p.venda_id = v.id
            $where
              AND v.status <> 'cancelada'
        ");
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $cancelParams = [
            ':empresa_id_cancel' => $empresaId,
            ':inicio_cancel' => $filters['start'],
            ':fim_cancel' => $filters['end'],
        ];
        $cancelSql = "
            SELECT COUNT(*) AS canceled_count
            FROM vendas v
            WHERE v.empresa_id = :empresa_id_cancel
              AND v.criado_em BETWEEN :inicio_cancel AND :fim_cancel
              AND v.status = 'cancelada'
        ";

        if (($filters['status'] ?? '') !== '') {
            $cancelSql .= ' AND v.status = :status_cancel';
            $cancelParams[':status_cancel'] = $filters['status'];
        }

        $cancelStmt = $this->db->prepare($cancelSql);
        $cancelStmt->execute($cancelParams);
        $canceled = $cancelStmt->fetch(PDO::FETCH_ASSOC) ?: ['canceled_count' => 0];

        return [
            'sales_count' => (int)($summary['sales_count'] ?? 0),
            'total_sales' => (float)($summary['total_sales'] ?? 0),
            'total_discount' => (float)($summary['total_discount'] ?? 0),
            'average_ticket' => (float)($summary['average_ticket'] ?? 0),
            'canceled_count' => (int)($canceled['canceled_count'] ?? 0),
        ];
    }

    public function estimatedCost(int $empresaId, array $filters): array
    {
        [$where, $params] = $this->saleWhere($empresaId, $filters, 'v', 'pay');

        // venda_itens has no historical cost; current product cost is used only as an estimate.
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(vi.quantidade * COALESCE(p.preco_custo, 0)), 0) AS estimated_cost
            FROM venda_itens vi
            INNER JOIN vendas v ON v.id = vi.venda_id
            LEFT JOIN produtos p
                   ON p.id = vi.produto_id
                  AND p.empresa_id = v.empresa_id
            LEFT JOIN pagamentos pay ON pay.venda_id = v.id
            $where
              AND v.status <> 'cancelada'
        ");
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['estimated_cost' => 0];
    }

    public function paymentMethods(int $empresaId, array $filters): array
    {
        [$where, $params] = $this->saleWhere($empresaId, $filters, 'v', 'p');

        $stmt = $this->db->prepare("
            SELECT
                p.metodo,
                COUNT(DISTINCT v.id) AS total_count,
                COALESCE(SUM(p.valor), 0) AS total_value
            FROM pagamentos p
            INNER JOIN vendas v ON v.id = p.venda_id
            $where
              AND v.status <> 'cancelada'
            GROUP BY p.metodo
            ORDER BY total_value DESC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function productsSold(int $empresaId, array $filters, int $limit = 10): array
    {
        [$where, $params] = $this->saleWhere($empresaId, $filters, 'v', 'p');
        $sql = "
            SELECT
                vi.produto_id,
                vi.produto_nome AS product_name,
                COALESCE(SUM(vi.quantidade), 0) AS quantity_sold,
                COALESCE(SUM(vi.subtotal), 0) AS total_value
            FROM venda_itens vi
            INNER JOIN vendas v ON v.id = vi.venda_id
            LEFT JOIN pagamentos p ON p.venda_id = v.id
            $where
              AND v.status <> 'cancelada'
        ";

        if (($filters['produto_id'] ?? 0) > 0) {
            $sql .= ' AND vi.produto_id = :produto_id_item';
            $params[':produto_id_item'] = (int)$filters['produto_id'];
        }

        $sql .= '
            GROUP BY vi.produto_id, vi.produto_nome
            ORDER BY quantity_sold DESC, total_value DESC
            LIMIT ' . max(1, min(50, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function operatorSales(int $empresaId, array $filters): array
    {
        [$where, $params] = $this->saleWhere($empresaId, $filters, 'v', 'p');

        $stmt = $this->db->prepare("
            SELECT
                v.usuario_id,
                COALESCE(u.nome, CONCAT('Usuário #', v.usuario_id)) AS operator_name,
                COUNT(DISTINCT v.id) AS sales_count,
                COALESCE(SUM(v.total), 0) AS total_sales,
                COALESCE(AVG(v.total), 0) AS average_ticket
            FROM vendas v
            LEFT JOIN usuarios u
                   ON u.id = v.usuario_id
                  AND u.empresa_id = v.empresa_id
            LEFT JOIN pagamentos p ON p.venda_id = v.id
            $where
              AND v.status <> 'cancelada'
            GROUP BY v.usuario_id, u.nome
            ORDER BY total_sales DESC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function dailySales(int $empresaId, array $filters): array
    {
        [$where, $params] = $this->saleWhere($empresaId, $filters, 'v', 'p');

        $stmt = $this->db->prepare("
            SELECT
                DATE(v.criado_em) AS sale_date,
                COUNT(DISTINCT v.id) AS sales_count,
                COALESCE(SUM(v.total), 0) AS total_sales
            FROM vendas v
            LEFT JOIN pagamentos p ON p.venda_id = v.id
            $where
              AND v.status <> 'cancelada'
            GROUP BY DATE(v.criado_em)
            ORDER BY sale_date ASC
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function salesList(int $empresaId, array $filters, int $limit = 100): array
    {
        [$where, $params] = $this->saleWhere($empresaId, $filters, 'v', 'p');
        $sql = "
            SELECT
                v.id,
                v.numero_venda,
                v.status,
                v.subtotal,
                v.desconto,
                v.total,
                v.criado_em,
                COALESCE(c.nome, 'Venda balcão') AS customer_name,
                COALESCE(u.nome, CONCAT('Usuário #', v.usuario_id)) AS operator_name,
                COALESCE(p.metodo, '') AS payment_method,
                p.parcelas AS payment_installments
            FROM vendas v
            LEFT JOIN clientes c
                   ON c.id = v.cliente_id
                  AND c.empresa_id = v.empresa_id
            LEFT JOIN usuarios u
                   ON u.id = v.usuario_id
                  AND u.empresa_id = v.empresa_id
            LEFT JOIN pagamentos p ON p.venda_id = v.id
            $where
            ORDER BY v.criado_em DESC, v.id DESC
            LIMIT " . max(1, min(300, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clientDebtSummary(int $empresaId, array $filters): array
    {
        [$where, $params] = $this->accountWhere($empresaId, $filters, 'cc');

        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(cc.saldo_aberto), 0) AS total_open,
                COALESCE(SUM(cc.valor_pago), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN cc.vencimento < CURDATE() AND cc.status NOT IN ('pago','cancelado') THEN cc.saldo_aberto ELSE 0 END), 0) AS total_overdue
            FROM cliente_contas cc
            $where
        ");
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_open' => 0, 'total_paid' => 0, 'total_overdue' => 0];
    }

    public function clientDebtRows(int $empresaId, array $filters, int $limit = 20): array
    {
        [$where, $params] = $this->accountWhere($empresaId, $filters, 'cc');
        $sql = "
            SELECT
                cc.id,
                COALESCE(c.nome, CONCAT('Cliente #', cc.cliente_id)) AS customer_name,
                cc.valor_original,
                cc.valor_pago,
                cc.saldo_aberto,
                cc.vencimento,
                cc.status
            FROM cliente_contas cc
            LEFT JOIN clientes c
                   ON c.id = cc.cliente_id
                  AND c.empresa_id = cc.empresa_id
            $where
            ORDER BY cc.saldo_aberto DESC, cc.vencimento ASC
            LIMIT " . max(1, min(100, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function filterOptions(int $empresaId): array
    {
        return [
            'users' => $this->simpleOptions('usuarios', $empresaId),
            'clients' => $this->simpleOptions('clientes', $empresaId),
            'products' => $this->simpleOptions('produtos', $empresaId),
        ];
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
        $days = max(1, min(365, $days));
        $stmt = $this->db->prepare("
            SELECT id, nome, lote, DATE_FORMAT(validade, '%Y-%m-%d') AS validade, quantidade
            FROM produtos
            WHERE empresa_id = :empresa_id
              AND ativo = 1
              AND validade IS NOT NULL
              AND validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
            ORDER BY validade ASC, nome ASC
        ");
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function simpleOptions(string $table, int $empresaId): array
    {
        $allowed = [
            'usuarios' => 'usuarios',
            'clientes' => 'clientes',
            'produtos' => 'produtos',
        ];

        $table = $allowed[$table] ?? 'clientes';
        $activeColumn = $table === 'produtos' || $table === 'clientes' || $table === 'usuarios' ? 'ativo' : null;
        $sql = "SELECT id, nome FROM $table WHERE empresa_id = :empresa_id";

        if ($activeColumn !== null) {
            $sql .= " AND $activeColumn = 1";
        }

        $sql .= ' ORDER BY nome ASC LIMIT 250';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function saleWhere(int $empresaId, array $filters, string $saleAlias, string $paymentAlias): array
    {
        $where = "WHERE $saleAlias.empresa_id = :empresa_id
                  AND $saleAlias.criado_em BETWEEN :inicio AND :fim";
        $params = [
            ':empresa_id' => $empresaId,
            ':inicio' => $filters['start'],
            ':fim' => $filters['end'],
        ];

        if (($filters['forma_pagamento'] ?? '') !== '') {
            $where .= " AND $paymentAlias.metodo = :forma_pagamento";
            $params[':forma_pagamento'] = $filters['forma_pagamento'];
        }

        if (($filters['status'] ?? '') !== '') {
            $where .= " AND $saleAlias.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (($filters['usuario_id'] ?? 0) > 0) {
            $where .= " AND $saleAlias.usuario_id = :usuario_id";
            $params[':usuario_id'] = (int)$filters['usuario_id'];
        }

        if (($filters['cliente_id'] ?? 0) > 0) {
            $where .= " AND $saleAlias.cliente_id = :cliente_id";
            $params[':cliente_id'] = (int)$filters['cliente_id'];
        }

        if (($filters['produto_id'] ?? 0) > 0) {
            $where .= " AND EXISTS (
                SELECT 1
                FROM venda_itens vi_filter
                WHERE vi_filter.venda_id = $saleAlias.id
                  AND vi_filter.produto_id = :produto_id_filter
            )";
            $params[':produto_id_filter'] = (int)$filters['produto_id'];
        }

        return [$where, $params];
    }

    private function accountWhere(int $empresaId, array $filters, string $accountAlias): array
    {
        $where = "WHERE $accountAlias.empresa_id = :empresa_id_account
                  AND $accountAlias.criado_em BETWEEN :inicio_account AND :fim_account";
        $params = [
            ':empresa_id_account' => $empresaId,
            ':inicio_account' => $filters['start'],
            ':fim_account' => $filters['end'],
        ];

        if (($filters['cliente_id'] ?? 0) > 0) {
            $where .= " AND $accountAlias.cliente_id = :cliente_id_account";
            $params[':cliente_id_account'] = (int)$filters['cliente_id'];
        }

        return [$where, $params];
    }
}
