<?php

namespace App\Controllers;

use App\Config\Database;
use PDO;

class ReportsController extends BaseController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function index()
    {
        $nivel    = $_SESSION['usuario_nivel'] ?? 'vendedor';
        $isAdmin  = ($nivel === 'admin');
        $filial_id = $isAdmin
            ? ($_GET['filial_id'] ?? 'all')
            : ($_SESSION['filial_id'] ?? 0);

        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate   = $_GET['end_date']   ?? date('Y-m-d');

        // Detect which optional columns exist once (avoids repeated DESCRIBE calls)
        $vendaColumns = $this->getColumns('vendas');
        $hasDescontoTotal = in_array('desconto_total', $vendaColumns);

        $data = [
            'kpis'             => $this->getKPIs($filial_id, $startDate, $endDate, $hasDescontoTotal),
            'sales_chart'      => $this->getSalesChartData($filial_id, $startDate, $endDate),
            'top_products'     => $this->getTopProducts($filial_id, $startDate, $endDate),
            'top_sellers'      => $this->getTopSellers($filial_id, $startDate, $endDate),
            'categories_chart' => $this->getCategoriesChartData($filial_id, $startDate, $endDate),
            'payment_chart'    => $this->getPaymentMethodsData($filial_id, $startDate, $endDate),
            'branches'         => $isAdmin ? $this->getBranches() : [],
            'filters'          => [
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'filial_id'  => $filial_id,
                'is_admin'   => $isAdmin,
            ],
        ];

        $this->render('reports_general', array_merge($data, [
            'title'     => 'Relatórios Gerais',
            'pageTitle' => 'Dashboard de Inteligência',
        ]));
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function getColumns(string $table): array
    {
        try {
            return array_column(
                $this->db->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC),
                'Field'
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    private function buildWhere(string $filial_id, string $start, string $end, array &$params): string
    {
        $where  = "WHERE v.data_venda BETWEEN :start AND :end AND v.status = 'concluido'";
        $params = [
            ':start' => $start . ' 00:00:00',
            ':end'   => $end   . ' 23:59:59',
        ];

        if ($filial_id !== 'all' && $filial_id > 0) {
            $where .= " AND v.filial_id = :filial_id";
            $params[':filial_id'] = $filial_id;
        }

        return $where;
    }

    // ─── KPIs ───────────────────────────────────────────────────────────────────

    private function getKPIs(string $filial_id, string $start, string $end, bool $hasDescontoTotal): array
    {
        $params = [];
        $where  = $this->buildWhere($filial_id, $start, $end, $params);

        $descontoCol = $hasDescontoTotal ? 'SUM(v.desconto_total)' : '0';

        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(v.id)       AS total_vendas,
                    SUM(v.valor_total) AS faturamento,
                    $descontoCol       AS total_descontos,
                    AVG(v.valor_total) AS ticket_medio
                FROM vendas v
                $where
            ");
            $stmt->execute($params);
            $kpis = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $kpis = ['total_vendas' => 0, 'faturamento' => 0, 'total_descontos' => 0, 'ticket_medio' => 0];
        }

        // Lucro Estimado: faturamento - custo atual dos produtos vendidos
        try {
            $stmtL = $this->db->prepare("
                SELECT SUM(vi.quantidade * p.preco_custo) AS total_custo
                FROM vendas_itens vi
                JOIN vendas v ON vi.venda_id = v.id
                JOIN produtos p ON vi.produto_id = p.id
                $where
            ");
            $stmtL->execute($params);
            $custo = $stmtL->fetchColumn() ?: 0;
        } catch (\Exception $e) {
            $custo = 0;
        }

        $kpis['lucro_estimado'] = ($kpis['faturamento'] ?? 0) - $custo;
        return $kpis;
    }

    // ─── Sales Chart ────────────────────────────────────────────────────────────

    private function getSalesChartData(string $filial_id, string $start, string $end): array
    {
        $params = [];
        $where  = $this->buildWhere($filial_id, $start, $end, $params);

        try {
            $stmt = $this->db->prepare("
                SELECT DATE(v.data_venda) AS data, SUM(v.valor_total) AS total
                FROM vendas v
                $where
                GROUP BY DATE(v.data_venda)
                ORDER BY DATE(v.data_venda) ASC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // ─── Top Products ───────────────────────────────────────────────────────────

    private function getTopProducts(string $filial_id, string $start, string $end): array
    {
        $params = [];
        $where  = $this->buildWhere($filial_id, $start, $end, $params);

        try {
            $stmt = $this->db->prepare("
                SELECT vi.produto_id, p.nome,
                       SUM(vi.quantidade) AS total_qtd,
                       SUM(vi.quantidade * vi.preco_unitario) AS total_valor
                FROM vendas_itens vi
                JOIN vendas v ON vi.venda_id = v.id
                JOIN produtos p ON vi.produto_id = p.id
                $where
                GROUP BY vi.produto_id, p.nome
                ORDER BY total_valor DESC
                LIMIT 10
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // ─── Top Sellers ────────────────────────────────────────────────────────────

    private function getTopSellers(string $filial_id, string $start, string $end): array
    {
        $params = [];
        $where  = $this->buildWhere($filial_id, $start, $end, $params);

        try {
            $stmt = $this->db->prepare("
                SELECT u.nome, SUM(v.valor_total) AS total_vendas, COUNT(v.id) AS qtd_vendas
                FROM vendas v
                JOIN usuarios u ON v.usuario_id = u.id
                $where
                GROUP BY v.usuario_id, u.nome
                ORDER BY total_vendas DESC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // ─── Categories Chart ───────────────────────────────────────────────────────

    private function getCategoriesChartData(string $filial_id, string $start, string $end): array
    {
        $params = [];
        $where  = $this->buildWhere($filial_id, $start, $end, $params);

        try {
            $stmt = $this->db->prepare("
                SELECT p.categoria, SUM(vi.quantidade * vi.preco_unitario) AS total
                FROM vendas_itens vi
                JOIN vendas v ON vi.venda_id = v.id
                JOIN produtos p ON vi.produto_id = p.id
                $where
                GROUP BY p.categoria
                ORDER BY total DESC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // ─── Payment Methods ────────────────────────────────────────────────────────

    private function getPaymentMethodsData(string $filial_id, string $start, string $end): array
    {
        $params = [];
        $where  = $this->buildWhere($filial_id, $start, $end, $params);

        try {
            $stmt = $this->db->prepare("
                SELECT v.forma_pagamento AS metodo, COUNT(*) AS qtd, SUM(v.valor_total) AS total
                FROM vendas v
                $where
                GROUP BY v.forma_pagamento
                ORDER BY total DESC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // ─── Branches ───────────────────────────────────────────────────────────────

    private function getBranches(): array
    {
        try {
            return $this->db
                ->query("SELECT id, nome FROM filiais ORDER BY nome ASC")
                ->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
